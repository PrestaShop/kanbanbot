<?php

declare(strict_types=1);

namespace App\Triage\Infrastructure\Command;

use App\Triage\Application\Command\CalibrateRubricCommand;
use App\Triage\Application\CommandHandler\CalibrateRubricCommandHandler;
use App\Triage\Domain\Aggregate\Issue\CalibrationResult;
use App\Triage\Domain\Aggregate\Issue\Disagreement;
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

        $report = $this->render($result, $repository).$this->renderSpread($runs);

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

    private function render(CalibrationResult $r, string $repository): string
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
        $lines[] = 'Each row is the label maintainers chose, each column what the rubric proposed. '
            .'The diagonal, from top left to bottom right, counts the issues where both agree. '
            .'Every other cell is a disagreement, and each one is listed at the end of this report.';
        $lines[] = '';
        $header = '| maintainer \\ rubric |';
        $divider = '|---|';
        foreach (Severity::cases() as $level) {
            $header .= ' '.$level->value.' |';
            $divider .= '---|';
        }
        $lines[] = $header.' found |';
        $lines[] = $divider.'---|';

        foreach (Severity::cases() as $truth) {
            $row = sprintf('| **%s** |', $truth->value);
            foreach (Severity::cases() as $proposed) {
                $row .= ' '.$r->matrix[$truth->value][$proposed->value].' |';
            }
            $lines[] = $row.sprintf(' %.0f%% |', $r->recall($truth) * 100);
        }

        $lines[] = '';
        $lines[] = '## When the rubric proposes a level, how often is it right';
        $lines[] = '';
        $lines[] = '| level | right | likely range | times proposed |';
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
        $lines[] = '## How to read this';
        $lines[] = '';
        foreach ($this->explainCritical($r) as $paragraph) {
            $lines[] = $paragraph;
            $lines[] = '';
        }
        $lines[] = sprintf(
            '**How far to trust these numbers.** They come from %d issues. With so few, a '
            .'rate could easily have come out a little higher or lower, and the "likely range" '
            .'says by how much. When comparing two runs, treat a difference as real only if '
            .'the ranges do not overlap. The model does not answer the same way twice either: '
            .'`--repeat` shows how much the numbers move with nothing changed.',
            $r->scored
        );
        $lines[] = '';
        $lines[] = '**What agreement means.** The labels were chosen by many people over several '
            .'years, and most issues are Minor, so agreeing on Minor is easy and says little. A '
            .'disagreement is not automatically a rubric error either: sometimes the label is the '
            .'odd one out. Reading the cases below is how to tell the two apart.';
        $lines[] = '';

        foreach ($this->renderDisagreements($r, $repository) as $line) {
            $lines[] = $line;
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * The two Critical rates, said with the counts behind them.
     *
     * A percentage alone asks the reader to know what recall and precision
     * are. "3 of 5" does not, and it shows how few items the rate rests on.
     *
     * @return array<int, string>
     */
    private function explainCritical(CalibrationResult $r): array
    {
        $found = $r->matrix[Severity::Critical->value][Severity::Critical->value] ?? 0;
        $labelled = array_sum($r->matrix[Severity::Critical->value] ?? []);
        $proposed = $r->proposedCount(Severity::Critical);
        $paragraphs = [];

        if (0 === $labelled) {
            $paragraphs[] = '**No issue labelled Critical was scored in this run**, so it says '
                .'nothing about the one level that decides the top of the sheriff\'s list.';
        } else {
            [$low, $high] = $r->recallInterval(Severity::Critical);
            $paragraphs[] = sprintf(
                '**Critical issues found: %d of %d.** Of the %d issues maintainers labelled '
                .'Critical, the rubric recognised %s. %s Likely range: %.0f%% to %.0f%%.',
                $found,
                $labelled,
                $labelled,
                0 === $found ? 'none' : (string) $found,
                $found === $labelled
                    ? 'None was missed.'
                    : sprintf(
                        'The other %d would not have reached the top of the sheriff\'s list, '
                        .'which is the costliest mistake it can make.',
                        $labelled - $found
                    ),
                $low * 100,
                $high * 100
            );
        }

        if (0 === $proposed) {
            $paragraphs[] = '**The rubric proposed no Critical in this run.**';
        } else {
            [$low, $high] = $r->precisionInterval(Severity::Critical);
            $paragraphs[] = sprintf(
                '**Critical proposals that were right: %d of %d.** This keeps the first number '
                .'honest: a rubric that called everything Critical would find every real one, and '
                .'the sheriff would soon stop trusting the label. Likely range: %.0f%% to %.0f%%.',
                $found,
                $proposed,
                $low * 100,
                $high * 100
            );
        }

        return $paragraphs;
    }

    /**
     * Every issue the rubric put on another level, furthest first.
     *
     * The matrix can only say how often the rubric disagrees. This is the
     * part that says where, so that a surprising number can be investigated
     * rather than argued about.
     *
     * @return array<int, string>
     */
    private function renderDisagreements(CalibrationResult $r, string $repository): array
    {
        $lines = ['## Where the rubric disagreed', ''];

        if ([] === $r->disagreements) {
            $lines[] = 'Nowhere: the rubric matched every label.';
            $lines[] = '';

            return $lines;
        }

        $lines[] = sprintf(
            '%d of the %d issues, furthest from the maintainers\' label first. The reasoning '
            .'is the model\'s own, as it gave it.',
            count($r->disagreements),
            $r->scored
        );
        $lines[] = '';

        $levels = Severity::cases();
        $disagreements = $r->disagreements;
        usort(
            $disagreements,
            static fn (Disagreement $a, Disagreement $b): int => [$b->distance(), array_search($a->truth, $levels, true), $a->number]
                <=> [$a->distance(), array_search($b->truth, $levels, true), $b->number]
        );

        $group = null;
        foreach ($disagreements as $disagreement) {
            $heading = $disagreement->distance() >= 2 ? '### Two levels apart or more' : '### One level apart';
            if ($heading !== $group) {
                if (null !== $group) {
                    $lines[] = '';
                }
                $lines[] = $heading;
                $lines[] = '';
                $group = $heading;
            }

            $lines[] = sprintf(
                '- [#%d](https://github.com/%s/issues/%d) %s  ',
                $disagreement->number,
                $repository,
                $disagreement->number,
                $this->inline($disagreement->title)
            );
            $lines[] = sprintf(
                '  Maintainers: **%s**, rubric: **%s**, confidence: %s',
                $disagreement->truth->value,
                $disagreement->proposed->value,
                $disagreement->confidence->value
            );
            $lines[] = '  > '.$this->inline($disagreement->rationale);
        }
        $lines[] = '';

        return $lines;
    }

    /**
     * Untrusted text made safe for one line of Markdown.
     *
     * Titles come from anyone who opens an issue and rationales from the
     * model reading them, and both land in a page maintainers read. Collapsed
     * so a newline cannot break out of the list item, escaped so neither can
     * inject a link, an image or a table.
     */
    private function inline(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        return (string) preg_replace('/([\\\\`*_\[\]<>|#!~])/', '\\\\$1', $text);
    }
}
