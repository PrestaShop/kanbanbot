<?php

declare(strict_types=1);

namespace App\Tests\Triage\Application\CommandHandler;

use App\Triage\Application\Command\CalibrateRubricCommand;
use App\Triage\Application\CommandHandler\CalibrateRubricCommandHandler;
use App\Triage\Domain\Aggregate\Issue\CalibrationResult;
use App\Triage\Domain\Aggregate\Issue\Confidence;
use App\Triage\Domain\Aggregate\Issue\IssueToClassify;
use App\Triage\Domain\Aggregate\Issue\Severity;
use App\Triage\Domain\Aggregate\Issue\TriagedIssue;
use App\Triage\Domain\Exception\NothingScoredException;
use App\Triage\Domain\Gateway\SeverityClassifierInterface;
use App\Triage\Infrastructure\Adapter\InMemoryIssueSearch;
use App\Triage\Infrastructure\Adapter\InMemorySeverityClassifier;
use PHPUnit\Framework\TestCase;

class CalibrateRubricCommandHandlerTest extends TestCase
{
    /**
     * @return array<int, array{number: int, title: string, body: string, truth: string}>
     */
    private static function corpus(int $perClass): array
    {
        $corpus = [];
        $number = 1;
        foreach (Severity::cases() as $level) {
            for ($i = 0; $i < $perClass; ++$i) {
                $corpus[] = [
                    'number' => $number++,
                    'title' => $level->value.' issue '.$i,
                    'body' => 'body',
                    'truth' => $level->value,
                ];
            }
        }

        return $corpus;
    }

    public function testItScoresEveryHeldOutIssueAgainstItsLabel(): void
    {
        $corpus = self::corpus(4);
        // A classifier that is always right: the matrix must be diagonal.
        $verdicts = [];
        foreach ($corpus as $issue) {
            $verdicts[$issue['number']] = Severity::from($issue['truth']);
        }

        $result = $this->handle($corpus, $verdicts);

        $this->assertSame(8, $result->scored, 'half of each class is held out');
        $this->assertSame(8, $result->exactAgreement());
        $this->assertSame(0, $result->offByOne());
        foreach (Severity::cases() as $level) {
            $this->assertSame(1.0, $result->recall($level));
            $this->assertSame(1.0, $result->precision($level));
        }
    }

    public function testRecallAndPrecisionMoveIndependently(): void
    {
        $corpus = self::corpus(4);
        // Everything called Critical: perfect recall on Critical, and the
        // precision that shows why recall alone is not enough.
        $verdicts = [];
        foreach ($corpus as $issue) {
            $verdicts[$issue['number']] = Severity::Critical;
        }

        $result = $this->handle($corpus, $verdicts);

        $this->assertSame(1.0, $result->recall(Severity::Critical));
        $this->assertSame(0.25, $result->precision(Severity::Critical));
        $this->assertSame(8, $result->proposedCount(Severity::Critical));
    }

    public function testEveryDisagreementIsKeptWithItsReasoning(): void
    {
        // The matrix says how often the rubric disagrees. Only the list says
        // which issues, so only the list can be investigated.
        $corpus = self::corpus(4);
        $verdicts = [];
        foreach ($corpus as $issue) {
            $verdicts[$issue['number']] = Severity::Critical;
        }

        $result = $this->handle($corpus, $verdicts);

        $this->assertCount(6, $result->disagreements, 'every held-out non-Critical, and no agreement');
        $this->assertSame(6, $result->overestimated(), 'calling everything Critical only ever rates too high');
        $this->assertSame(0, $result->underestimated());
        foreach ($result->disagreements as $disagreement) {
            $this->assertNotSame(Severity::Critical, $disagreement->truth);
            $this->assertSame(Severity::Critical, $disagreement->proposed);
            $this->assertSame('staged verdict', $disagreement->rationale);
        }
    }

    public function testUnderAndOverEstimatesAreCountedApart(): void
    {
        // Everything called Trivial: the mirror image, every miss too low.
        $corpus = self::corpus(4);
        $verdicts = [];
        foreach ($corpus as $issue) {
            $verdicts[$issue['number']] = Severity::Trivial;
        }

        $result = $this->handle($corpus, $verdicts);

        $this->assertSame(6, $result->underestimated());
        $this->assertSame(0, $result->overestimated());
        foreach ($result->disagreements as $disagreement) {
            $this->assertTrue($disagreement->isUnderestimate());
        }
    }

