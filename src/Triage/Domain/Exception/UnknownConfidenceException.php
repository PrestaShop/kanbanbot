<?php

declare(strict_types=1);

namespace App\Triage\Domain\Exception;

class UnknownConfidenceException extends \InvalidArgumentException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('"%s" is not one of: high, medium, low.', $name));
    }
}
