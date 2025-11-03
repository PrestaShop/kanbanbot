<?php

declare(strict_types=1);

namespace App\Tests\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\CheckTranslationsCommand;
use App\PullRequest\Application\CommandHandler\CheckTranslationsCommandHandler;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequest;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestDiff;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Infrastructure\Adapter\InMemoryPullRequestRepository;
use App\Shared\Infrastructure\Provider\TranslationsCatalogProvider;
use PHPUnit\Framework\TestCase;

class CheckTranslationsCommandHandlerTest extends TestCase
{
    private PullRequestId $prId;
    private PullRequest $pr;
    private InMemoryPullRequestRepository $prRepository;
    private TranslationsCatalogProvider $catalogProvider;
    private CheckTranslationsCommandHandler $checkTranslationsCommandHandler;

    protected function setUp(): void
    {
        $this->prId = new PullRequestId('PrestaShop', 'PrestaShop', '30510');
        $this->pr = PullRequest::create(id: $this->prId, labels: [], approvals: [], targetBranch: 'main');
        $prDiffContent = file_get_contents(__DIR__.'/../../../fixtures/30510.diff');
        $this->prRepository = $this->createMock(InMemoryPullRequestRepository::class);
        $this->catalogProvider = $this->createMock(TranslationsCatalogProvider::class);
        $this->checkTranslationsCommandHandler = new CheckTranslationsCommandHandler($this->prRepository, $this->catalogProvider);

        $this->prRepository->method('find')->willReturn($this->pr);
        $this->prRepository->method('getDiff')->willReturn(PullRequestDiff::parseDiff($this->prId, (string) $prDiffContent));
    }

    /**
     * @param array<string>                $wordingsInCatalog
     * @param array<string, array<string>> $expectedNewWordings
     * @param array<string>                $expectedNewDomains
     *
     * @dataProvider provideTestNewWordingsDetection
     */
    public function testNewWordingsDetection(array $wordingsInCatalog, array $expectedNewWordings, array $expectedNewDomains, bool $expectedLabelWaitingWording): void
    {
        $this->catalogProvider->method('getTranslationsCatalog')->willReturn($wordingsInCatalog); // @phpstan-ignore-line

        if ($expectedNewWordings) {
            $this->prRepository->expects($this->once()) // @phpstan-ignore-line
                ->method('addTranslationsComment')
                ->with($this->prId, $expectedNewWordings, $expectedNewDomains);
        } else {
            $this->prRepository->expects($this->never()) // @phpstan-ignore-line
                ->method('addTranslationsComment');
        }

        $this->checkTranslationsCommandHandler->__invoke(new CheckTranslationsCommand(
            repositoryOwner: $this->prId->repositoryOwner,
            repositoryName: $this->prId->repositoryName,
            pullRequestNumber: $this->prId->pullRequestNumber,
        ));

        /** @var PullRequest $pr */
        $pr = $this->prRepository->find($this->prId);

        $this->assertCount(
            0, // $expectedLabelWaitingWording ? 1 : 0 - this is temporay disabled for now.
            array_filter(
                $pr->getLabels(),
                static fn (string $label) => 'Waiting for wording' === $label
            )
        );
    }

    /**
     * @return array<int, array<int, array<int|string, array<int, string>|string>|bool>>
     */
    public static function provideTestNewWordingsDetection(): array
    {
        return [
            [
                [
                    'By deleting this image format, the theme will not be able to use it. This will result in a degraded experience on your front office.',
                    'Delete the images linked to this image setting',
                    'Are you sure you want to delete this image setting?',
                    'Cancel',
                ],
                [
                    'Admin.Actions' => [
                        'Delete',
                    ],
                ],
                [],
                true,
            ],
            [
                [
                    'By deleting this image format, the theme will not be able to use it. This will result in a degraded experience on your front office.',
                    'Delete the images linked to this image setting',
                    'Are you sure you want to delete this image setting?',
                    'Cancel',
                    'Delete',
                ],
                [],
                [],
                false,
            ],
            [
                [],
                [
                    'Admin.Actions' => [
                        'Cancel',
                        'Delete',
                    ],
                    'Admin.Design.Notification' => [
                        'By deleting this image format, the theme will not be able to use it. This will result in a degraded experience on your front office.',
                        'Delete the images linked to this image setting',
                    ],
                    'Admin.Design.Feature' => [
                        'Are you sure you want to delete this image setting?',
                    ],
                ],
                [
                    'Admin.Design.Notification',
                    'Admin.Design.Feature',
                    'Admin.Actions',
                ],
                true,
            ],
        ];
    }
}
