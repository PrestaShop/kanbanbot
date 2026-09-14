<?php

declare(strict_types=1);

namespace App\Tests\Triage\Application\CommandHandler;

use App\Triage\Application\Command\CalibrateRubricCommand;
use App\Triage\Application\CommandHandler\CalibrateRubricCommandHandler;
use App\Triage\Domain\Aggregate\Issue\Confidence;
use App\Triage\Domain\Aggregate\Issue\Severity;
use App\Triage\Domain\Aggregate\Issue\TriagedIssue;
use App\Triage\Domain\Exception\NothingScoredException;
use App\Triage\Domain\Gateway\SeverityClassifierInterface;
use App\Triage\Infrastructure\Adapter\InMemoryIssueSearch;
use App\Triage\Infrastructure\Adapter\InMemorySeverityClassifier;
use PHPUnit\Framework\TestCase;

class CalibrateRubricCommandHandlerTest extends TestCase
{
    /**
     * @return array<int, array{number: int, title: string, body: string, truth: string}>
     */
    private static function corpus(int $perClass): array
    {
        $corpus = [];
        $number = 1;
        foreach (Severity::cases() as $level) {
            for ($i = 0; $i < $perClass; ++$i) {
                $corpus[] = [
                    'number' => $number++,
                    'title' => $level->value.' issue '.$i,
                    'body' => 'body',
                    'truth' => $level->value,
                ];
            }
        }

        return $corpus;
    }

    public function testItScoresEveryHeldOutIssueAgainstItsLabel(): void
    {
        $corpus = self::corpus(4);
        // A classifier that is always right: the matrix must be diagonal.
        $verdicts = [];
        foreach ($corpus as $issue) {
            $verdicts[$issue['number']] = Severity::from($issue['truth']);
        }

        $result = $this->handle($corpus, $verdicts);

        $this->assertSame(8, $result->scored, 'half of each class is held out');
        $this->assertSame(8, $result->exactAgreement());
        $this->assertSame(0, $result->offByOne());
        foreach (Severity::cases() as $level) {
            $this->assertSame(1.0, $result->recall($level));
            $this->assertSame(1.0, $result->precision($level));
        }
    }

    public function testRecallAndPrecisionMoveIndependently(): void
    {
        $corpus = self::corpus(4);
        // Everything called Critical: perfect recall on Critical, and the
        // precision that shows why recall alone is not enough.
        $verdicts = [];
        foreach ($corpus as $issue) {
            $verdicts[$issue['number']] = Severity::Critical;
        }

        $result = $this->handle($corpus, $verdicts);

        $this->assertSame(1.0, $result->recall(Severity::Critical));
        $this->assertSame(0.25, $result->precision(Severity::Critical));
        $this->assertSame(8, $result->proposedCount(Severity::Critical));
    }

    public function testOffByOneIsCountedSeparatelyFromWorseMisses(): void
    {
        $corpus = self::corpus(2);
        $verdicts = [];
        foreach ($corpus as $issue) {
            // Critical -> Major is one level; Trivial -> Critical is three.
            $verdicts[$issue['number']] = match ($issue['truth']) {
                Severity::Critical->value => Severity::Major,
                Severity::Trivial->value => Severity::Critical,
                default => Severity::from($issue['truth']),
            };
        }

        $result = $this->handle($corpus, $verdicts);

        $this->assertSame(2, $result->exactAgreement(), 'Major and Minor are still right');
        $this->assertSame(1, $result->offByOne(), 'the Critical near miss');
    }

    public function testAHeldOutPrefixStaysBalancedAcrossClasses(): void
    {
        $corpus = self::corpus(20);
        $verdicts = [];
        foreach ($corpus as $issue) {
            $verdicts[$issue['number']] = Severity::from($issue['truth']);
        }

        // A limited run must sample every class, or the matrix has one row.
        $result = $this->handle($corpus, $verdicts, limit: 8);

        foreach (Severity::cases() as $level) {
            $this->assertSame(
                2,
                array_sum($result->matrix[$level->value]),
                $level->value.' must appear in a limited run'
            );
        }
    }

    public function testSplitIsDeterministic(): void
    {
        $corpus = self::corpus(10);
        $handler = new CalibrateRubricCommandHandler(new InMemoryIssueSearch(), new InMemorySeverityClassifier());

        [, $first] = $handler->split($corpus);
        [, $second] = $handler->split($corpus);

        $this->assertSame(
            array_column($first, 'number'),
            array_column($second, 'number'),
            'two runs of the same corpus must compare like with like'
        );
    }

