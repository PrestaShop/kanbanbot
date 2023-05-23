<?php

declare(strict_types=1);

namespace App\Tests\PullRequestDashboard\Application\CommandHandler;

use App\PullRequestDashboard\Application\Command\MovePullRequestCardToColumnByLabelCommand;
use App\PullRequestDashboard\Application\CommandHandler\MovePullRequestCardToColumnByLabelCommandHandler;
use App\PullRequestDashboard\Domain\Aggregate\PullRequest;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCardId;
use App\PullRequestDashboard\Domain\Exception\PullRequestCardNotFoundException;
use App\PullRequestDashboard\Infrastructure\Adapter\InMemoryPullRequestPullRequestCardRepository;
use PHPUnit\Framework\TestCase;

class MovePullRequestCardToColumnByLabelHandlerTest extends TestCase
{
    private MovePullRequestCardToColumnByLabelCommandHandler $movePullRequestCardToColumnByLabelHandler;
    private InMemoryPullRequestPullRequestCardRepository $pullRequestCardRepository;

    public static function testHandleDataProvider(): array
    {
        return [
            ['Waiting for author', 'Waiting for author',],
            ['Waiting for PM', 'Waiting for PM/UX/Dev',],
            ['Waiting for UX', 'Waiting for PM/UX/Dev',],
            ['Waiting for dev', 'Waiting for PM/UX/Dev',],
        ];
    }

    protected function setUp(): void
    {
        $this->pullRequestCardRepository = new InMemoryPullRequestPullRequestCardRepository();
        $this->movePullRequestCardToColumnByLabelHandler = new MovePullRequestCardToColumnByLabelCommandHandler($this->pullRequestCardRepository);
    }

    /**
     * @dataProvider testHandleDataProvider
     */
    public function testHandle(string $label, string $expectedColumn): void
    {
        $pullRequestCardId = new PullRequestCardId(
            projectNumber: '17',
            repositoryOwner: 'repositoryOwner',
            repositoryName: 'repositoryName',
            pullRequestNumber: 'pullRequestNumber',
        );
        $this->pullRequestCardRepository->feed([
            PullRequestCard::create(
                id: $pullRequestCardId,
                columnName: 'Whatever column name',
                pullRequest: new PullRequest(approvalCount: 0)
            ),
        ]);

        $this->movePullRequestCardToColumnByLabelHandler->__invoke(new MovePullRequestCardToColumnByLabelCommand(
            projectNumber: $pullRequestCardId->projectNumber,
            repositoryOwner: $pullRequestCardId->repositoryOwner,
            repositoryName: $pullRequestCardId->repositoryName,
            pullRequestNumber: $pullRequestCardId->pullRequestNumber,
            label: $label
        ));
        /** @var PullRequestCard $pullRequestCard */
        $pullRequestCard = $this->pullRequestCardRepository->find($pullRequestCardId);
        // todo : add enum instead
        $this->assertSame($expectedColumn, $pullRequestCard->getColumnName());
    }

    public function testPullRequestCardNotFound(): void
    {
        $this->expectException(PullRequestCardNotFoundException::class);
        $this->movePullRequestCardToColumnByLabelHandler->__invoke(new MovePullRequestCardToColumnByLabelCommand(
            projectNumber: 'fake',
            repositoryOwner: 'fake',
            repositoryName: 'fake',
            pullRequestNumber: 'fake',
            label: 'fake'
        ));
    }
}