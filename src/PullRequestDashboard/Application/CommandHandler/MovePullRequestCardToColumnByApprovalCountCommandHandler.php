<?php

declare(strict_types=1);

namespace App\PullRequestDashboard\Application\CommandHandler;

use App\PullRequestDashboard\Application\Command\MovePullRequestCardToColumnByApprovalCountCommand;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCardId;
use App\PullRequestDashboard\Domain\Exception\PullRequestCardNotFoundException;
use App\PullRequestDashboard\Domain\Gateway\PullRequestCardRepositoryInterface;

class MovePullRequestCardToColumnByApprovalCountCommandHandler
{

    public function __construct(private readonly PullRequestCardRepositoryInterface $pullRequestCardRepository)
    {
    }

    public function __invoke(MovePullRequestCardToColumnByApprovalCountCommand $command)
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

        $pullRequestCard->moveByApprovalCount();
        $this->pullRequestCardRepository->update($pullRequestCard);
    }
}