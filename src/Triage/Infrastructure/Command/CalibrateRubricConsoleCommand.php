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
    public function __construct(
        private readonly CalibrateRubricCommandHandler $handler,
        private readonly string $reportPath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('repository', null, InputOption::VALUE_OPTIONAL, '', 'PrestaShop/PrestaShop')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Score only the first N held-out issues. The set is interleaved, so any prefix stays balanced across the four classes', 0)
            ->addOption('report', null, InputOption::VALUE_OPTIONAL, 'Where to write the scored report');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repository = $this->stringOption($input, 'repository', 'PrestaShop/PrestaShop');

        $io->text(sprintf('Fetching the labelled corpus from %s...', $repository));

        try {
            $result = ($this->handler)(new CalibrateRubricCommand(
                repository: $repository,
                limit: (int) $this->stringOption($input, 'limit', '0'),
            ));
        } catch (NothingScoredException $e) {
            // Distinguished from an empty corpus on purpose: a job that goes
            // green while every call is failing is how a broken agent stays
            // broken.
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (0 === $result->scored) {
            $io->warning('The corpus is empty - nothing to score.');

            return Command::SUCCESS;
        }

        $report = $this->render($result);

        $path = $this->stringOption($input, 'report', $this->reportPath);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o777, true);
        }
        file_put_contents($path, $report);

        $output->writeln('');
        $output->write($report);
        $io->success('Wrote '.$path);

        return Command::SUCCESS;
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

        if ($r->failures > 0) {
            $lines[] = sprintf('- Failed to classify: %d', $r->failures);
        }
        $lines[] = sprintf('- Estimated cost of this run: $%.2f', $r->estimatedCost);
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
        $lines[] = '| level | precision | proposed n |';
        $lines[] = '|---|---|---|';
        foreach (Severity::cases() as $level) {
            $lines[] = sprintf(
                '| %s | %.0f%% | %d |',
                $level->value,
                $r->precision($level) * 100,
                $r->proposedCount($level)
            );
        }

        $lines[] = '';
        $lines[] = '## Reading this';
        $lines[] = '';
        $lines[] = sprintf(
            '**Critical recall is %.0f%% and Critical precision is %.0f%%.** Recall is the '
            .'share of real Criticals the rubric proposed as Critical: a miss there is an '
            .'issue the sheriff never sees ranked. Precision is the counterweight - a rubric '
            .'reaches every Critical by calling everything Critical, and the sheriff stops '
            .'reading the section. Both, or neither.',
            $r->recall(Severity::Critical) * 100,
            $r->precision(Severity::Critical) * 100
        );
        $lines[] = '';
        $lines[] = 'Weigh the matrix rather than the headline percentage. The corpus is heavily '
            .'imbalanced, so answering "Minor" to everything would score well and say nothing. '
            .'And these labels are years of decisions by many different people: this measures '
            .'agreement with past practice, not correctness.';
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }
}
