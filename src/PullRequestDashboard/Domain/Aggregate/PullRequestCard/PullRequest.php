<?php

declare(strict_types=1);

namespace App\PullRequestDashboard\Domain\Aggregate\PullRequestCard;

class PullRequest
{
    /**
     * @param Approval[] $approvals
     */
    public function __construct(public readonly array $approvals)
    {
    }
}