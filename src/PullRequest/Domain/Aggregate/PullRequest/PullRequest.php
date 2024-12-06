<?php

declare(strict_types=1);

namespace App\PullRequest\Domain\Aggregate\PullRequest;

class PullRequest
{
    /**
     * @param string[]   $labels
     * @param Approval[] $approvals
     */
    private function __construct(
        private PullRequestId $id,
        private array $labels,
        private array $approvals,
        private string $targetBranch,
        private string $bodyDescription = '',
        private ?int $milestoneNumber = null,
    ) {
    }

    /**
     * @param string[]   $labels
     * @param Approval[] $approvals
     */
    public static function create(PullRequestId $id, array $labels, array $approvals, string $targetBranch, string $bodyDescription = '', ?int $milestoneNumber = null): self
    {
        return new self($id, $labels, $approvals, $targetBranch, $bodyDescription, $milestoneNumber);
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

    public function waitingForWording(): void
    {
        if (!in_array('Waiting for wording', $this->labels, true)) {
            $this->labels[] = 'Waiting for wording';
        }
    }

    public function hookContribution(): void
    {
        if (!in_array('Hook Contribution', $this->labels, true)) {
            $this->labels[] = 'Hook Contribution';
        }
    }

    /**
     * @param string[] $committers
     */
    public function addLabelByApprovalCount(array $committers): void
    {
        $validApprovals = array_filter(
            $this->approvals,
            static fn (Approval $approval) => in_array($approval->author, $committers, true)
        );
        if (
            'PrestaShop' === $this->id->repositoryOwner
            && 'PrestaShop' === $this->id->repositoryName
            && 2 === count($validApprovals)
        ) {
            $this->labels[] = 'Waiting for QA';
        }
        if (
            'PrestaShop' === $this->id->repositoryOwner
            && 'PrestaShop' !== $this->id->repositoryName
            && 1 === count($validApprovals)
        ) {
            $this->labels[] = 'Waiting for QA';
        }
    }

    public function getTargetBranch(): string
    {
        return $this->targetBranch;
    }

    public function getBodyDescription(): string
    {
        return $this->bodyDescription;
    }

    public function getMilestoneNumber(): ?int
    {
        return $this->milestoneNumber;
    }

    public function addLabelsByDescription(PullRequestDescription $description): void
    {
        // Remove some labels in labels list
        $this->labels = array_diff($this->labels, [
            'develop',
            '8.1.x',
            '8.2.x',
            '9.0.x',
            'Bug fix',
            'Improvement',
            'Feature',
            'Refactoring',
            'BC break',
        ]);

        // Add label for branch
        if ($description->getBranch()) {
            $this->labels[] = $description->getBranch();
        }

        // Add label for PR type
        if ($description->getType()) {
            $mapLabelTypes = [
                'bug fix' => 'Bug fix',
                'improvement' => 'Improvement',
                'new feature' => 'Feature',
                'refacto' => 'Refactoring',
            ];
            $this->labels[] = $mapLabelTypes[$description->getType()];
        }

        // Add label if BC break declared
        if ($description->isBCBreak()) {
            $this->labels[] = 'BC break';
        }
    }

    public function isQAValidated(): bool
    {
        return in_array('QA ✔️', $this->labels);
    }
}
