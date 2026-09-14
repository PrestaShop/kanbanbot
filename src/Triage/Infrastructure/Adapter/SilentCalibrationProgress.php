<?php

declare(strict_types=1);

namespace App\Triage\Infrastructure\Adapter;

use App\Triage\Domain\Gateway\CalibrationProgressInterface;

/**
 * Says nothing, for callers that have nowhere to say it.
 */
final class SilentCalibrationProgress implements CalibrationProgressInterface
{
    public function start(int $total): void
    {
    }

    public function advance(): void
    {
    }

    public function finish(): void
    {
    }
}
