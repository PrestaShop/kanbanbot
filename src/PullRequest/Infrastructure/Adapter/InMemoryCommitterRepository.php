<?php

declare(strict_types=1);

namespace App\PullRequest\Infrastructure\Adapter;


use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Gateway\CommitterRepositoryInterface;

class InMemoryCommitterRepository implements CommitterRepositoryInterface
{
    public function findAll(PullRequestId $pullRequestCardId): array
    {
        return [
            'lartist',
            'nicosomb',
        ];
    }

}