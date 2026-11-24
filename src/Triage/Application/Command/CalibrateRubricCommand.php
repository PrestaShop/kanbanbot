<?php

declare(strict_types=1);

namespace App\Triage\Application\Command;

/**
 * Measure how far the rubric agrees with the labels maintainers applied.
 *
 * Without this number the agent produces confident verdicts nobody can check.
 * It is also the concrete answer to "train it on five years of closed issues":
 * there is no fine-tuning, the corpus is used for grounding and measurement.
 */
final class CalibrateRubricCommand
{
    public function __construct(
        public readonly string $repository = 'PrestaShop/PrestaShop',
        public readonly int $firstYear = 2021,
        /** 0 scores the whole held-out set. */
        public readonly int $limit = 0,
    ) {
    }
}
