<?php

declare(strict_types=1);

namespace App\PullRequestDashboard\Domain\Aggregate\PullRequestCard;

class PullRequestCardId
{

    public function __construct(
        public readonly string $projectNumber,
        public readonly string $repositoryOwner,
        public readonly string $repositoryName,
        public readonly string $pullRequestNumber,
    )
    {
    }
}