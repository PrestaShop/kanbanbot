<?php

declare(strict_types=1);

namespace App\PullRequest\Infrastructure\Adapter;

use App\PullRequest\Domain\Aggregate\PullRequest\PullRequest;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestDiff;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Gateway\PullRequestRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Validator\ConstraintViolationListInterface;

#[When(env: 'test')]
class InMemoryPullRequestRepository implements PullRequestRepositoryInterface
{
    /** @var PullRequest[] */
    private $prs = [];

    public function find(PullRequestId $pullRequestId): ?PullRequest
    {
        $pr = $this->prs[$this->getIdByPrId(new PullRequestId($pullRequestId->repositoryOwner, $pullRequestId->repositoryName, $pullRequestId->pullRequestNumber))] ?? null;
        if ($pr instanceof PullRequest) {
            $pr = clone $pr;
        }

        return $pr;
    }

    /**
     * @param PullRequest[] $prs
     */
    public function feed(array $prs): void
    {
        foreach ($prs as $pr) {
            $this->prs[$this->getIdByPrId($pr->getId())] = $pr;
        }
    }

    public function update(PullRequest $pullRequest): void
    {
        $this->prs[$this->getIdByPrId($pullRequest->getId())] = $pullRequest;
    }

    private function getIdByPrId(PullRequestId $prId): string
    {
        return $prId->repositoryOwner.'-'.$prId->repositoryName.'-'.$prId->pullRequestNumber;
    }

    public function getDiff(PullRequestId $pullRequestId): PullRequestDiff
    {
        return new PullRequestDiff($pullRequestId, []);
    }

    public function addTranslationsComment(PullRequestId $pullRequestId, array $newTranslations, array $newDomains): void
    {
    }

    public function addWelcomeComment(PullRequestId $pullRequestId, string $contributor): void
    {
    }

    public function addTableDescriptionErrorsComment(PullRequestId $pullRequestId, ConstraintViolationListInterface $errors, bool $isLinkedIssuesNeeded): void
    {
    }

    public function removeTableDescriptionErrorsComment(PullRequestId $pullRequestId): void
    {
    }

    public function addMissingMilestoneComment(PullRequestId $pullRequestId): void
    {
    }

    public function isMilestoneNeeded(PullRequestId $pullRequestId): bool
    {
        return true;
    }

    public function addSecurityBranchComment(PullRequestId $pullRequestId, string $targetedBranch): void
    {
    }

    public function addCommunityQAComment(PullRequestId $pullRequestId): void
    {
    }
}
