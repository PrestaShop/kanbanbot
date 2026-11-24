<?php

declare(strict_types=1);

namespace App\Triage\Infrastructure\Command;

use App\Triage\Domain\Aggregate\Issue\Severity;
use App\Triage\Domain\Gateway\CalibrationProgressInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Draws the run on the terminal and writes each verdict down as it arrives.
 *
 * The checkpoint is append-only and flushed per line on purpose. A run that
 * dies at item 300 of 334 has already spent the money for those 300, and the
 * difference between having them and not is one `fwrite`.
 */
final class ConsoleCalibrationProgress implements CalibrationProgressInterface
{
    /**
     * @var resource|null
     */
    private $checkpoint;

    public function __construct(
        private readonly SymfonyStyle $io,
        ?string $checkpointPath = null,
    ) {
        if (null === $checkpointPath) {
            return;
        }

        $handle = @fopen($checkpointPath, 'a');
        // Best effort. A checkpoint that cannot be opened must not take the
        // run down with it - the run is the expensive part.
        $this->checkpoint = false === $handle ? null : $handle;
    }

    public function start(int $total): void
    {
        // Append rather than truncate, so a checkpoint left behind by a run
        // that died is still there after the next attempt - losing it would
        // throw away exactly what it was written for. This header is what
        // keeps the runs apart: without it both sets of lines sit in one file
        // carrying the same issue numbers, and the salvage is ambiguous.
        $this->append(['run_started' => date(\DATE_ATOM), 'items' => $total]);
        $this->io->progressStart($total);
    }

    public function scored(int $number, string $truth, Severity $proposed): void
    {
        $this->append(['number' => $number, 'truth' => $truth, 'proposed' => $proposed->value]);
        $this->io->progressAdvance();
    }

    public function failed(int $number, string $reason): void
    {
        $this->append(['number' => $number, 'failed' => $reason]);
        $this->io->progressAdvance();
    }

    public function finish(): void
    {
        $this->io->progressFinish();

        if (null !== $this->checkpoint) {
            fclose($this->checkpoint);
            $this->checkpoint = null;
        }
    }

    /**
     * @param array<string, mixed> $line
     */
    private function append(array $line): void
    {
        if (null === $this->checkpoint) {
            return;
        }

        fwrite($this->checkpoint, json_encode($line, JSON_THROW_ON_ERROR).PHP_EOL);
    }
}
