<?php

declare(strict_types=1);

namespace App\Triage\Domain\Gateway;

use App\Triage\Domain\Aggregate\Issue\TriagedIssue;

interface SeverityClassifierInterface
{
    /**
     * Propose a severity for one issue.
     *
     * @param array{number: int, title: string, body: string, labels: string[]} $issue
     * @param array<int, array{number: int, title: string}>                     $duplicateCandidates
     *                                                                                               Similar open issues the classifier may pick from. Supplying the
     *                                                                                               shortlist is what stops it inventing issue numbers: it chooses
     *                                                                                               from what it is handed, or returns nothing.
     *
     * @throws \App\Triage\Domain\Exception\ClassificationFailedException
     */
    public function classify(array $issue, array $duplicateCandidates = []): TriagedIssue;
}
