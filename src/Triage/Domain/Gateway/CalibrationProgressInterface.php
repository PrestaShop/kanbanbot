<?php

declare(strict_types=1);

namespace App\Triage\Domain\Gateway;

use App\Triage\Domain\Aggregate\Issue\Severity;

/**
 * Watches a scoring run go by, item by item.
 *
 * Two things need this and they need the same hook. A full run is a few
 * hundred sequential calls over more than an hour, so without progress a slow
 * run and a hung one look identical until the job timeout decides. And a run
 * that dies at item 300 of 334 has spent the money either way, so each verdict
 * is worth writing down as it arrives rather than only at the end.
 */
interface CalibrationProgressInterface
{
    public function start(int $total): void;

    public function scored(int $number, string $truth, Severity $proposed): void;

    public function failed(int $number, string $reason): void;

    public function finish(): void;
}
