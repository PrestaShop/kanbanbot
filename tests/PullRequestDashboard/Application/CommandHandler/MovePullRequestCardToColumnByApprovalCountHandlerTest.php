<?php

declare(strict_types=1);

namespace App\Tests\PullRequestDashboard\Application\CommandHandler;

use App\PullRequestDashboard\Application\Command\MovePullRequestCardToColumnByApprovalCountCommand;
use App\PullRequestDashboard\Application\CommandHandler\MovePullRequestCardToColumnByApprovalCountCommandHandler;
use App\PullRequestDashboard\Domain\Aggregate\PullRequest;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCardId;
use App\PullRequestDashboard\Domain\Exception\PullRequestCardNotFoundException;
use App\PullRequestDashboard\Infrastructure\Adapter\InMemoryPullRequestPullRequestCardRepository;
use PHPUnit\Framework\TestCase;

class MovePullRequestCardToColumnByApprovalCountHandlerTest extends TestCase
{
    private MovePullRequestCardToColumnByApprovalCountCommandHandler $movePullRequestCardToColumnByApprovalCountHandler;
    private InMemoryPullRequestPullRequestCardRepository $pullRequestCardRepository;

    public static function provideTestHandle(): array
    {
        return [
            [
                new PullRequestCardId(
                    projectNumber: '17',
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                1,
                'Need 2nd approval'
            ],
            [
                new PullRequestCardId(
                    projectNumber: '17',
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'OtherThanPrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                1,
                'Waiting for author'
            ],
        ];
    }

    protected function setUp(): void
    {
        $this->pullRequestCardRepository = new InMemoryPullRequestPullRequestCardRepository();
        $this->movePullRequestCardToColumnByApprovalCountHandler = new MovePullRequestCardToColumnByApprovalCountCommandHandler($this->pullRequestCardRepository);
    }

    /**
     * @dataProvider provideTestHandle
     */
    public function testHandle(PullRequestCardId $pullRequestCardId, int $approvalCount, string $expectedColumnName): void
    {
        $this->pullRequestCardRepository->feed([
            PullRequestCard::create(
                id: $pullRequestCardId,
                columnName: 'Waiting for author',
                pullRequest: new PullRequest(approvalCount: $approvalCount)
            ),
        ]);

        $this->movePullRequestCardToColumnByApprovalCountHandler->__invoke(new MovePullRequestCardToColumnByApprovalCountCommand(
            projectNumber: $pullRequestCardId->projectNumber,
            repositoryOwner: $pullRequestCardId->repositoryOwner,
            repositoryName: $pullRequestCardId->repositoryName,
            pullRequestNumber: $pullRequestCardId->pullRequestNumber,
        ));
        /** @var PullRequestCard $pullRequestCard */
        $pullRequestCard = $this->pullRequestCardRepository->find($pullRequestCardId);
        // Todo : add enum instead
        $this->assertSame($expectedColumnName, $pullRequestCard->getColumnName());
    }

    public function testPullRequestCardNotFound(): void
    {
        $this->expectException(PullRequestCardNotFoundException::class);
        $this->movePullRequestCardToColumnByApprovalCountHandler->__invoke(new MovePullRequestCardToColumnByApprovalCountCommand(
            projectNumber: 'fake',
            repositoryOwner: 'fake',
            repositoryName: 'fake',
            pullRequestNumber: 'fake'
        ));
    }


}