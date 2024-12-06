<?php

declare(strict_types=1);

namespace App\PullRequest\Domain\Aggregate\PullRequest;

use Symfony\Component\Validator\Constraints as Assert;

class PullRequestDescription
{
    public const TARGET_BRANCH_AVAILABLE = ['develop', '8.1.x', '8.2.x', '9.0.x'];
    public const TEMPLATE_DESCRIPTION = 'Please be specific when describing the PR. <br> Every detail helps: versions, browser/server configuration, specific module/theme, etc. Feel free to add more information below this table.';
    public const TYPES_AVAILABLE = ['bug fix', 'improvement', 'new feature', 'refacto'];
    public const CATEGORIES_AVAILABLE = ['FO', 'BO', 'CO', 'IN', 'WS', 'TE', 'LO', 'ME', 'PM'];
    public const TEMPLATE_HOW_TO_TEST = 'Indicate how to verify that this change works as expected.';
    public const TEMPLATE_UI_TESTS = 'Please run UI tests and paste here the link to the run. [Read this page to know why and how to use this tool](https://devdocs.prestashop-project.org/8/contribute/contribution-guidelines/ui-tests/).';

    public function __construct(
        private string $bodyContent
    ) {
    }

    #[Assert\NotBlank(message: 'The `branch` should be `develop`, `9.0.x` or `8.2.x`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))')]
    public function getBranch(): ?string
    {
        $branch = $this->extractWithRegex('Branch');
        if (in_array($branch, self::TARGET_BRANCH_AVAILABLE)) {
            return $branch;
        }

        return null;
    }

    #[Assert\NotBlank(message: 'The `description` shouldn\'t be empty. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#description))')]
    public function getDescription(): ?string
    {
        $description = $this->extractWithRegex('Description');
        if (self::TEMPLATE_DESCRIPTION !== $description) {
            return $description;
        }

        return null;
    }

    #[Assert\NotBlank(message: 'The `type` should be one of these: `new feature`, `improvement`, `bug fix` or `refacto`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))')]
    public function getType(): ?string
    {
        $type = strtolower($this->extractWithRegex('Type'));
        if (in_array($type, self::TYPES_AVAILABLE)) {
            return $type;
        }

        return null;
    }

    #[Assert\NotBlank(message: 'The `category` should be one of these: `FO`, `BO`, `CO`, `IN`, `WS`, `TE`, `LO`, `ME` or `PM`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#branch))')]
    public function getCategory(): ?string
    {
        $category = strtoupper($this->extractWithRegex('Category'));
        if (in_array($category, self::CATEGORIES_AVAILABLE)) {
            return $category;
        }

        return null;
    }

    #[Assert\NotNull(message: 'The `BC breaks` should be `yes` or `no`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#bc-breaks))')]
    public function isBcBreak(): ?bool
    {
        $bcBreak = strtolower($this->extractWithRegex('BC breaks'));
        if (in_array($bcBreak, ['yes', 'no'])) {
            return 'yes' === $bcBreak;
        }

        return null;
    }

    #[Assert\NotNull(message: 'The `deprecations` should be `yes` or `no`. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#deprecations))')]
    public function isDeprecated(): ?bool
    {
        $deprecations = strtolower($this->extractWithRegex('Deprecations'));
        if (in_array($deprecations, ['yes', 'no'])) {
            return 'yes' === $deprecations;
        }

        return null;
    }

    #[Assert\NotBlank(message: 'The `How to test` shouldn\'t be empty. ([Read explanation](https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/pull-requests/#how-to-test))')]
    public function getHowToTest(): ?string
    {
        $howToTest = $this->extractWithRegex('How to test');
        if (self::TEMPLATE_HOW_TO_TEST !== $howToTest) {
            return $howToTest;
        }

        return null;
    }

    public function getUITests(): ?string
    {
        $uiTests = $this->extractWithRegex('UI Tests');
        if (self::TEMPLATE_UI_TESTS !== $uiTests) {
            return $uiTests;
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function getIssuesFixed(): array
    {
        $issuesFixed = $this->extractWithRegex('Fixed issue or discussion');

        preg_match_all('/(?:#[0-9]+|https:\/\/github.com\/.*\/.*\/issues\/[0-9]+|https:\/\/github.com\/.*\/.*\/discussions\/[0-9]+)/', $issuesFixed, $matches);

        return array_pop($matches);
    }

    public function hasLinkedIssues(): bool
    {
        return !empty($this->getIssuesFixed());
    }

    /**
     * @return string[]
     */
    public function getPrRelated(): array
    {
        $prRelated = $this->extractWithRegex('Related PRs');

        preg_match_all('/(?:#[0-9]+|https:\/\/github.com\/.*\/.*\/pull\/[0-9]+)/', $prRelated, $matches);

        return array_pop($matches);
    }

    public function getSponsor(): ?string
    {
        return $this->extractWithRegex('Sponsor company');
    }

    public function isLinkedIssuesNeeded(): bool
    {
        // If "n/a" or "~" is found, it means that the PR doesn't need an issue anyway.
        if (in_array(strtolower($this->extractWithRegex('Fixed issue or discussion')), ['n/a', '~'])) {
            return false;
        }

        return in_array($this->getType(), ['bug fix', 'new feature']) && !in_array($this->getCategory(), ['TE', 'ME', 'PM']);
    }

    private function extractWithRegex(string $field, string $patternValue = '[^|]*'): string
    {
        $regex = sprintf('~(?:\|?\s*%s\??\s+\|\s*)(%s)\s*~', $field, $patternValue);
        preg_match($regex, $this->bodyContent, $matches);

        return isset($matches[1]) ? trim($matches[1]) : '';
    }
}