    public function testOffByOneIsCountedSeparatelyFromWorseMisses(): void
    {
        $corpus = self::corpus(2);
        $verdicts = [];
        foreach ($corpus as $issue) {
            // Critical -> Major is one level; Trivial -> Critical is three.
            $verdicts[$issue['number']] = match ($issue['truth']) {
                Severity::Critical->value => Severity::Major,
                Severity::Trivial->value => Severity::Critical,
                default => Severity::from($issue['truth']),
            };
        }

        $result = $this->handle($corpus, $verdicts);

        $this->assertSame(2, $result->exactAgreement(), 'Major and Minor are still right');
        $this->assertSame(1, $result->offByOne(), 'the Critical near miss');
    }

    public function testAHeldOutPrefixStaysBalancedAcrossClasses(): void
    {
        $corpus = self::corpus(20);
        $verdicts = [];
        foreach ($corpus as $issue) {
            $verdicts[$issue['number']] = Severity::from($issue['truth']);
        }

        // A limited run must sample every class, or the matrix has one row.
        $result = $this->handle($corpus, $verdicts, limit: 8);

        foreach (Severity::cases() as $level) {
            $this->assertSame(
                2,
                array_sum($result->matrix[$level->value]),
                $level->value.' must appear in a limited run'
            );
        }
    }

    public function testSplitIsDeterministic(): void
    {
        $corpus = self::corpus(10);
        $handler = new CalibrateRubricCommandHandler(new InMemoryIssueSearch(), new InMemorySeverityClassifier());

        [, $first] = $handler->split($corpus);
        [, $second] = $handler->split($corpus);

        $this->assertSame(
            array_column($first, 'number'),
            array_column($second, 'number'),
            'two runs of the same corpus must compare like with like'
        );
    }

    public function testFailuresAreCountedWithoutStoppingTheRun(): void
    {
        $corpus = self::corpus(4);
        $verdicts = [];
        foreach ($corpus as $issue) {
            $verdicts[$issue['number']] = Severity::from($issue['truth']);
        }
        $handler = new CalibrateRubricCommandHandler(
            new InMemoryIssueSearch($corpus),
            new InMemorySeverityClassifier($verdicts, [1 => 'API rejected the request']),
        );

        $result = $handler(new CalibrateRubricCommand(repository: 'x/y'));

        $this->assertSame(1, $result->failures());
        $this->assertSame(7, $result->scored);
        $this->assertSame(
            ['API rejected the request' => 1],
            $result->failureReasons,
            'the reason is what makes a failure count actionable'
        );
    }

    public function testIdenticalFailuresAreGroupedUnderOneReason(): void
    {
        $corpus = self::corpus(4);
        $verdicts = [];
        foreach ($corpus as $issue) {
            $verdicts[$issue['number']] = Severity::from($issue['truth']);
        }

        // Failures have to land on issues that are actually scored, so they
        // are taken from the held-out half rather than guessed at.
        [, $heldOut] = (new CalibrateRubricCommandHandler(new InMemoryIssueSearch(), new InMemorySeverityClassifier()))
            ->split($corpus);
        $failures = [
            $heldOut[0]['number'] => 'maxItems is not supported',
            $heldOut[1]['number'] => 'maxItems is not supported',
            $heldOut[2]['number'] => 'a different problem',
        ];

        $handler = new CalibrateRubricCommandHandler(
            new InMemoryIssueSearch($corpus),
            new InMemorySeverityClassifier($verdicts, $failures),
        );

        $result = $handler(new CalibrateRubricCommand(repository: 'x/y'));

        $this->assertSame(
            ['maxItems is not supported' => 2, 'a different problem' => 1],
            $result->failureReasons
        );
        $this->assertSame(3, $result->failures());
    }

