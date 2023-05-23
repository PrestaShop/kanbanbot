<?php

declare(strict_types=1);

namespace App\PullRequestDashboard\Domain\Gateway;

use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCardId;

interface CommitterRepositoryInterface
{
    /**
     * @return string[]
     * Todo: replace pullRequestId by just the necessary
     */
    public function findAll(PullRequestCardId $pullRequestCardId): array;
}