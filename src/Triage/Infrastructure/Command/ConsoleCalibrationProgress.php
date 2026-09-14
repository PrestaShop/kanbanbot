<?php

declare(strict_types=1);

namespace App\Triage\Infrastructure\Command;

use App\Triage\Domain\Gateway\CalibrationProgressInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Draws the run on the terminal, and in a job log.
 *
 * The estimate matters more than the bar: the operator's real question during
 * an hour-long paid run is whether to keep waiting.
 */
final class ConsoleCalibrationProgress implements CalibrationProgressInterface
{
    public function __construct(private readonly SymfonyStyle $io)
    {
    }

    public function start(int $total): void
    {
        $this->io->progressStart($total);
    }

    public function advance(): void
    {
        $this->io->progressAdvance();
    }

    public function finish(): void
    {
        $this->io->progressFinish();
    }
}
