<?php

declare(strict_types=1);

namespace App\Triage\Infrastructure\Adapter;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Messages\Message;
use Anthropic\Messages\TextBlock;
use App\Triage\Domain\Aggregate\Issue\Confidence;
use App\Triage\Domain\Aggregate\Issue\Severity;
use App\Triage\Domain\Aggregate\Issue\TriagedIssue;
use App\Triage\Domain\Exception\ClassificationFailedException;
use App\Triage\Domain\Gateway\SeverityClassifierInterface;
use App\Triage\Infrastructure\Provider\RubricProviderInterface;

/**
 * Proposes a severity by sending one issue to Claude against the rubric.
 *
 * One request per item, with a structured-output schema so the answer needs no
 * parsing heuristics. The rubric is byte-stable across a run and is sent as a
 * cached block: the first item pays for it, the rest read it back at a tenth
 * of the price.
 */
final class AnthropicSeverityClassifier implements SeverityClassifierInterface
{
    private const MODEL = 'claude-opus-5';

    /**
     * A verdict is a handful of short fields; this only has to leave room for
     * adaptive thinking plus the JSON object.
     */
    private const MAX_TOKENS = 4000;

    /**
     * Applying a written rubric to a report is matching rather than
     * open-ended reasoning, so medium is the right trade.
     */
    private const EFFORT = 'medium';

    private const MAX_ATTEMPTS = 3;

    private ?Client $client = null;

    /**
     * @var array{input: int, output: int, cacheWrite: int, cacheRead: int}
     */
    private array $usage = ['input' => 0, 'output' => 0, 'cacheWrite' => 0, 'cacheRead' => 0];

    public function __construct(
        private readonly RubricProviderInterface $rubricProvider,
        private readonly string $anthropicApiKey,
    ) {
    }

    public function classify(array $issue, array $duplicateCandidates = []): TriagedIssue
    {
        $verdict = $this->askClaude($this->renderIssue($issue, $duplicateCandidates));

        $strings = [];
        foreach (['severity', 'confidence', 'rationale'] as $required) {
            if (!isset($verdict[$required]) || !is_string($verdict[$required])) {
                throw new ClassificationFailedException(sprintf('Verdict for #%d is missing "%s"', $issue['number'], $required));
            }
            $strings[$required] = $verdict[$required];
        }

        $candidates = array_values(array_filter(
            is_array($verdict['duplicate_candidates'] ?? null) ? $verdict['duplicate_candidates'] : [],
            static fn ($n): bool => is_int($n)
        ));

        return new TriagedIssue(
            number: $issue['number'],
            title: $issue['title'],
            severity: Severity::fromName($strings['severity']),
            confidence: Confidence::fromName($strings['confidence']),
            rationale: $strings['rationale'],
            securitySuspicion: (bool) ($verdict['security_suspicion'] ?? false),
            looksLikeRegression: (bool) ($verdict['looks_like_regression'] ?? false),
            // Capped here rather than in the schema: structured outputs reject
            // maxItems, so the limit is a rubric instruction and this is what
            // makes it hold.
            duplicateCandidates: array_slice($candidates, 0, 3),
        );
    }

    /**
     * @return array{input: int, output: int, cacheWrite: int, cacheRead: int}
     */
    public function usage(): array
    {
        return $this->usage;
    }

    /**
     * Cost of the run so far at published list prices.
     *
     * Cache reads bill at a tenth of the input rate and writes at 1.25x, which
     * is why a well-cached run costs far less than the raw input count suggests.
     */
    public function estimatedCost(): float
    {
        $inputPerMTok = 5.00;
        $outputPerMTok = 25.00;

        return (
            $this->usage['input'] * $inputPerMTok
            + $this->usage['cacheWrite'] * $inputPerMTok * 1.25
            + $this->usage['cacheRead'] * $inputPerMTok * 0.1
            + $this->usage['output'] * $outputPerMTok
        ) / 1_000_000;
    }

