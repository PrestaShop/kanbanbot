<?php

declare(strict_types=1);

namespace App\Core\Domain\Aggregate\PR;

class PRId
{

    private function __construct(
        public readonly string $repositoryOwner,
        public readonly string $repositoryName,
        public readonly string $pullRequestNumber,
    )
    {
    }

    public static function create(string $repositoryOwner, string $repositoryName, string $pullRequestNumber): self
    {
        return new self($repositoryOwner, $repositoryName, $pullRequestNumber);
    }
}