    public function testFailuresAreCountedWithoutStoppingTheRun(): void
    {
        $corpus = self::corpus(4);
        $verdicts = [];
        foreach ($corpus as $issue) {
            $verdicts[$issue['number']] = Severity::from($issue['truth']);
        }
        $handler = new CalibrateRubricCommandHandler(
            new InMemoryIssueSearch($corpus),
            new InMemorySeverityClassifier($verdicts, [1 => 'API rejected the request']),
        );

        $result = $handler(new CalibrateRubricCommand(repository: 'x/y'));

        $this->assertSame(1, $result->failures());
        $this->assertSame(7, $result->scored);
        $this->assertSame(
            ['API rejected the request' => 1],
            $result->failureReasons,
            'the reason is what makes a failure count actionable'
        );
    }

    public function testIdenticalFailuresAreGroupedUnderOneReason(): void
    {
        $corpus = self::corpus(4);
        $verdicts = [];
        foreach ($corpus as $issue) {
            $verdicts[$issue['number']] = Severity::from($issue['truth']);
        }

        // Failures have to land on issues that are actually scored, so they
        // are taken from the held-out half rather than guessed at.
        [, $heldOut] = (new CalibrateRubricCommandHandler(new InMemoryIssueSearch(), new InMemorySeverityClassifier()))
            ->split($corpus);
        $failures = [
            $heldOut[0]['number'] => 'maxItems is not supported',
            $heldOut[1]['number'] => 'maxItems is not supported',
            $heldOut[2]['number'] => 'a different problem',
        ];

        $handler = new CalibrateRubricCommandHandler(
            new InMemoryIssueSearch($corpus),
            new InMemorySeverityClassifier($verdicts, $failures),
        );

        $result = $handler(new CalibrateRubricCommand(repository: 'x/y'));

        $this->assertSame(
            ['maxItems is not supported' => 2, 'a different problem' => 1],
            $result->failureReasons
        );
        $this->assertSame(3, $result->failures());
    }

    public function testTheHeldOutIssuesAreSentWithoutTheirLabels(): void
    {
        // The single most dangerous regression this context can suffer, and
        // until now it was guarded by a comment. Passing the labels the
        // maintainers applied would put the answer in the question: the run
        // would report near-perfect agreement and be measuring nothing.
        $seen = [];
        $classifier = new class($seen) implements SeverityClassifierInterface {
            /**
             * @param array<int, array<string, mixed>> $seen
             */
            public function __construct(private array &$seen)
            {
            }

            /**
             * @return array<int, array<string, mixed>>
             */
            public function seen(): array
            {
                return $this->seen;
            }

            public function classify(array $issue, array $duplicateCandidates = []): TriagedIssue
            {
                $this->seen[] = $issue;

                return new TriagedIssue(
                    number: $issue['number'],
                    title: $issue['title'],
                    severity: Severity::Minor,
                    confidence: Confidence::High,
                    rationale: 'recorded',
                );
            }

            public function estimatedCost(): float
            {
                return 0.0;
            }
        };

        (new CalibrateRubricCommandHandler(new InMemoryIssueSearch(self::corpus(4)), $classifier))(
            new CalibrateRubricCommand(repository: 'x/y')
        );

        $this->assertNotEmpty($seen);
        foreach ($seen as $issue) {
            $this->assertSame([], $issue['labels'], 'a severity label here would leak the ground truth');
            $this->assertArrayNotHasKey('truth', $issue);
        }
    }

    public function testARunThatScoresNothingIsAFailureNotAnEmptyReport(): void
    {
        $corpus = self::corpus(4);
        $failures = [];
        foreach ($corpus as $issue) {
            $failures[$issue['number']] = 'API rejected the request';
        }
        $handler = new CalibrateRubricCommandHandler(
            new InMemoryIssueSearch($corpus),
            new InMemorySeverityClassifier([], $failures),
        );

        $this->expectException(NothingScoredException::class);
        $handler(new CalibrateRubricCommand(repository: 'x/y'));
    }

    /**
     * @param array<int, array{number: int, title: string, body: string, truth: string}> $corpus
     * @param array<int, Severity>                                                       $verdicts
     */
    private function handle(array $corpus, array $verdicts, int $limit = 0): \App\Triage\Domain\Aggregate\Issue\CalibrationResult
    {
        $handler = new CalibrateRubricCommandHandler(
            new InMemoryIssueSearch($corpus),
            new InMemorySeverityClassifier($verdicts),
        );

        return $handler(new CalibrateRubricCommand(repository: 'x/y', limit: $limit));
    }
}
