<?php

declare(strict_types=1);

namespace App\PullRequestDashboard\Application\CommandHandler;

use App\PullRequestDashboard\Application\Command\MovePullRequestCardToColumnByApprovalCountCommand;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCardId;
use App\PullRequestDashboard\Domain\Exception\PullRequestCardNotFoundException;
use App\PullRequestDashboard\Domain\Gateway\PullRequestCardRepositoryInterface;
use App\Shared\Domain\Gateway\CommitterRepositoryInterface;

class MovePullRequestCardToColumnByApprovalCountCommandHandler
{
    public function __construct(
        private readonly PullRequestCardRepositoryInterface $pullRequestCardRepository,
        private readonly CommitterRepositoryInterface $committersRepository
    ) {
    }

    public function __invoke(MovePullRequestCardToColumnByApprovalCountCommand $command): void
    {
        $pullRequestCard = $this->pullRequestCardRepository->find(
            new PullRequestCardId(
                projectNumber: $command->projectNumber,
                repositoryOwner: $command->repositoryOwner,
                repositoryName: $command->repositoryName,
                pullRequestNumber: $command->pullRequestNumber
            )
        );

        if (null === $pullRequestCard) {
            throw new PullRequestCardNotFoundException();
        }

        $pullRequestCard->moveByApprovalCount($this->committersRepository->findAll($pullRequestCard->getId()->repositoryOwner));
        $this->pullRequestCardRepository->update($pullRequestCard);
    }
}
