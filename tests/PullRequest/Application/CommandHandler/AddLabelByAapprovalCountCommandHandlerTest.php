<?php

declare(strict_types=1);

namespace App\Tests\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\AddLabelByApprovalCountCommand;
use App\PullRequest\Application\CommandHandler\AddLabelByApprovalCountCommandHandler;
use App\PullRequest\Domain\Aggregate\PullRequest\Approval;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequest;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Exception\PullRequestNotFoundException;
use App\PullRequest\Infrastructure\Adapter\InMemoryPullRequestRepository;
use App\Shared\Infrastructure\Adapter\InMemoryCommitterRepository;
use PHPUnit\Framework\TestCase;

class AddLabelByAapprovalCountCommandHandlerTest extends TestCase
{
    private AddLabelByApprovalCountCommandHandler $addLabelByApprovalCountCommandHandler;
    private InMemoryPullRequestRepository $prRepository;
    private InMemoryCommitterRepository $committerRepository;

    protected function setUp(): void
    {
        $this->prRepository = new InMemoryPullRequestRepository();
        $this->committerRepository = new InMemoryCommitterRepository();
        $this->addLabelByApprovalCountCommandHandler = new AddLabelByApprovalCountCommandHandler($this->prRepository, $this->committerRepository);
    }

    /**
     * @param string[]   $originalLabels
     * @param Approval[] $approvals
     * @param string[]   $expectedLabels
     *
     * @dataProvider provideTestHandle
     */
    public function testHandle(array $originalLabels, PullRequestId $pullRequestId, array $approvals, array $expectedLabels): void
    {
        $this->prRepository->feed([
            PullRequest::create(
                id: $pullRequestId,
                labels: $originalLabels,
                approvals: $approvals,
                targetBranch: 'main'
            ),
        ]);

        $this->addLabelByApprovalCountCommandHandler->__invoke(new AddLabelByApprovalCountCommand(
            repositoryOwner: $pullRequestId->repositoryOwner,
            repositoryName: $pullRequestId->repositoryName,
            pullRequestNumber: $pullRequestId->pullRequestNumber,
        ));
        /** @var PullRequest $pr */
        $pr = $this->prRepository->find(
            new PullRequestId(repositoryOwner: $pullRequestId->repositoryOwner, repositoryName: $pullRequestId->repositoryName, pullRequestNumber: $pullRequestId->pullRequestNumber)
        );

        $this->assertEquals($expectedLabels, $pr->getLabels());
    }

    /**
     * @return array<int|string, array<int, array<int, string>|PullRequestId|Approval[]>>
     */
    public static function provideTestHandle(): array
    {
        return [
            [
                [],
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [new Approval('lartist')],
                [],
            ],
            [
                [],
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [new Approval('lartist'), new Approval('nicosomb')],
                ['Waiting for QA'],
            ],
            [
                [],
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'OtherThanPrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [new Approval('lartist')],
                ['Waiting for QA'],
            ],
            [
                [],
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'OtherThanPrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [new Approval('no_committer')],
                [],
            ],
            'Third approval keeps the label' => [
                [],
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [new Approval('lartist'), new Approval('nicosomb'), new Approval('third')],
                ['Waiting for QA'],
            ],
            'Label not duplicated' => [
                ['Waiting for QA'],
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [new Approval('lartist'), new Approval('nicosomb')],
                ['Waiting for QA'],
            ],
            'Approvals drop below 2 on core' => [
                ['Bug fix', 'Waiting for QA', 'develop'],
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [new Approval('lartist')],
                ['Bug fix', 'develop'],
            ],
            'Approvals drop to 0 on a module' => [
                ['Waiting for QA'],
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'OtherThanPrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [],
                [],
            ],
            'QA validated: label not added back' => [
                ['QA ✔️'],
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [new Approval('lartist'), new Approval('nicosomb')],
                ['QA ✔️'],
            ],
            'Community QA validated: label not added back' => [
                ['QA ✔️ by Community'],
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [new Approval('lartist'), new Approval('nicosomb')],
                ['QA ✔️ by Community'],
            ],
            'Waiting for community QA: no "Waiting for QA" added' => [
                ['Waiting for QA by Community'],
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [new Approval('lartist'), new Approval('nicosomb')],
                ['Waiting for QA by Community'],
            ],
            'Approvals drop: community waiting label removed' => [
                ['Waiting for QA by Community', 'develop'],
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [new Approval('lartist')],
                ['develop'],
            ],
            'Outside PrestaShop org: untouched' => [
                ['Waiting for QA'],
                new PullRequestId(
                    repositoryOwner: 'someone',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'pullRequestNumber'
                ),
                [],
                ['Waiting for QA'],
            ],
        ];
    }

    public function testPullRequestNotFound(): void
    {
        $this->expectException(PullRequestNotFoundException::class);
        $this->addLabelByApprovalCountCommandHandler->__invoke(new AddLabelByApprovalCountCommand(
            repositoryOwner: 'fake',
            repositoryName: 'fake',
            pullRequestNumber: 'fake'
        ));
    }
}
