<?php

declare(strict_types=1);

namespace App\Triage\Domain\Exception;

/**
 * One item could not be classified.
 *
 * Always carries the reason. A calibration that scores nothing has to say why:
 * the first real run came back green with an empty report because this was
 * swallowed, and the cause - a schema keyword the API rejects - stayed
 * invisible through two runs.
 */
class ClassificationFailedException extends \RuntimeException
{
}
