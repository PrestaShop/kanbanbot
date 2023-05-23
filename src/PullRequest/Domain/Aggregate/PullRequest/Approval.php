<?php

declare(strict_types=1);

namespace App\PullRequest\Domain\Aggregate\PullRequest;

class Approval
{
    public function __construct(public readonly string $author)
    {
    }
}