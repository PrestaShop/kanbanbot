<?php

declare(strict_types=1);

namespace App\Triage\Domain\Exception;

class UnknownSeverityException extends \InvalidArgumentException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf(
            '"%s" is not one of the four severity levels: Critical, Major, Minor, Trivial.',
            $name
        ));
    }
}
