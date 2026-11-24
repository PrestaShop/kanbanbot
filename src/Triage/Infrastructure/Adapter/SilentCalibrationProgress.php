<?php

declare(strict_types=1);

namespace App\Triage\Infrastructure\Adapter;

use App\Triage\Domain\Aggregate\Issue\Severity;
use App\Triage\Domain\Gateway\CalibrationProgressInterface;

/**
 * Says nothing and remembers nothing, for callers that want neither.
 */
final class SilentCalibrationProgress implements CalibrationProgressInterface
{
    public function start(int $total): void
    {
    }

    public function scored(int $number, string $truth, Severity $proposed): void
    {
    }

    public function failed(int $number, string $reason): void
    {
    }

    public function finish(): void
    {
    }
}
