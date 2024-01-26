<?php

declare(strict_types=1);

namespace App\Tests\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\CheckForNewTranslationsCommand;
use App\PullRequest\Application\CommandHandler\CheckForNewTranslationsCommandHandler;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequest;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Exception\PullRequestNotFoundException;
use App\PullRequest\Infrastructure\Adapter\InMemoryPullRequestRepository;
use App\PullRequestDashboard\Infrastructure\Adapter\InMemoryPullRequestPullRequestCardRepository;
use PHPUnit\Framework\TestCase;

class CheckForNewTranslationsCommandHandlerTest extends TestCase
{
    private CheckForNewTranslationsCommandHandler $checkForNewTranslationsCommandHandler;
    private InMemoryPullRequestRepository $prRepository;
    private InMemoryPullRequestPullRequestCardRepository $pullRequestCardRepository;

    protected function setUp(): void
    {
        $this->prRepository = new InMemoryPullRequestRepository();
        $this->checkForNewTranslationsCommandHandler = new CheckForNewTranslationsCommandHandler($this->prRepository, $this->pullRequestCardRepository);
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
                approvals: []
            ),
        ]);

        $this->checkForNewTranslationsCommandHandler->__invoke(new CheckForNewTranslationsCommand(
            repositoryOwner: $pullRequestId->repositoryOwner,
            repositoryName: $pullRequestId->repositoryName,
            pullRequestNumber: $pullRequestId->pullRequestNumber,
        ));
        /** @var PullRequest $pr */
        $pr = $this->prRepository->find($pullRequestId);

        /*$this->assertCount(
            1,
            array_filter(
                $pr->getLabels(),
                static fn (string $label) => 'Waiting for author' === $label
            )
        );*/
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
        $this->checkForNewTranslationsCommandHandler->__invoke(new CheckForNewTranslationsCommand(
            repositoryOwner: 'fake',
            repositoryName: 'fake',
            pullRequestNumber: 'fake'
        ));
    }
}
