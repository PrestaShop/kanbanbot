<?php

declare(strict_types=1);

namespace App\Triage\Domain\Aggregate\Issue;

/**
 * The scored outcome of a calibration run.
 *
 * Carries the matrix rather than a headline percentage on purpose. The corpus
 * is heavily imbalanced - 68 Criticals against 1378 Minors in the labelled
 * history - so plain accuracy looks good while saying nothing: answering
 * "Minor" to everything would score well.
 */
final class CalibrationResult
{
    /**
     * @param array<string, array<string, int>> $matrix truth => proposal => count
     */
    public function __construct(
        public readonly array $matrix,
        public readonly int $scored,
        public readonly int $failures,
        public readonly float $estimatedCost,
    ) {
    }

    public function exactAgreement(): int
    {
        $exact = 0;
        foreach (Severity::cases() as $level) {
            $exact += $this->matrix[$level->value][$level->value] ?? 0;
        }

        return $exact;
    }

    /**
     * Proposals that landed one level away from the truth.
     *
     * Reported separately because the two failure directions are not equally
     * costly: a Major proposed as Critical costs the sheriff one glance, a
     * Critical proposed as Minor is an issue they never see ranked.
     */
    public function offByOne(): int
    {
        $count = 0;
        foreach (Severity::cases() as $truth) {
            foreach (Severity::cases() as $proposed) {
                if (1 === $truth->distanceTo($proposed)) {
                    $count += $this->matrix[$truth->value][$proposed->value] ?? 0;
                }
            }
        }

        return $count;
    }

    /**
     * Share of a level's issues the rubric also proposed at that level.
     *
     * Critical recall is the number that decides whether the top of the weekly
     * list can be trusted.
     */
    public function recall(Severity $level): float
    {
        $row = array_sum($this->matrix[$level->value] ?? []);

        return $row > 0 ? ($this->matrix[$level->value][$level->value] ?? 0) / $row : 0.0;
    }

    /**
     * Share of proposals at a level that were right.
     *
     * The counterweight to recall: a rubric can reach every Critical by
     * calling everything Critical, and the sheriff stops reading the section.
     */
    public function precision(Severity $level): float
    {
        $column = 0;
        foreach (Severity::cases() as $truth) {
            $column += $this->matrix[$truth->value][$level->value] ?? 0;
        }

        return $column > 0 ? ($this->matrix[$level->value][$level->value] ?? 0) / $column : 0.0;
    }

    public function proposedCount(Severity $level): int
    {
        $column = 0;
        foreach (Severity::cases() as $truth) {
            $column += $this->matrix[$truth->value][$level->value] ?? 0;
        }

        return $column;
    }
}
