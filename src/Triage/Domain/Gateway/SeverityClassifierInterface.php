<?php

declare(strict_types=1);

namespace App\Triage\Domain\Gateway;

use App\Triage\Domain\Aggregate\Issue\IssueToClassify;
use App\Triage\Domain\Aggregate\Issue\TriagedIssue;

interface SeverityClassifierInterface
{
    /**
     * Propose a severity for one issue.
     *
     * `$duplicateCandidates` is a shortlist of similar open issues the
     * classifier may pick from. Supplying it is what stops the classifier
     * inventing issue numbers: it chooses from what it is handed, or returns
     * nothing.
     *
     * @param array<int, array{number: int, title: string}> $duplicateCandidates
     *
     * @throws \App\Triage\Domain\Exception\ClassificationFailedException
     */
    public function classify(IssueToClassify $issue, array $duplicateCandidates = []): TriagedIssue;

    /**
     * What the classifications made so far cost, at published list prices.
     *
     * On the port rather than on the adapter: a calibration report that does
     * not say what the run cost cannot be weighed against running it again,
     * and reaching through the port to ask would defeat the point of having
     * one. An implementation that spends nothing returns 0.0.
     */
    public function estimatedCost(): float;

    /**
     * The share of input tokens that were served from cache, 0 to 1.
     *
     * The rubric is several times the size of the report it is applied to, so
     * this number decides what a run costs. It is also what any argument about
     * moving to the Batch API has to be settled against: the batch discount is
     * a flat 50%, and it is only a saving if it beats what caching is already
     * doing here. An implementation that sends nothing returns 0.0.
     */
    public function cachedInputShare(): float;
}
