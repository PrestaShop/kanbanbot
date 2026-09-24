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
     * @param array<string, array<string, int>> $matrix         truth => proposal => count
     * @param array<string, int>                $failureReasons message => how many items it happened to
     * @param array<int, Disagreement>          $disagreements  every scored item the rubric put on another level
     */
    public function __construct(
        public readonly array $matrix,
        public readonly int $scored,
        public readonly array $failureReasons,
        public readonly float $estimatedCost,
        public readonly float $cachedInputShare = 0.0,
        public readonly array $disagreements = [],
    ) {
    }

    /**
     * Items the classifier could not answer for.
     *
     * Grouped by message rather than counted, because the count alone is the
     * one thing that cannot be acted on. Forty failures reading "API rejected
     * the request: maxItems is not supported" is a schema to fix; forty
     * reading "the client exhausted its retries" is a rate limit to pace.
     */
    public function failures(): int
    {
        return array_sum($this->failureReasons);
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
        $column = $this->proposedCount($level);

        return $column > 0 ? ($this->matrix[$level->value][$level->value] ?? 0) / $column : 0.0;
    }

    /**
     * A 95% interval around a rate, given how few items it was computed from.
     *
     * The point estimate on its own invites a comparison it cannot support.
     * Critical precision is computed from however many Criticals the rubric
     * proposed, often under a hundred, so a five-point move between two runs
     * sits well inside the noise. Printing the interval next to the rate is
     * what stops that move being read as a result.
     *
     * Wilson rather than the textbook normal interval, which misbehaves at
     * the small counts and lopsided rates this corpus produces.
     *
     * @return array{0: float, 1: float} lower and upper bound
     */
    public function interval(int $successes, int $total): array
    {
        if ($total <= 0) {
            return [0.0, 0.0];
        }

        $z = 1.96;
        $p = $successes / $total;
        $denominator = 1 + $z ** 2 / $total;
        $centre = ($p + $z ** 2 / (2 * $total)) / $denominator;
        $spread = $z / $denominator * sqrt($p * (1 - $p) / $total + $z ** 2 / (4 * $total ** 2));

        return [max(0.0, $centre - $spread), min(1.0, $centre + $spread)];
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function recallInterval(Severity $level): array
    {
        return $this->interval(
            $this->matrix[$level->value][$level->value] ?? 0,
            array_sum($this->matrix[$level->value] ?? [])
        );
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function precisionInterval(Severity $level): array
    {
        return $this->interval(
            $this->matrix[$level->value][$level->value] ?? 0,
            $this->proposedCount($level)
        );
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
