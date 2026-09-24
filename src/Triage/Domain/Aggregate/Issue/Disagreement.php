<?php

declare(strict_types=1);

namespace App\Triage\Domain\Aggregate\Issue;

/**
 * One held-out issue where the rubric did not land on the maintainers' label.
 *
 * Kept item by item because the matrix only says how often the rubric
 * disagrees, never why. Reading the reasoning next to the issue is how a
 * rubric problem is told apart from a label nobody would pick today.
 */
final class Disagreement
{
    public function __construct(
        public readonly int $number,
        public readonly string $title,
        public readonly Severity $truth,
        public readonly Severity $proposed,
        public readonly Confidence $confidence,
        public readonly string $rationale,
    ) {
    }

    public function distance(): int
    {
        return $this->truth->distanceTo($this->proposed);
    }
}
