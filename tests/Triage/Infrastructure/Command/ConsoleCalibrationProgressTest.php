<?php

declare(strict_types=1);

namespace App\Tests\Triage\Infrastructure\Command;

use App\Triage\Domain\Aggregate\Issue\Severity;
use App\Triage\Infrastructure\Command\ConsoleCalibrationProgress;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConsoleCalibrationProgressTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/triage-checkpoint-'.uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testEachVerdictIsOnDiskBeforeTheRunEnds(): void
    {
        // The point of the checkpoint: a run that dies at item 300 of 334 has
        // already paid for those 300.
        $path = $this->directory.'/run.jsonl';
        $progress = $this->progress($path);

        $progress->start(3);
        $progress->scored(11, 'Critical', Severity::Major);
        $progress->failed(12, 'API rejected the request');

        $lines = array_filter(explode("\n", (string) file_get_contents($path)));

        $this->assertCount(2, $lines, 'written as they arrive, not flushed at the end');
        $this->assertSame(
            ['number' => 11, 'truth' => 'Critical', 'proposed' => 'Major'],
            json_decode($lines[0], true)
        );
        $this->assertSame(
            ['number' => 12, 'failed' => 'API rejected the request'],
            json_decode($lines[1], true)
        );
    }

    public function testAnUnwritableCheckpointDoesNotTakeTheRunDown(): void
    {
        // The run is the expensive part. Losing the checkpoint is a nuisance;
        // losing the run over the checkpoint would not be.
        $progress = $this->progress($this->directory.'/no/such/directory/run.jsonl');

        $progress->start(1);
        $progress->scored(1, 'Minor', Severity::Minor);
        $progress->finish();

        $this->expectNotToPerformAssertions();
    }

    public function testItReportsProgressOnTheTerminal(): void
    {
        $output = new BufferedOutput();
        $progress = new ConsoleCalibrationProgress(new SymfonyStyle(new ArrayInput([]), $output));

        $progress->start(2);
        $progress->scored(1, 'Minor', Severity::Minor);
        $progress->finish();

        $this->assertNotSame('', $output->fetch(), 'a silent hour looks the same as a hung run');
    }

    private function progress(string $checkpointPath): ConsoleCalibrationProgress
    {
        return new ConsoleCalibrationProgress(
            new SymfonyStyle(new ArrayInput([]), new BufferedOutput()),
            $checkpointPath
        );
    }
}
