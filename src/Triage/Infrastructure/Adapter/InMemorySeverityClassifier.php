<?php

declare(strict_types=1);

namespace App\Triage\Infrastructure\Adapter;

use App\Triage\Domain\Aggregate\Issue\Confidence;
use App\Triage\Domain\Aggregate\Issue\Severity;
use App\Triage\Domain\Aggregate\Issue\TriagedIssue;
use App\Triage\Domain\Exception\ClassificationFailedException;
use App\Triage\Domain\Gateway\SeverityClassifierInterface;

/**
 * Test double returning verdicts decided in advance.
 *
 * Also able to fail on demand: a handler that scores nothing must be
 * distinguishable from one that had nothing to score, and that path needs a
 * test of its own.
 */
final class InMemorySeverityClassifier implements SeverityClassifierInterface
{
    /**
     * @param array<int, Severity> $verdicts keyed by issue number
     * @param array<int, string>   $failures keyed by issue number, the message to throw
     */
    public function __construct(
        private array $verdicts = [],
        private array $failures = [],
    ) {
    }

    public function classify(array $issue, array $duplicateCandidates = []): TriagedIssue
    {
        $number = $issue['number'];

        if (isset($this->failures[$number])) {
            throw new ClassificationFailedException($this->failures[$number]);
        }

        if (!isset($this->verdicts[$number])) {
            throw new ClassificationFailedException(sprintf('No verdict staged for #%d', $number));
        }

        return new TriagedIssue(
            number: $number,
            title: $issue['title'],
            severity: $this->verdicts[$number],
            confidence: Confidence::High,
            rationale: 'staged verdict',
        );
    }

    /**
     * Nothing is sent anywhere, so nothing is spent.
     */
    public function estimatedCost(): float
    {
        return 0.0;
    }

    public function cachedInputShare(): float
    {
        return 0.0;
    }
}
