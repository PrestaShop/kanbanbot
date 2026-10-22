<?php

declare(strict_types=1);

namespace App\PullRequest\Domain\Aggregate\PullRequest;

class PullRequestDiffHunk
{
    public function __construct(
        private readonly string $head,
        private readonly string $diff,
    ) {
    }

    public function getHead(): string
    {
        return $this->head;
    }

    public function getDiff(): string
    {
        return $this->diff;
    }

    public function getOld(): string
    {
        $lines = explode("\n", $this->diff);
        $old = [];
        foreach ($lines as $line) {
            if (!preg_match('/^\+(.*)/', $line) && preg_match('/^-?(.*)/', $line, $matches)) {
                $old[] = $matches[1];
            }
        }

        return implode("\n", $old);
    }

    public function getNew(): string
    {
        $lines = explode("\n", $this->diff);
        $new = [];
        foreach ($lines as $line) {
            if (!preg_match('/^-(.*)/', $line) && preg_match('/^\+?(.*)/', $line, $matches)) {
                $new[] = $matches[1];
            }
        }

        return implode("\n", $new);
    }
}
