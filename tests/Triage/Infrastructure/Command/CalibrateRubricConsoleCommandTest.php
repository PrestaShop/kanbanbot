<?php

declare(strict_types=1);

namespace App\Tests\Triage\Infrastructure\Command;

use App\Triage\Application\CommandHandler\CalibrateRubricCommandHandler;
use App\Triage\Domain\Aggregate\Issue\Severity;
use App\Triage\Infrastructure\Adapter\InMemoryIssueSearch;
use App\Triage\Infrastructure\Adapter\InMemorySeverityClassifier;
use App\Triage\Infrastructure\Command\CalibrateRubricConsoleCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CalibrateRubricConsoleCommandTest extends TestCase
{
    private const TITLE = 'Checkout [breaks] | *always*';

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/triage-report-'.uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testEachDisagreementIsListedWithALinkAndTheReasoning(): void
    {
        // Critical proposed as Minor, Minor proposed as Major, the other two
        // levels agreed. One Critical and one Minor are held out either way.
        $report = $this->report([
            Severity::Critical->value => Severity::Minor,
            Severity::Major->value => Severity::Major,
            Severity::Minor->value => Severity::Major,
            Severity::Trivial->value => Severity::Trivial,
        ]);

        $this->assertStringContainsString('## Where the rubric disagreed', $report);
        $this->assertStringContainsString('2 of the 4 issues', $report);
        $this->assertStringContainsString('https://github.com/x/y/issues/', $report);
        $this->assertStringContainsString('Maintainers: **Critical**, rubric: **Minor**, two levels lower', $report);
        $this->assertStringContainsString('Maintainers: **Minor**, rubric: **Major**, one level higher', $report);
        $this->assertStringContainsString('> staged verdict', $report);
        $this->assertLessThan(
            strpos($report, '### Rated higher than maintainers did (1)'),
            strpos($report, '### Rated lower than maintainers did (1)'),
            'an underrated issue can hide a real problem, so those come first'
        );
    }

    public function testTheSummarySaysWhichWayTheMissesLean(): void
    {
        $report = $this->report([
            Severity::Critical->value => Severity::Minor,
            Severity::Major->value => Severity::Major,
            Severity::Minor->value => Severity::Major,
            Severity::Trivial->value => Severity::Trivial,
        ]);

        $this->assertStringContainsString('**Rated lower than maintainers did: 1/4**', $report);
        $this->assertStringContainsString('**Rated higher than maintainers did: 1/4**', $report);
    }

    public function testAnIssueTitleCannotInjectMarkdown(): void
    {
        // Titles are written by anyone who opens an issue, and the report is
        // published as a page maintainers read.
        $report = $this->report([
            Severity::Critical->value => Severity::Minor,
            Severity::Major->value => Severity::Major,
            Severity::Minor->value => Severity::Minor,
            Severity::Trivial->value => Severity::Trivial,
        ]);

        $this->assertStringContainsString('Checkout \[breaks\] \| \*always\*', $report);
        $this->assertStringNotContainsString(self::TITLE, $report);
    }

    public function testTheCriticalRatesAreSpelledOutAsCounts(): void
    {
        $report = $this->report([
            Severity::Critical->value => Severity::Minor,
            Severity::Major->value => Severity::Major,
            Severity::Minor->value => Severity::Minor,
            Severity::Trivial->value => Severity::Trivial,
        ]);

        $this->assertStringContainsString('**Critical issues found: 0 of 1.**', $report);
        $this->assertStringContainsString('**The rubric proposed no Critical in this run.**', $report);
    }

    public function testAFullAgreementSaysSoInsteadOfAnEmptyList(): void
    {
        $report = $this->report([
            Severity::Critical->value => Severity::Critical,
            Severity::Major->value => Severity::Major,
            Severity::Minor->value => Severity::Minor,
            Severity::Trivial->value => Severity::Trivial,
        ]);

        $this->assertStringContainsString('Nowhere: the rubric matched every label.', $report);
        $this->assertStringContainsString('None was missed.', $report);
    }

    /**
     * Two issues per level, so one of each is held out, each answered with
     * the verdict staged for its label.
     *
     * @param array<string, Severity> $verdictByLabel
     */
    private function report(array $verdictByLabel): string
    {
        $corpus = [];
        $verdicts = [];
        $number = 1;
        foreach (Severity::cases() as $level) {
            for ($i = 0; $i < 2; ++$i) {
                $corpus[] = [
                    'number' => $number,
                    'title' => Severity::Critical === $level ? self::TITLE : $level->value.' issue',
                    'body' => 'body',
                    'truth' => $level->value,
                ];
                $verdicts[$number] = $verdictByLabel[$level->value];
                ++$number;
            }
        }

        $path = $this->directory.'/report.md';
        $command = new CalibrateRubricConsoleCommand(
            new CalibrateRubricCommandHandler(new InMemoryIssueSearch($corpus), new InMemorySeverityClassifier($verdicts)),
            $path,
        );

        $tester = new CommandTester($command);
        $tester->execute(['--repository' => 'x/y']);
        $tester->assertCommandIsSuccessful();

        return (string) file_get_contents($path);
    }
}
