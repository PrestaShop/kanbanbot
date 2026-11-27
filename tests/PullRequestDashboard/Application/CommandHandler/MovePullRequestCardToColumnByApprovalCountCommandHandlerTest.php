<?php

declare(strict_types=1);

namespace App\Tests\PullRequestDashboard\Application\CommandHandler;

use App\PullRequestDashboard\Application\Command\MovePullRequestCardToColumnByApprovalCountCommand;
use App\PullRequestDashboard\Application\CommandHandler\MovePullRequestCardToColumnByApprovalCountCommandHandler;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\Approval;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequest;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCard;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCardId;
use App\PullRequestDashboard\Domain\Exception\PullRequestCardNotFoundException;
use App\PullRequestDashboard\Infrastructure\Adapter\InMemoryPullRequestPullRequestCardRepository;
use App\Shared\Infrastructure\Adapter\InMemoryCommitterRepository;
use PHPUnit\Framework\TestCase;

class MovePullRequestCardToColumnByApprovalCountCommandHandlerTest extends TestCase
{
    private MovePullRequestCardToColumnByApprovalCountCommandHandler $movePullRequestCardToColumnByApprovalCountHandler;
    private InMemoryPullRequestPullRequestCardRepository $pullRequestCardRepository;
    private InMemoryCommitterRepository $committerRepository;

    /**
     * @return array<array{0: string, 1: PullRequestCardId, 2: Approval[], 3: string}>
     */
    public static function provideTestHandle(): array
    {
        return [
            [
                'Waiting for author',
                new PullRequestCardId(
                    projectNumber: '17',
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [new Approval('lartist')],
                'Need 2nd approval',
            ],
            [
                'Waiting for author',
                new PullRequestCardId(
                    projectNumber: '17',
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [new Approval('nicosomb')],
                'Need 2nd approval',
            ],
            [
                'Waiting for author',
                new PullRequestCardId(
                    projectNumber: '17',
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'OtherThanPrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [new Approval('lartist')],
                'Waiting for author',
            ],
            [
                'Waiting for author',
                new PullRequestCardId(
                    projectNumber: '17',
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [new Approval('no_commiter')],
                'Waiting for author',
            ],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: Approval[], 3: string}>
     */
    public static function provideTestHandleApprovalsDropped(): array
    {
        return [
            'Core, 2 -> 1 approval' => ['To be tested', 'PrestaShop', [new Approval('lartist')], 'Need 2nd approval'],
            'Core, 2 -> 0 approval' => ['To be tested', 'PrestaShop', [], 'Ready for review'],
            'Core, 1 -> 0 approval' => ['Need 2nd approval', 'PrestaShop', [], 'Ready for review'],
            'Core, still 2 approvals' => ['To be tested', 'PrestaShop', [new Approval('lartist'), new Approval('nicosomb')], 'To be tested'],
            'Module, 1 -> 0 approval' => ['To be tested', 'OtherThanPrestaShop', [], 'Ready for review'],
            'Module, still 1 approval' => ['To be tested', 'OtherThanPrestaShop', [new Approval('lartist')], 'To be tested'],
            'Other column untouched' => ['Waiting for PM/UX/Dev', 'PrestaShop', [new Approval('lartist')], 'Waiting for PM/UX/Dev'],
            'Waiting for author untouched' => ['Waiting for author', 'PrestaShop', [], 'Waiting for author'],
        ];
    }

    /**
     * @dataProvider provideTestHandleApprovalsDropped
     *
     * @param Approval[] $approvals
     */
    public function testHandleApprovalsDropped(string $originalColumn, string $repositoryName, array $approvals, string $expectedColumnName): void
    {
        $pullRequestCardId = new PullRequestCardId('17', 'PrestaShop', $repositoryName, '1');
        $this->pullRequestCardRepository->feed([
            PullRequestCard::create(id: $pullRequestCardId, columnName: $originalColumn, pullRequest: new PullRequest(approvals: $approvals)),
        ]);

        $this->movePullRequestCardToColumnByApprovalCountHandler->__invoke(new MovePullRequestCardToColumnByApprovalCountCommand(
            projectNumber: '17',
            repositoryOwner: 'PrestaShop',
            repositoryName: $repositoryName,
            pullRequestNumber: '1',
            approvalsDropped: true,
        ));
        /** @var PullRequestCard $pullRequestCard */
        $pullRequestCard = $this->pullRequestCardRepository->find($pullRequestCardId);

        $this->assertSame($expectedColumnName, $pullRequestCard->getColumnName());
    }

    protected function setUp(): void
    {
        $this->pullRequestCardRepository = new InMemoryPullRequestPullRequestCardRepository();
        $this->committerRepository = new InMemoryCommitterRepository();
        $this->movePullRequestCardToColumnByApprovalCountHandler = new MovePullRequestCardToColumnByApprovalCountCommandHandler(
            $this->pullRequestCardRepository,
            $this->committerRepository,
        );
    }

    /**
     * @dataProvider provideTestHandle
     *
     * @param Approval[] $approvals
     */
    public function testHandle(string $originalColumn, PullRequestCardId $pullRequestCardId, array $approvals, string $expectedColumnName): void
    {
        $this->pullRequestCardRepository->feed([
            PullRequestCard::create(
                id: $pullRequestCardId,
                columnName: $originalColumn,
                pullRequest: new PullRequest(approvals: $approvals)
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
