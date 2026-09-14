<?php

declare(strict_types=1);

namespace App\Triage\Domain\Exception;

class NothingScoredException extends \RuntimeException
{
    /**
     * @param array<int, string> $reasons the distinct messages the failures carried
     */
    public function __construct(
        public readonly int $failures,
        public readonly array $reasons = [],
    ) {
        parent::__construct(rtrim(sprintf(
            "All %d items failed to classify - nothing was scored.\n%s",
            $failures,
            implode("\n", array_map(static fn (string $r): string => '  - '.$r, $reasons))
        )));
    }
}
