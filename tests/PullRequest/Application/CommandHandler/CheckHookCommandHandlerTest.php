<?php

declare(strict_types=1);

namespace App\Tests\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\CheckHookCommand;
use App\PullRequest\Application\CommandHandler\CheckHookCommandHandler;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequest;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestDiff;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Infrastructure\Adapter\InMemoryPullRequestRepository;
use PHPUnit\Framework\TestCase;

class CheckHookCommandHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        /* $this->prId = new PullRequestId('PrestaShop', 'PrestaShop', '30510'); */
        /* $this->pr = PullRequest::create(id: $this->prId, labels: [], approvals: [], targetBranch: 'main'); */
        /* $prDiffContent = file_get_contents(__DIR__.'/../../../fixtures/30510.diff'); */
        /* $this->prRepository = $this->createMock(InMemoryPullRequestRepository::class); */
        /* $this->catalogProvider = $this->createMock(TranslationsCatalogProvider::class); */
        /* $this->checkTranslationsCommandHandler = new CheckTranslationsCommandHandler($this->prRepository, $this->catalogProvider); */

        /* $this->prRepository->method('find')->willReturn($this->pr); */
        /* $this->prRepository->method('getDiff')->willReturn(PullRequestDiff::parseDiff($this->prId, (string) $prDiffContent)); */
    }

    /**
     * @dataProvider provideTestHookContributionDetection
     */
    public function testPullRequestHasHooksModifications(string $prId, bool $expectedLabelHookContribution): void
    {
        $pullRequestId = new PullRequestId('PrestaShop', 'PrestaShop', $prId);
        $pullRequest = PullRequest::create(id: $pullRequestId, labels: [], approvals: [], targetBranch: 'main');

        $prDiffContent = file_get_contents(__DIR__.'/../../../fixtures/'.$prId.'.diff');

        /** @var InMemoryPullRequestRepository $prRepository */
        $prRepository = $this->getMockBuilder(InMemoryPullRequestRepository::class)
                             ->onlyMethods(['getDiff'])
                             ->getMock();
        $prRepository->feed([$pullRequest]);

        $checkHookCommandHandler = new CheckHookCommandHandler($prRepository);

        $prRepository->method('getDiff')->willReturn(PullRequestDiff::parseDiff($pullRequestId, (string) $prDiffContent)); // @phpstan-ignore-line

        $checkHookCommandHandler->__invoke(new CheckHookCommand(
            repositoryOwner: $pullRequestId->repositoryOwner,
            repositoryName: $pullRequestId->repositoryName,
            pullRequestNumber: $pullRequestId->pullRequestNumber,
        ));

        /** @var PullRequest $editedPullRequest */
        $editedPullRequest = $prRepository->find($pullRequestId);

        if ($expectedLabelHookContribution) {
            $this->assertContains('Hook Contribution', $editedPullRequest->getLabels());
        } else {
            $this->assertNotContains('Hook Contribution', $editedPullRequest->getLabels());
        }
    }

    /**
     * @return array<array<int, string|bool>>
     */
    public static function provideTestHookContributionDetection(): array
    {
        return [
            ['30510', false],
            ['37448', true],
        ];
    }
}
