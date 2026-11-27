<?php

declare(strict_types=1);

namespace App\Tests\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\RequestChangesCommand;
use App\PullRequest\Application\CommandHandler\RequestChangesCommandHandler;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequest;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Exception\PullRequestNotFoundException;
use App\PullRequest\Infrastructure\Adapter\InMemoryPullRequestRepository;
use PHPUnit\Framework\TestCase;

class RequestChangesCommandHandlerTest extends TestCase
{
    private RequestChangesCommandHandler $requestChangesCommandHandler;
    private InMemoryPullRequestRepository $prRepository;

    protected function setUp(): void
    {
        $this->prRepository = new InMemoryPullRequestRepository();
        $this->requestChangesCommandHandler = new RequestChangesCommandHandler($this->prRepository);
    }

    /**
     * @dataProvider handleDataProvider
     *
     * @param string[] $originalLabels
     */
    public function testHandle(PullRequestId $pullRequestId, array $originalLabels): void
    {
        $this->prRepository->feed([
            PullRequest::create(
                id: $pullRequestId,
                labels: $originalLabels,
                approvals: [],
                targetBranch: 'main'
            ),
        ]);

        $this->requestChangesCommandHandler->__invoke(new RequestChangesCommand(
            repositoryOwner: $pullRequestId->repositoryOwner,
            repositoryName: $pullRequestId->repositoryName,
            pullRequestNumber: $pullRequestId->pullRequestNumber,
        ));
        /** @var PullRequest $pr */
        $pr = $this->prRepository->find($pullRequestId);

        $this->assertCount(
            1,
            array_filter(
                $pr->getLabels(),
                static fn (string $label) => 'Waiting for author' === $label
            )
        );
    }

    /**
     * @return array<array{0: PullRequestId, 1: string[]}>
     */
    public static function handleDataProvider(): array
    {
        return [
            [
                new PullRequestId(
                    repositoryOwner: 'repositoryOwner',
                    repositoryName: 'repositoryName',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'repositoryOwner',
                    repositoryName: 'repositoryName',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                ['Waiting for author'],
            ],
        ];
    }

    public function testPullRequestNotFound(): void
    {
        $this->expectException(PullRequestNotFoundException::class);
        $this->requestChangesCommandHandler->__invoke(new RequestChangesCommand(
            repositoryOwner: 'fake',
            repositoryName: 'fake',
            pullRequestNumber: 'fake'
        ));
    }
}
