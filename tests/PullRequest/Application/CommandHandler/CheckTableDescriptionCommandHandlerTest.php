<?php

declare(strict_types=1);

namespace App\Tests\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\CheckTableDescriptionCommand;
use App\PullRequest\Application\CommandHandler\CheckTableDescriptionCommandHandler;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequest;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestDescription;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Exception\PullRequestNotFoundException;
use App\PullRequest\Infrastructure\Adapter\InMemoryPullRequestRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CheckTableDescriptionCommandHandlerTest extends KernelTestCase
{
    private CheckTableDescriptionCommandHandler $checkTableDescriptionCommandHandler;
    private InMemoryPullRequestRepository $prRepository;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->prRepository = $this->getMockBuilder(InMemoryPullRequestRepository::class)
            ->onlyMethods(['addTableDescriptionErrorsComment'])
            ->getMock();
        /** @var ValidatorInterface $validator */
        $validator = $this->getContainer()->get(ValidatorInterface::class);
        $this->validator = $validator;
        $this->checkTableDescriptionCommandHandler = new CheckTableDescriptionCommandHandler($this->prRepository, $this->validator);
    }

    /**
     * @dataProvider provideTestHandle
     *
     * @param string[] $expectedErrors
     * @param string[] $expectedNotErrors
     * @param string[] $expectedLabels
     */
    public function testHandle(PullRequestId $pullRequestId, string $bodyDescription, array $expectedErrors, array $expectedNotErrors, bool $linkedIssuesNeeded, bool $expectedComment, array $expectedLabels): void
    {
        // We feed the PR repository with a PR
        $this->prRepository->feed([
            PullRequest::create(
                id: $pullRequestId,
                labels: [],
                approvals: [],
                targetBranch: 'main',
                bodyDescription: $bodyDescription,
            ),
        ]);

        // We check errors on the description after validation
        $prDescription = new PullRequestDescription($bodyDescription);
        $errors = $this->validator->validate($prDescription);
        $errorsMessages = array_map(fn (ConstraintViolationInterface $error) => $error->getMessage(), iterator_to_array($errors));
        foreach ($expectedErrors as $expectedError) {
            $this->assertContains($expectedError, $errorsMessages);
        }
        foreach ($expectedNotErrors as $expectedNotError) {
            $this->assertNotContains($expectedNotError, $errorsMessages);
        }

        // We check if issue linked needed
        $this->assertEquals($linkedIssuesNeeded, $prDescription->isLinkedIssuesNeeded());

        // Then, we check if the comment is added or not.
        // @phpstan-ignore-next-line
        $this->prRepository
            ->expects($expectedComment ? $this->once() : $this->never())
            ->method('addTableDescriptionErrorsComment');

        $this->checkTableDescriptionCommandHandler->__invoke(new CheckTableDescriptionCommand(
            repositoryOwner: $pullRequestId->repositoryOwner,
            repositoryName: $pullRequestId->repositoryName,
            pullRequestNumber: $pullRequestId->pullRequestNumber,
        ));

        // We check the automatic labels in PR
        /** @var PullRequest $pr */
        $pr = $this->prRepository->find($pullRequestId);
        $this->assertEquals($expectedLabels, $pr->getLabels());
    }

    /**
     * @return array<array<int, array<int, string>|PullRequestId|bool|string>>
     */
    public static function provideTestHandle(): array
    {
        return [
            [
                new PullRequestId(
                    repositoryOwner: 'fake',
                    repositoryName: 'fake',
                    pullRequestNumber: 'fake'
                ),
                '',
                [],
                [],
                false,
                false,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '',
                [
                    'The `branch` should be `develop`, `9.0.x` or `8.2.x`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))',
                    "The `description` shouldn't be empty. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#description))",
                    'The `type` should be one of these: `new feature`, `improvement`, `bug fix` or `refacto`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))',
                    'The `category` should be one of these: `FO`, `BO`, `CO`, `IN`, `WS`, `TE`, `LO`, `ME` or `PM`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))',
                    'The `BC breaks` should be `yes` or `no`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#bc-breaks))',
                    'The `deprecations` should be `yes` or `no`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#deprecations))',
                    "The `How to test` shouldn't be empty. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#how-to-test))",
                ],
                [],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                "
| Questions         | Answers
| ----------------- | -------------------------------------------------------
| Branch?           | develop / 8.2.x
| Description?      | Please be specific when describing the PR. <br> Every detail helps: versions, browser/server configuration, specific module/theme, etc. Feel free to add more information below this table.
| Type?             | bug fix / improvement / new feature / refacto
| Category?         | FO / BO / CO / IN / WS / TE / LO / ME / PM / see explanations at https://devdocs.prestashop-project.org/8/contribute/contribution-guidelines/pull-requests/#type--category
| BC breaks?        | yes / no
| Deprecations?     | yes / no
| How to test?      | Indicate how to verify that this change works as expected.
| UI Tests          | Please run UI tests and paste here the link to the run. [Read this page to know why and how to use this tool](https://devdocs.prestashop-project.org/8/contribute/contribution-guidelines/ui-tests/).
| Fixed issue or discussion?     | Fixes #{issue number here}, Fixes #{another issue number here}, Fixes https://github.com/PrestaShop/PrestaShop/discussions/ {discussion number here}
| Related PRs       | If theme, autoupgrade or other module change is needed to make this change work, provide a link to related PRs here.
| Sponsor company   | Your company or customer's name goes here (if applicable).
",
                [
                    'The `branch` should be `develop`, `9.0.x` or `8.2.x`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))',
                    "The `description` shouldn't be empty. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#description))",
                    'The `type` should be one of these: `new feature`, `improvement`, `bug fix` or `refacto`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))',
                    'The `category` should be one of these: `FO`, `BO`, `CO`, `IN`, `WS`, `TE`, `LO`, `ME` or `PM`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))',
                    'The `BC breaks` should be `yes` or `no`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#bc-breaks))',
                    'The `deprecations` should be `yes` or `no`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#deprecations))',
                    "The `How to test` shouldn't be empty. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#how-to-test))",
                ],
                [],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Branch?           | fake',
                ['The `branch` should be `develop`, `9.0.x` or `8.2.x`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                [],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Branch?           | develop',
                [],
                ['The `branch` should be `develop`, `9.0.x` or `8.2.x`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                false,
                true,
                ['develop'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Branch?           | 8.2.x',
                [],
                ['The `branch` should be `develop`, `9.0.x` or `8.2.x`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                false,
                true,
                ['8.2.x'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Description?      | This is a fake description',
                [],
                ['The `description` shouldn\'t be empty. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#description))'],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Type?             | fake',
                ['The `type` should be one of these: `new feature`, `improvement`, `bug fix` or `refacto`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                [],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Type?             | bug fix',
                [],
                ['The `type` should be one of these: `new feature`, `improvement`, `bug fix` or `refacto`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                true,
                true,
                ['Bug fix'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Type?             | improvement',
                [],
                ['The `type` should be one of these: `new feature`, `improvement`, `bug fix` or `refacto`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                false,
                true,
                ['Improvement'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Type?             | new feature',
                [],
                ['The `type` should be one of these: `new feature`, `improvement`, `bug fix` or `refacto`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                true,
                true,
                ['Feature'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Type?             | refacto',
                [],
                ['The `type` should be one of these: `new feature`, `improvement`, `bug fix` or `refacto`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                false,
                true,
                ['Refactoring'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Category?             | fake',
                ['The `category` should be one of these: `FO`, `BO`, `CO`, `IN`, `WS`, `TE`, `LO`, `ME` or `PM`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                [],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Category?             | FO',
                [],
                ['The `category` should be one of these: `FO`, `BO`, `CO`, `IN`, `WS`, `TE`, `LO`, `ME` or `PM`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Category?             | BO',
                [],
                ['The `category` should be one of these: `FO`, `BO`, `CO`, `IN`, `WS`, `TE`, `LO`, `ME` or `PM`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Category?             | CO',
                [],
                ['The `category` should be one of these: `FO`, `BO`, `CO`, `IN`, `WS`, `TE`, `LO`, `ME` or `PM`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Category?             | IN',
                [],
                ['The `category` should be one of these: `FO`, `BO`, `CO`, `IN`, `WS`, `TE`, `LO`, `ME` or `PM`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Category?             | WS',
                [],
                ['The `category` should be one of these: `FO`, `BO`, `CO`, `IN`, `WS`, `TE`, `LO`, `ME` or `PM`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Category?             | TE',
                [],
                ['The `category` should be one of these: `FO`, `BO`, `CO`, `IN`, `WS`, `TE`, `LO`, `ME` or `PM`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Category?             | LO',
                [],
                ['The `category` should be one of these: `FO`, `BO`, `CO`, `IN`, `WS`, `TE`, `LO`, `ME` or `PM`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Category?             | ME',
                [],
                ['The `category` should be one of these: `FO`, `BO`, `CO`, `IN`, `WS`, `TE`, `LO`, `ME` or `PM`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Category?             | PM',
                [],
                ['The `category` should be one of these: `FO`, `BO`, `CO`, `IN`, `WS`, `TE`, `LO`, `ME` or `PM`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))'],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| BC breaks?            | fake',
                ['The `BC breaks` should be `yes` or `no`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#bc-breaks))'],
                [],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| BC breaks?            | yes',
                [],
                ['The `BC breaks` should be `yes` or `no`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#bc-breaks))'],
                false,
                true,
                ['BC break'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| BC breaks?            | no',
                [],
                ['The `BC breaks` should be `yes` or `no`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#bc-breaks))'],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Deprecations?          | fake',
                ['The `deprecations` should be `yes` or `no`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#deprecations))'],
                [],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Deprecations?          | yes',
                [],
                ['The `deprecations` should be `yes` or `no`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#deprecations))'],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| Deprecations?          | no',
                [],
                ['The `deprecations` should be `yes` or `no`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#deprecations))'],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '| How to test?          | Fake',
                [],
                ['The `How to test` shouldn\'t be empty. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#how-to-test))'],
                false,
                true,
                [],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '
                | Type?             | bug fix
                | Category?         | TE
                ',
                [],
                [],
                false,
                true,
                ['Bug fix'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '
                | Type?             | bug fix
                | Category?         | ME
                ',
                [],
                [],
                false,
                true,
                ['Bug fix'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '
                | Type?             | bug fix
                | Category?         | PM
                ',
                [],
                [],
                false,
                true,
                ['Bug fix'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '
                | Type?             | bug fix
                | Category?         | BO
                ',
                [],
                [],
                true,
                true,
                ['Bug fix'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '
                | Type?             | new feature
                | Category?         | TE
                ',
                [],
                [],
                false,
                true,
                ['Feature'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '
                | Type?             | new feature
                | Category?         | ME
                ',
                [],
                [],
                false,
                true,
                ['Feature'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '
                | Type?             | new feature
                | Category?         | PM
                ',
                [],
                [],
                false,
                true,
                ['Feature'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '
                | Type?             | new feature
                | Category?         | BO
                ',
                [],
                [],
                true,
                true,
                ['Feature'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '
                | Type?                          | new feature
                | Category?                      | BO
                | Fixed issue or discussion?     | n/a
                ',
                [],
                [],
                false,
                true,
                ['Feature'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '
                | Type?                          | new feature
                | Category?                      | BO
                | Fixed issue or discussion?     | ~
                ',
                [],
                [],
                false,
                true,
                ['Feature'],
            ],
            [
                new PullRequestId(
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: 'fake'
                ),
                '
                | Branch?           | develop
                | Description?      | Fake description 
                | Type?             | new feature
                | Category?         | FO
                | BC breaks?        | no
                | Deprecations?     | no
                | How to test?      | Fake how to test
                | UI Tests          | Fake UI tests 
                | Fixed issue or discussion?     | Fixes #123 
                ',
                [],
                [],
                true,
                false,
                ['develop', 'Feature'],
            ],
        ];
    }

    public function testPullRequestNotFound(): void
    {
        $this->expectException(PullRequestNotFoundException::class);
        $this->checkTableDescriptionCommandHandler->__invoke(new CheckTableDescriptionCommand(
            repositoryOwner: 'PrestaShop',
            repositoryName: 'PrestaShop',
            pullRequestNumber: 'fake'
        ));
    }
}
