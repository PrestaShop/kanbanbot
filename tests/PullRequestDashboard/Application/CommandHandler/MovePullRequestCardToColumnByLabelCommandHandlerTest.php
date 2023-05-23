<?php

declare(strict_types=1);

namespace App\Tests\PullRequestDashboard\Application\CommandHandler;

use App\PullRequestDashboard\Application\Command\MovePullRequestCardToColumnByLabelCommand;
use App\PullRequestDashboard\Application\CommandHandler\MovePullRequestCardToColumnByLabelCommandHandler;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\Approval;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequest;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCard;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCardId;
use App\PullRequestDashboard\Domain\Exception\PullRequestCardNotFoundException;
use App\PullRequestDashboard\Infrastructure\Adapter\InMemoryPullRequestPullRequestCardRepository;
use PHPUnit\Framework\TestCase;

class MovePullRequestCardToColumnByLabelCommandHandlerTest extends TestCase
{
    private MovePullRequestCardToColumnByLabelCommandHandler $movePullRequestCardToColumnByLabelHandler;
    private InMemoryPullRequestPullRequestCardRepository $pullRequestCardRepository;

    /**
     * @return string[][]
     */
    public static function handleDataProvider(): array
    {
        return [
            ['Whatever column name', 'Waiting for author', 'Waiting for author',],
            ['Whatever column name', 'Waiting for PM', 'Waiting for PM/UX/Dev',],
            ['Whatever column name', 'Waiting for UX', 'Waiting for PM/UX/Dev',],
            ['Whatever column name', 'Waiting for dev', 'Waiting for PM/UX/Dev',],
            ['Whatever column name', 'Waiting for QA', 'To be tested',],
            ['Whatever column name', 'Unknown label', 'Whatever column name',],
        ];
    }

    protected function setUp(): void
    {
        $this->pullRequestCardRepository = new InMemoryPullRequestPullRequestCardRepository();
        $this->movePullRequestCardToColumnByLabelHandler = new MovePullRequestCardToColumnByLabelCommandHandler($this->pullRequestCardRepository);
    }

    /**
     * @dataProvider handleDataProvider
     */
    public function testHandle(string $originalColumn, string $label, string $expectedColumn): void
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
                columnName: $originalColumn,
                pullRequest: new PullRequest(approvals: [new Approval('lartist')])
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