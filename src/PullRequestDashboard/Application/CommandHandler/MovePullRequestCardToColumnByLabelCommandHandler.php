<?php

declare(strict_types=1);

namespace App\PullRequestDashboard\Application\CommandHandler;

use App\PullRequestDashboard\Application\Command\MovePullRequestCardToColumnByLabelCommand;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCardId;
use App\PullRequestDashboard\Domain\Exception\PullRequestCardNotFoundException;
use App\PullRequestDashboard\Domain\Gateway\PullRequestCardRepositoryInterface;

class MovePullRequestCardToColumnByLabelCommandHandler
{
    public function __construct(private readonly PullRequestCardRepositoryInterface $pullRequestCardRepository)
    {
    }

    public function __invoke(MovePullRequestCardToColumnByLabelCommand $command): void
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

        $pullRequestCard->moveColumnByLabel($command->label);
        $this->pullRequestCardRepository->update($pullRequestCard);
    }
}
