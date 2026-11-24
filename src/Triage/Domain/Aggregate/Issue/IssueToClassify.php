<?php

declare(strict_types=1);

namespace App\Triage\Domain\Aggregate\Issue;

/**
 * What the classifier is allowed to see about an issue.
 *
 * A type rather than an array shape, because the shape was the contract and it
 * was restated in four docblocks that could drift apart without anything
 * failing. What it leaves out is the point: no milestone, no comments, and in
 * particular no severity label. During calibration those are exactly what
 * maintainers added *after* triage, so passing them would put the answer in
 * the question and the run would report near-perfect agreement while measuring
 * nothing.
 */
final class IssueToClassify
{
    /**
     * @param string[] $labels non-severity labels only, and empty during calibration
     */
    public function __construct(
        public readonly int $number,
        public readonly string $title,
        public readonly string $body,
        public readonly array $labels = [],
    ) {
    }
}
