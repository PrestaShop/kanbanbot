<?php

declare(strict_types=1);

namespace App\Triage\Domain\Exception;

class NothingScoredException extends \RuntimeException
{
    public function __construct(public readonly int $failures)
    {
        parent::__construct(sprintf(
            'All %d items failed to classify - nothing was scored.',
            $failures
        ));
    }
}