    /**
     * @return array<string, mixed>
     */
    private function askClaude(string $userText): array
    {
        $lastError = null;

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; ++$attempt) {
            try {
                $message = $this->client()->messages->create(
                    maxTokens: self::MAX_TOKENS,
                    messages: [['role' => 'user', 'content' => $userText]],
                    model: self::MODEL,
                    outputConfig: [
                        'effort' => self::EFFORT,
                        'format' => ['type' => 'json_schema', 'schema' => $this->rubricProvider->issueSchema()],
                    ],
                    system: [[
                        'type' => 'text',
                        'text' => $this->rubricProvider->severityRubric(),
                        'cacheControl' => ['type' => 'ephemeral', 'ttl' => '1h'],
                    ]],
                    thinking: ['type' => 'adaptive'],
                );
            } catch (RateLimitException|APIConnectionException|InternalServerException $e) {
                // Transient: throttling, a dropped connection, a 5xx.
                $lastError = $e;
                if ($attempt < self::MAX_ATTEMPTS - 1) {
                    sleep(5 * (2 ** $attempt));
                }

                continue;
            } catch (APIStatusException $e) {
                // Anything else the API rejected is our bug - a malformed
                // schema, a bad model id, a missing key - and retrying just
                // repeats it. The message is carried through: a run that fails
                // on every item has to say why.
                throw new ClassificationFailedException('API rejected the request: '.$e->getMessage(), 0, $e);
            }

            if ('refusal' === $message->stopReason) {
                throw new ClassificationFailedException('Model declined to answer this item');
            }

            $this->recordUsage($message);

            return $this->extractJson($message);
        }

        throw new ClassificationFailedException(sprintf('Gave up after %d attempts: %s', self::MAX_ATTEMPTS, $lastError?->getMessage() ?? 'unknown error'));
    }

    private function client(): Client
    {
        if (null === $this->client) {
            if ('' === $this->anthropicApiKey) {
                throw new ClassificationFailedException('ANTHROPIC_API_KEY is not configured');
            }
            $this->client = new Client(apiKey: $this->anthropicApiKey);
        }

        return $this->client;
    }

    private function recordUsage(Message $message): void
    {
        $usage = $message->usage;
        $this->usage['input'] += $usage->inputTokens ?? 0;
        $this->usage['output'] += $usage->outputTokens ?? 0;
        $this->usage['cacheWrite'] += $usage->cacheCreationInputTokens ?? 0;
        $this->usage['cacheRead'] += $usage->cacheReadInputTokens ?? 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractJson(Message $message): array
    {
        foreach ($message->content as $block) {
            // Narrowed by type rather than by a `type` property: a response's
            // content is a union of block kinds and thinking blocks come first
            // when adaptive thinking is on.
            if (!$block instanceof TextBlock) {
                continue;
            }
            // Always decoded, never string-matched: escaping inside structured
            // output can vary between models.
            $decoded = json_decode($block->text, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new ClassificationFailedException('No JSON object found in the response');
    }

    /**
     * @param array{number: int, title: string, body: string, labels: string[]} $issue
     * @param array<int, array{number: int, title: string}>                     $duplicateCandidates
     */
    private function renderIssue(array $issue, array $duplicateCandidates): string
    {
        $lines = [
            sprintf('# Issue #%d: %s', $issue['number'], $issue['title']),
            '',
            '- Existing labels: '.(implode(', ', $issue['labels']) ?: 'none'),
            '',
            '## Body',
            '',
            '' !== $issue['body'] ? $issue['body'] : '_(empty)_',
            '',
            '## Candidate duplicates',
            '',
        ];

        if ([] === $duplicateCandidates) {
            $lines[] = 'None found - return an empty list.';
        } else {
            $lines[] = 'You may only return numbers from this list, and only if the other '
                .'issue describes the same underlying defect:';
            foreach ($duplicateCandidates as $candidate) {
                $lines[] = sprintf('- #%d: %s', $candidate['number'], $candidate['title']);
            }
        }

        return implode(PHP_EOL, $lines);
    }
}
