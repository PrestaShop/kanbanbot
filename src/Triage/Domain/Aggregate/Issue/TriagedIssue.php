<?php

declare(strict_types=1);

namespace App\Triage\Domain\Aggregate\Issue;

/**
 * One issue as the triage agent sees it: what was reported, and what the
 * rubric proposes for it.
 *
 * Deliberately a proposal and never a decision. Nothing in this context writes
 * a label or a board field; a human accepts, corrects or ignores every one.
 */
final class TriagedIssue
{
    /**
     * @param int[] $duplicateCandidates issue numbers, from the shortlist the
     *                                   classifier was handed - never invented
     */
    public function __construct(
        public readonly int $number,
        public readonly string $title,
        public readonly Severity $severity,
        public readonly Confidence $confidence,
        public readonly string $rationale,
        public readonly bool $securitySuspicion = false,
        public readonly bool $looksLikeRegression = false,
        public readonly array $duplicateCandidates = [],
    ) {
    }

    /**
     * Whether the sheriff should look at this one this week rather than in the
     * normal queue.
     *
     * A security suspicion counts whatever the severity: a false positive
     * costs a maintainer one minute, a missed vulnerability costs far more.
     */
    public function needsAttentionNow(): bool
    {
        return $this->securitySuspicion
            || Severity::Critical === $this->severity
            || ($this->looksLikeRegression && Severity::Major === $this->severity);
    }
}
