<?php

declare(strict_types=1);

namespace App\PullRequestDashboard\Domain\Aggregate\PullRequestCard;

class Approval
{

    public function __construct(public readonly string $author)
    {
    }
}