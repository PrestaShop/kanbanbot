<?php

namespace App\PullRequest\Domain\Gateway;

use App\PullRequest\Domain\Aggregate\PullRequest\PullRequest;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestDiff;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use Symfony\Component\Validator\ConstraintViolationListInterface;

interface PullRequestRepositoryInterface
{
    public function find(PullRequestId $pullRequestId): ?PullRequest;

    public function update(PullRequest $pullRequest): void;

    public function getDiff(PullRequestId $pullRequestId): PullRequestDiff;

    /**
     * @param array<string, array<int, string|null>> $newTranslations
     * @param string[]                               $newDomains
     */
    public function addTranslationsComment(PullRequestId $pullRequestId, array $newTranslations, array $newDomains): void;

    public function addWelcomeComment(PullRequestId $pullRequestId, string $contributor): void;

    public function addTableDescriptionErrorsComment(PullRequestId $pullRequestId, ConstraintViolationListInterface $errors, bool $isLinkedIssuesNeeded): void;

    public function removeTableDescriptionErrorsComment(PullRequestId $pullRequestId): void;

    public function addMissingMilestoneComment(PullRequestId $pullRequestId): void;

    /**
     * Check if a milestone is needed for the PR.
     * (If there an opened milestone in the repository, we consider that a milestone is needed).
     */
    public function isMilestoneNeeded(PullRequestId $pullRequestId): bool;

    public function addSecurityBranchComment(PullRequestId $pullRequestId, string $targetedBranch): void;

    public function addCommunityQAComment(PullRequestId $pullRequestId): void;
}
