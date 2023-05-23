<?php

declare(strict_types=1);

namespace App\PullRequest\Domain\Aggregate\PullRequest;

class PullRequest
{
    /**
     * @param string[] $labels
     * @param Approval[] $approvals
     */
    private function __construct(
        private PullRequestId $id,
        private array $labels,
        private array $approvals,
    ) {
    }

    /**
     * @param string[] $labels
     * @param Approval[] $approvals
     */
    public static function create(PullRequestId $id, array $labels, array $approvals): self
    {
        return new self($id, $labels, $approvals);
    }

    public function getId(): PullRequestId
    {
        return $this->id;
    }

    /**
     * @return string[]
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    public function requestChanges(): void
    {
        if (!in_array('Waiting for author', $this->labels, true)) {
            $this->labels[] = 'Waiting for author';
        }
    }

    /**
     * @param string[] $committers
     */
    public function addLabelByApprovalCount(array $committers): void
    {
        //Todo: use enum instead
        $validApprovals = array_filter(
            $this->approvals,
            static fn (Approval $approval) => in_array($approval->author, $committers, true)
        );
        if (
            $this->id->repositoryOwner === 'PrestaShop'
            && $this->id->repositoryName === 'PrestaShop'
            && count($validApprovals) === 2
        ) {
            $this->labels[] = 'Waiting for QA';
        }
        if (
            $this->id->repositoryOwner === 'PrestaShop'
            && $this->id->repositoryName !== 'PrestaShop'
            && count($validApprovals) === 1
        ) {
            $this->labels[] = 'Waiting for QA';
        }
    }
}
