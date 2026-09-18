<?php

declare(strict_types=1);

namespace App\Triage\Infrastructure\Command;

use App\Triage\Application\Command\CalibrateRubricCommand;
use App\Triage\Application\CommandHandler\CalibrateRubricCommandHandler;
use App\Triage\Domain\Aggregate\Issue\CalibrationResult;
use App\Triage\Domain\Aggregate\Issue\Severity;
use App\Triage\Domain\Exception\NothingScoredException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:triage:calibrate',
    description: 'Score the triage severity rubric against the labels maintainers applied',
)]
class CalibrateRubricConsoleCommand extends Command
{
    private const DEFAULT_REPOSITORY = 'PrestaShop/PrestaShop';

    public function __construct(
        private readonly CalibrateRubricCommandHandler $handler,
        private readonly string $reportPath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('repository', null, InputOption::VALUE_OPTIONAL, 'Repository whose labelled history to score against', self::DEFAULT_REPOSITORY)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Score only the first N held-out issues. The set is interleaved, so any prefix stays balanced across the four classes', '0')
            ->addOption('report', null, InputOption::VALUE_OPTIONAL, 'Where to write the scored report')
            ->addOption('repeat', null, InputOption::VALUE_OPTIONAL, 'Score the held-out set N times and report the spread between runs. Inference is not deterministic, so this is what says whether a move between two rubrics is a result or noise', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repository = $this->stringOption($input, 'repository', self::DEFAULT_REPOSITORY);

        $io->text(sprintf('Fetching the labelled corpus from %s...', $repository));

        $repeat = max(1, (int) $this->stringOption($input, 'repeat', '1'));
        $reportPath = $this->stringOption($input, 'report', $this->reportPath);
        $runs = [];

        if (!$this->ensureDirectory(dirname($reportPath))) {
            $io->warning('Could not create '.dirname($reportPath).'. Running without a checkpoint.');
        }

        try {
            for ($run = 1; $run <= $repeat; ++$run) {
                if ($repeat > 1) {
                    $io->text(sprintf('Run %d of %d...', $run, $repeat));
                }
                $runs[] = ($this->handler)(
                    new CalibrateRubricCommand(
                        repository: $repository,
                        limit: (int) $this->stringOption($input, 'limit', '0'),
                    ),
                    new ConsoleCalibrationProgress($io, $this->checkpointPath($reportPath, $run)),
                );
            }
        } catch (NothingScoredException $e) {
            // Distinguished from an empty corpus on purpose: a job that goes
            // green while every call is failing is how a broken agent stays
            // broken.
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $result = $runs[0];

        if (0 === $result->scored) {
            $io->warning('The corpus is empty - nothing to score.');

            return Command::SUCCESS;
        }

        $report = $this->render($result).$this->renderSpread($runs);

        $output->writeln('');
        $output->write($report);

        // Checked rather than assumed. The run that produced this report costs
        // real money and an hour of wall clock; announcing a file that is not
        // there would send someone looking for it after the only copy has
        // scrolled past.
        if (!$this->ensureDirectory(dirname($reportPath))) {
            $io->error('Could not create '.dirname($reportPath).'. The report above was not saved.');

            return Command::FAILURE;
        }

        if (false === file_put_contents($reportPath, $report)) {
            $io->error('Could not write '.$reportPath.'. The report above was not saved.');

            return Command::FAILURE;
        }

        $io->success('Wrote '.$reportPath);

        return Command::SUCCESS;
    }

    /**
     * What the same rubric scored on the same items, run after run.
     *
     * Nothing here is deterministic except the split: sampling is fixed by the
     * seed, the model's answers are not. Without this the temptation is to
     * read any movement after a rubric edit as the edit working.
     *
     * @param array<int, CalibrationResult> $runs
     */
    private function renderSpread(array $runs): string
    {
        if (count($runs) < 2) {
            return '';
        }

        $lines = ['', '## Run-to-run spread', '', 'The same rubric, the same held-out items, '.count($runs).' runs.', ''];
        $lines[] = '| run | exact agreement | Critical recall | Critical precision |';
        $lines[] = '|---|---|---|---|';

        $precisions = [];
        foreach ($runs as $index => $run) {
            $precisions[] = $run->precision(Severity::Critical);
            $lines[] = sprintf(
                '| %d | %.1f%% | %.0f%% | %.0f%% |',
                $index + 1,
                0 === $run->scored ? 0.0 : $run->exactAgreement() / $run->scored * 100,
                $run->recall(Severity::Critical) * 100,
                $run->precision(Severity::Critical) * 100
            );
        }

        $lines[] = '';
        $lines[] = sprintf(
            'Critical precision moved %.0f points across these runs with nothing changed. '
            .'A rubric edit has to beat that before it counts as an improvement.',
            (max($precisions) - min($precisions)) * 100
        );
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    private function ensureDirectory(string $directory): bool
    {
        return is_dir($directory) || mkdir($directory, 0o755, true) || is_dir($directory);
    }

    /**
     * Where the verdicts are written down while the run is still going.
     */
    private function checkpointPath(string $reportPath, int $run): string
    {
        return preg_replace('/\.md$/', '', $reportPath).sprintf('.run%d.jsonl', $run);
    }

    /**
     * Console options are mixed by contract, and an absent one is null rather
     * than the declared default.
     */
    private function stringOption(InputInterface $input, string $name, string $fallback): string
    {
        $value = $input->getOption($name);

        return is_string($value) && '' !== $value ? $value : $fallback;
    }

    private function render(CalibrationResult $r): string
    {
        $lines = [
            '# Calibration against maintainer labels',
            '',
            sprintf(
                'Held-out set: **%d** closed issues, each carrying exactly one severity label '
                .'applied by a maintainer. The worked examples were mined from a disjoint '
                .'pool, so none of these were shown to the model.',
                $r->scored
            ),
            '',
            sprintf(
                '- Exact agreement: **%d/%d** (%.1f%%)',
                $r->exactAgreement(),
                $r->scored,
                $r->exactAgreement() / $r->scored * 100
            ),
            sprintf(
                '- Off by one level: %d/%d (%.1f%%)',
                $r->offByOne(),
                $r->scored,
                $r->offByOne() / $r->scored * 100
            ),
            sprintf(
                '- Off by two or more: %d/%d (%.1f%%)',
                $r->scored - $r->exactAgreement() - $r->offByOne(),
                $r->scored,
                ($r->scored - $r->exactAgreement() - $r->offByOne()) / $r->scored * 100
            ),
        ];

        if ($r->failures() > 0) {
            $lines[] = sprintf('- Failed to classify: %d', $r->failures());
            // Listed, not just counted. The reason is what turns a number in
            // a report into something somebody can fix.
            $failureReasons = $r->failureReasons;
            arsort($failureReasons);
            foreach ($failureReasons as $reason => $count) {
                $lines[] = sprintf('  - %d x %s', $count, $reason);
            }
        }
        $lines[] = sprintf('- Estimated cost of this run: $%.2f', $r->estimatedCost);
        $lines[] = sprintf(
            '- Input served from cache: %.0f%%. The rubric is several times the size of the '
            .'report it is applied to, so this is what decides the figure above.',
            $r->cachedInputShare * 100
        );
        $lines[] = '';

        $lines[] = '## Confusion matrix';
        $lines[] = '';
        $lines[] = 'Rows are what the maintainers labelled, columns what the rubric proposed.';
        $lines[] = '';
        $header = '| maintainer \\ rubric |';
        $divider = '|---|';
        foreach (Severity::cases() as $level) {
            $header .= ' '.$level->value.' |';
            $divider .= '---|';
        }
        $lines[] = $header.' recall |';
        $lines[] = $divider.'---|';

        foreach (Severity::cases() as $truth) {
            $row = sprintf('| **%s** |', $truth->value);
            foreach (Severity::cases() as $proposed) {
                $row .= ' '.$r->matrix[$truth->value][$proposed->value].' |';
            }
            $lines[] = $row.sprintf(' %.0f%% |', $r->recall($truth) * 100);
        }

        $lines[] = '';
        $lines[] = '## Per-class precision';
        $lines[] = '';
        $lines[] = '| level | precision | 95% interval | proposed n |';
        $lines[] = '|---|---|---|---|';
        foreach (Severity::cases() as $level) {
            [$low, $high] = $r->precisionInterval($level);
            $lines[] = sprintf(
                '| %s | %.0f%% | %.0f%% to %.0f%% | %d |',
                $level->value,
                $r->precision($level) * 100,
                $low * 100,
                $high * 100,
                $r->proposedCount($level)
            );
        }

        $lines[] = '';
        $lines[] = '## Reading this';
        $lines[] = '';
        [$recallLow, $recallHigh] = $r->recallInterval(Severity::Critical);
        [$precisionLow, $precisionHigh] = $r->precisionInterval(Severity::Critical);
        $lines[] = sprintf(
            '**Critical recall is %.0f%% (%.0f-%.0f%%) and Critical precision is %.0f%% (%.0f-%.0f%%).** Recall is the '
            .'share of real Criticals the rubric proposed as Critical: a miss there is an '
            .'issue the sheriff never sees ranked. Precision is the counterweight - a rubric '
            .'reaches every Critical by calling everything Critical, and the sheriff stops '
            .'reading the section. Both, or neither.',
            $r->recall(Severity::Critical) * 100,
            $recallLow * 100,
            $recallHigh * 100,
            $r->precision(Severity::Critical) * 100,
            $precisionLow * 100,
            $precisionHigh * 100
        );
        $lines[] = '';
        $lines[] = 'The bracketed ranges are 95% intervals, and they are wide because each '
            .'rate is computed from a few dozen items. Two rubrics whose intervals overlap '
            .'have not been shown to differ, however far apart their headline numbers look. '
            .'They cover sampling error on this held-out set only; inference is not '
            .'deterministic either, and `--repeat` measures that second source.';
        $lines[] = '';
        $lines[] = 'Weigh the matrix rather than the headline percentage. The corpus is heavily '
            .'imbalanced, so answering "Minor" to everything would score well and say nothing. '
            .'And these labels are years of decisions by many different people: this measures '
            .'agreement with past practice, not correctness.';
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }
}
