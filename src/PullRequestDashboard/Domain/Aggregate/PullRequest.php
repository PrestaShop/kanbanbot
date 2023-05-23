<?php

declare(strict_types=1);

namespace App\PullRequestDashboard\Domain\Aggregate;

class PullRequest
{
    public function __construct(public readonly int $approvalCount)
    {
    }
}