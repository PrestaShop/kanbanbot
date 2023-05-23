<?php

declare(strict_types=1);

namespace App\PullRequest\Domain\Gateway;

use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;

//Todo: mutualize with the one in PullRequestDashboard Bounded Countext
interface CommitterRepositoryInterface
{
    /**
     * @return string[]
     * Todo: replace pullRequestId by just the necessary
     */
    public function findAll(PullRequestId $pullRequestId): array;
}