<?php

declare(strict_types=1);

namespace App\Triage\Application\CommandHandler;

use App\Triage\Application\Command\CalibrateRubricCommand;
use App\Triage\Domain\Aggregate\Issue\CalibrationResult;
use App\Triage\Domain\Aggregate\Issue\Severity;
use App\Triage\Domain\Exception\ClassificationFailedException;
use App\Triage\Domain\Exception\NothingScoredException;
use App\Triage\Domain\Gateway\CalibrationProgressInterface;
use App\Triage\Domain\Gateway\IssueSearchInterface;
use App\Triage\Domain\Gateway\SeverityClassifierInterface;
use App\Triage\Infrastructure\Adapter\SilentCalibrationProgress;

/**
 * @phpstan-import-type LabelledIssue from IssueSearchInterface
 */
final class CalibrateRubricCommandHandler
{
    /**
     * Stratified rather than random. A random sample of this corpus holds
     * roughly ten Criticals, far too few to say anything about the one class
     * that decides whether the top of the weekly list can be trusted.
     */
    private const EVAL_PER_CLASS = 100;

    /**
     * Fixed, so that re-running after a rubric change compares like with like
     * instead of reshuffling the ground underneath it.
     */
    private const SPLIT_SEED = 42;

    public function __construct(
        private readonly IssueSearchInterface $issueSearch,
        private readonly SeverityClassifierInterface $classifier,
    ) {
    }

    public function __invoke(CalibrateRubricCommand $command, ?CalibrationProgressInterface $progress = null): CalibrationResult
    {
        $progress ??= new SilentCalibrationProgress();

        $corpus = $this->fetchCorpus($command);
        [, $heldOut] = $this->split($corpus);

        if ($command->limit > 0) {
            $heldOut = array_slice($heldOut, 0, $command->limit);
        }

        /** @var array<string, array<string, int>> $matrix */
        $matrix = [];
        foreach (Severity::cases() as $truth) {
            foreach (Severity::cases() as $proposed) {
                $matrix[$truth->value][$proposed->value] = 0;
            }
        }

        $scored = 0;
        /** @var array<string, int> $failureReasons */
        $failureReasons = [];

        $progress->start(count($heldOut));

        foreach ($heldOut as $issue) {
            try {
                $verdict = $this->classifier->classify([
                    'number' => $issue['number'],
                    'title' => $issue['title'],
                    // Deliberately bare: no labels, no milestone, no comments.
                    // Anything maintainers added after triage would leak the
                    // answer into the question.
                    'body' => $issue['body'],
                    'labels' => [],
                ]);
            } catch (ClassificationFailedException $e) {
                // The message is kept, not just the tally. A partial failure
                // is the realistic one, and "40 items failed" without saying
                // how is the one report nobody can act on.
                $reason = $e->getMessage();
                $failureReasons[$reason] = ($failureReasons[$reason] ?? 0) + 1;

                continue;
            } finally {
                $progress->advance();
            }

            ++$matrix[$issue['truth']][$verdict->severity->value];
            ++$scored;
        }

        $progress->finish();

        // An empty corpus and a run where every call failed look identical
        // from the outside, and must not: a scheduled job that reports success
        // while the agent is broken is how it stays broken.
        if (0 === $scored && [] !== $failureReasons) {
            throw new NothingScoredException(array_sum($failureReasons), array_keys($failureReasons));
        }

        return new CalibrationResult(
            matrix: $matrix,
            scored: $scored,
            failureReasons: $failureReasons,
            estimatedCost: $this->classifier->estimatedCost(),
        );
    }

    /**
     * @return array<int, LabelledIssue>
     */
    private function fetchCorpus(CalibrateRubricCommand $command): array
    {
        $corpus = [];
        $lastYear = (int) date('Y');

        foreach (Severity::cases() as $level) {
            for ($year = $command->firstYear; $year <= $lastYear; ++$year) {
                foreach ($this->issueSearch->findLabelled($command->repository, $level->value, $year) as $issue) {
                    $corpus[] = $issue;
                }
            }
        }

        return $corpus;
    }

    /**
     * Stratified, deterministic split into an example pool and a held-out set.
     *
     * The held-out half is interleaved round-robin rather than appended class
     * by class, so that any prefix of it stays balanced. Appending one class at
     * a time made a limited run score nothing but Criticals and produce a
     * confusion matrix with a single populated row.
     *
     * @param array<int, LabelledIssue> $corpus
     *
     * @return array{0: array<int, LabelledIssue>, 1: array<int, LabelledIssue>}
     */
    public function split(array $corpus): array
    {
        $byClass = [];
        foreach (Severity::cases() as $level) {
            $byClass[$level->value] = [];
        }
        foreach ($corpus as $issue) {
            $byClass[$issue['truth']][] = $issue;
        }

        $pool = [];
        $held = [];

        foreach (Severity::cases() as $level) {
            $items = $byClass[$level->value];
            // Sorted before shuffling so the split never depends on the order
            // GitHub happened to return.
            usort($items, static fn (array $a, array $b): int => $a['number'] <=> $b['number']);
            $items = $this->seededShuffle($items);

            $take = min(self::EVAL_PER_CLASS, intdiv(count($items), 2));
            $byClass[$level->value] = array_slice($items, 0, $take);
            $pool = array_merge($pool, array_slice($items, $take));
        }

        for ($index = 0;; ++$index) {
            $added = false;
            foreach (Severity::cases() as $level) {
                if (isset($byClass[$level->value][$index])) {
                    $held[] = $byClass[$level->value][$index];
                    $added = true;
                }
            }
            if (!$added) {
                break;
            }
        }

        return [$pool, $held];
    }

    /**
     * Fisher-Yates driven by a generator carried in a local variable.
     *
     * `shuffle()` would mean `mt_srand()`, which reseeds the process-wide
     * generator and makes every later call to `rand`, `shuffle` or
     * `array_rand` anywhere in the process depend on this method having run.
     * A split is not worth that. The arithmetic is a plain linear congruential
     * generator, fixed here rather than taken from the runtime so that the
     * split does not move with the PHP version.
     *
     * @param array<int, LabelledIssue> $items
     *
     * @return array<int, LabelledIssue>
     */
    private function seededShuffle(array $items): array
    {
        $state = self::SPLIT_SEED;

        for ($i = count($items) - 1; $i > 0; --$i) {
            // glibc's constants; any full-period choice would do.
            $state = (1103515245 * $state + 12345) % 2147483648;
            $j = $state % ($i + 1);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }
}
