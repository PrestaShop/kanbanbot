<?php

declare(strict_types=1);

namespace App\PullRequestDashboard\Infrastructure\Adapter;

use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCard;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCardId;
use App\PullRequestDashboard\Domain\Gateway\CommitterRepositoryInterface;
use App\PullRequestDashboard\Domain\Gateway\PullRequestCardRepositoryInterface;

class InMemoryCommitterRepository implements CommitterRepositoryInterface
{
    public function findAll(PullRequestCardId $pullRequestCardId): array
    {
        return [
            'lartist',
            'nicosomb',
        ];
    }

}