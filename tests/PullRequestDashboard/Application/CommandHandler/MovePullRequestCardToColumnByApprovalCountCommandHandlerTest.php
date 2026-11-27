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
     * @return array<string, array{0: string, 1: string, 2: Approval[], 3: string, 4: bool}>
     */
    public static function provideTestHandleApprovalsDropped(): array
    {
        return [
            'Core, 2 -> 1 approval' => ['To be tested', 'PrestaShop', [new Approval('lartist')], 'Need 2nd approval', false],
            'Core, 2 -> 0 approval' => ['To be tested', 'PrestaShop', [], 'Ready for review', false],
            'Core, 1 -> 0 approval' => ['Need 2nd approval', 'PrestaShop', [], 'Ready for review', false],
            'Core, still 2 approvals' => ['To be tested', 'PrestaShop', [new Approval('lartist'), new Approval('nicosomb')], 'To be tested', false],
            'Module, 1 -> 0 approval' => ['To be tested', 'OtherThanPrestaShop', [], 'Ready for review', false],
            'Module, still 1 approval' => ['To be tested', 'OtherThanPrestaShop', [new Approval('lartist')], 'To be tested', false],
            'Other column untouched' => ['Waiting for PM/UX/Dev', 'PrestaShop', [new Approval('lartist')], 'Waiting for PM/UX/Dev', true],
            'Dismissal: Waiting for author untouched' => ['Waiting for author', 'PrestaShop', [], 'Waiting for author', false],
            'Push: Waiting for author, 0 approval' => ['Waiting for author', 'PrestaShop', [], 'Ready for review', true],
            'Push: Waiting for author, core 1 approval' => ['Waiting for author', 'PrestaShop', [new Approval('lartist')], 'Need 2nd approval', true],
            'Push: Waiting for author, core 2 approvals' => ['Waiting for author', 'PrestaShop', [new Approval('lartist'), new Approval('nicosomb')], 'To be tested', true],
            'Push: Waiting for author, module 1 approval' => ['Waiting for author', 'OtherThanPrestaShop', [new Approval('lartist')], 'To be tested', true],
        ];
    }

    /**
     * @dataProvider provideTestHandleApprovalsDropped
     *
     * @param Approval[] $approvals
     */
    public function testHandleApprovalsDropped(string $originalColumn, string $repositoryName, array $approvals, string $expectedColumnName, bool $authorPushed): void
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
            authorPushed: $authorPushed,
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