    public function testTheHeldOutIssuesAreSentWithoutTheirLabels(): void
    {
        // The single most dangerous regression this context can suffer, and
        // until now it was guarded by a comment. Passing the labels the
        // maintainers applied would put the answer in the question: the run
        // would report near-perfect agreement and be measuring nothing.
        $seen = [];
        $classifier = new class($seen) implements SeverityClassifierInterface {
            /**
             * @param array<int, IssueToClassify> $seen
             */
            public function __construct(private array &$seen)
            {
            }

            /**
             * @return array<int, IssueToClassify>
             */
            public function seen(): array
            {
                return $this->seen;
            }

            public function classify(IssueToClassify $issue, array $duplicateCandidates = []): TriagedIssue
            {
                $this->seen[] = $issue;

                return new TriagedIssue(
                    number: $issue->number,
                    title: $issue->title,
                    severity: Severity::Minor,
                    confidence: Confidence::High,
                    rationale: 'recorded',
                );
            }

            public function estimatedCost(): float
            {
                return 0.0;
            }

            public function cachedInputShare(): float
            {
                return 0.0;
            }
        };

        (new CalibrateRubricCommandHandler(new InMemoryIssueSearch(self::corpus(4)), $classifier))(
            new CalibrateRubricCommand(repository: 'x/y')
        );

        $this->assertNotEmpty($seen);
        foreach ($seen as $issue) {
            $this->assertSame([], $issue->labels, 'a severity label here would leak the ground truth');
        }
        // The type is the other half of the guarantee: there is nowhere on it
        // to put the label even by accident.
        $this->assertSame(
            ['number', 'title', 'body', 'labels'],
            array_keys(get_object_vars($seen[0]))
        );
    }

    public function testARunThatScoresNothingIsAFailureNotAnEmptyReport(): void
    {
        $corpus = self::corpus(4);
        $failures = [];
        foreach ($corpus as $issue) {
            $failures[$issue['number']] = 'API rejected the request';
        }
        $handler = new CalibrateRubricCommandHandler(
            new InMemoryIssueSearch($corpus),
            new InMemorySeverityClassifier([], $failures),
        );

        $this->expectException(NothingScoredException::class);
        $handler(new CalibrateRubricCommand(repository: 'x/y'));
    }

    /**
     * @param array<int, array{number: int, title: string, body: string, truth: string}> $corpus
     * @param array<int, Severity>                                                       $verdicts
     */
    private function handle(array $corpus, array $verdicts, int $limit = 0): CalibrationResult
    {
        $handler = new CalibrateRubricCommandHandler(
            new InMemoryIssueSearch($corpus),
            new InMemorySeverityClassifier($verdicts),
        );

        return $handler(new CalibrateRubricCommand(repository: 'x/y', limit: $limit));
    }

    public function testAnIntervalWidensAsTheCountShrinks(): void
    {
        // The point of printing it: 3 of 4 and 300 of 400 are both 75%, and
        // only one of them says anything.
        $result = new CalibrationResult(matrix: [], scored: 0, failureReasons: [], estimatedCost: 0.0);

        [$narrowLow, $narrowHigh] = $result->interval(300, 400);
        [$wideLow, $wideHigh] = $result->interval(3, 4);

        $this->assertGreaterThan($narrowHigh - $narrowLow, $wideHigh - $wideLow);
        $this->assertLessThan(0.75, $narrowLow);
        $this->assertGreaterThan(0.75, $narrowHigh);
    }

    public function testAnIntervalOnNoObservationsIsEmptyRatherThanDividingByZero(): void
    {
        $result = new CalibrationResult(matrix: [], scored: 0, failureReasons: [], estimatedCost: 0.0);

        $this->assertSame([0.0, 0.0], $result->interval(0, 0));
    }

    public function testTheIntervalBracketsTheRateItDescribes(): void
    {
        $corpus = self::corpus(4);
        $verdicts = [];
        foreach ($corpus as $issue) {
            $verdicts[$issue['number']] = Severity::from($issue['truth']);
        }

        $result = $this->handle($corpus, $verdicts);

        foreach (Severity::cases() as $level) {
            [$low, $high] = $result->precisionInterval($level);
            $this->assertGreaterThanOrEqual($low, $result->precision($level));
            $this->assertLessThanOrEqual($high, $result->precision($level));
            $this->assertGreaterThanOrEqual(0.0, $low);
            $this->assertLessThanOrEqual(1.0, $high);
        }
    }
}
