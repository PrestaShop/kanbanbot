<?php

declare(strict_types=1);

namespace App\Triage\Domain\Aggregate\Issue;

use App\Triage\Domain\Exception\UnknownConfidenceException;

/**
 * How far the proposal can be trusted.
 *
 * `Low` is a routing instruction rather than an apology: those items are
 * pulled into their own section of the report, because the sheriff can act on
 * an honest "I was guessing" and cannot act on a confident wrong answer.
 */
enum Confidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public static function fromName(string $name): self
    {
        return self::tryFrom($name) ?? throw new UnknownConfidenceException($name);
    }
}
