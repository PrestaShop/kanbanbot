<?php

declare(strict_types=1);

namespace App\Triage\Domain\Gateway;

/**
 * Reports how far a scoring run has got.
 *
 * A full run is a few hundred sequential calls over more than an hour. Without
 * this the command prints one line and then nothing, and there is no way to
 * tell a slow run from a hung one until the job's timeout decides for you.
 */
interface CalibrationProgressInterface
{
    public function start(int $total): void;

    public function advance(): void;

    public function finish(): void;
}
