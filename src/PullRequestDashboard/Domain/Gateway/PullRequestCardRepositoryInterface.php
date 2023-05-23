<?php

namespace App\PullRequestDashboard\Domain\Gateway;

use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCard;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCardId;

interface PullRequestCardRepositoryInterface
{
    public function find(PullRequestCardId $pullRequestCardId): ?PullRequestCard;

    public function update(PullRequestCard $pullRequestCard): void;
}
