<?php

declare(strict_types=1);

namespace App\Tests\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\RemoveLabelCommand;
use App\PullRequest\Application\CommandHandler\RemoveLabelCommandHandler;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequest;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Exception\PullRequestNotFoundException;
use App\PullRequest\Infrastructure\Adapter\InMemoryPullRequestRepository;
use PHPUnit\Framework\TestCase;

class RemoveLabelCommandHandlerTest extends TestCase
{
    private RemoveLabelCommandHandler $handler;
    private InMemoryPullRequestRepository $prRepository;

    protected function setUp(): void
    {
        $this->prRepository = new InMemoryPullRequestRepository();
        $this->handler = new RemoveLabelCommandHandler($this->prRepository);
    }

    /**
     * @dataProvider handleDataProvider
     *
     * @param string[] $originalLabels
     * @param string[] $expectedLabels
     */
    public function testHandle(array $originalLabels, string $label, array $expectedLabels): void
    {
        $pullRequestId = new PullRequestId('owner', 'repo', '1');
        $this->prRepository->feed([
            PullRequest::create(id: $pullRequestId, labels: $originalLabels, approvals: [], targetBranch: 'develop'),
        ]);

        $this->handler->__invoke(new RemoveLabelCommand('owner', 'repo', '1', $label));
        /** @var PullRequest $pr */
        $pr = $this->prRepository->find($pullRequestId);

        $this->assertSame($expectedLabels, $pr->getLabels());
    }

    /**
     * @return array<string, array{0: string[], 1: string, 2: string[]}>
     */
    public static function handleDataProvider(): array
    {
        return [
            'Push after a fix' => [['Bug fix', 'Waiting for author', 'develop'], 'Waiting for author', ['Bug fix', 'develop']],
            'QA validated' => [['Waiting for QA', 'QA ✔️'], 'Waiting for QA', ['QA ✔️']],
            'Label absent' => [['Bug fix'], 'Waiting for author', ['Bug fix']],
        ];
    }

    public function testPullRequestNotFound(): void
    {
        $this->expectException(PullRequestNotFoundException::class);
        $this->handler->__invoke(new RemoveLabelCommand('fake', 'fake', 'fake', 'Waiting for author'));
    }
}
