<?php

declare(strict_types=1);

namespace App\PullRequestDashboard\Domain\Aggregate\PullRequestCard;

class PullRequestCard
{
    private const REPOSITORY_WITH_2_APPROVALS = [
        'PrestaShop/PrestaShop'
    ];

    private function __construct(
        private PullRequestCardId $id,
        private string            $columnName,
        private PullRequest       $pullRequest
    ) {
    }

    public static function create(PullRequestCardId $id, string $columnName, PullRequest $pullRequest): self
    {
        return new self($id, $columnName, $pullRequest);
    }

    public function getId(): PullRequestCardId
    {
        return $this->id;
    }

    public function getColumnName(): string
    {
        return $this->columnName;
    }

    public function moveColumnByLabel(string $label): void
    {
        if ($label === 'Waiting for author') {
            $this->columnName = $label;
        }

        if ($label === 'Waiting for QA') {
            $this->columnName = 'To be tested';
        }
        if (in_array($label, ['Waiting for PM', 'Waiting for UX', 'Waiting for dev'], true)) {
            $this->columnName = 'Waiting for PM/UX/Dev';
        }
    }

    /**
     * @param string[] $committers
     */
    public function moveByApprovalCount(array $committers): void
    {
        //Todo: use enum
        $validApprovals = array_filter(
            $this->pullRequest->approvals,
            static fn (Approval $approval) => in_array($approval->author, $committers, true)
        );
        if (
            count($validApprovals) === 1
            and in_array(
                $this->id->repositoryOwner . '/' . $this->id->repositoryName,
                self::REPOSITORY_WITH_2_APPROVALS,
                true
            )
        ) {
            $this->columnName = 'Need 2nd approval';
        }
    }
}
