<?php

declare(strict_types=1);

namespace App\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\CheckMilestoneCommand;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Exception\PullRequestNotFoundException;
use App\PullRequest\Domain\Gateway\PullRequestRepositoryInterface;

class CheckMilestoneCommandHandler
{
    public function __construct(
        private readonly PullRequestRepositoryInterface $prRepository,
    ) {
    }

    public function __invoke(CheckMilestoneCommand $command): void
    {
        // Retrieve the PullRequest object
        $prId = new PullRequestId($command->repositoryOwner, $command->repositoryName, $command->pullRequestNumber);
        $pullRequest = $this->prRepository->find($prId);
        if (null === $pullRequest) {
            throw new PullRequestNotFoundException();
        }

        // Check if the milestone is needed
        if (!$this->prRepository->isMilestoneNeeded($prId)) {
            return;
        }

        // We check if the milestone is set
        if (null === $pullRequest->getMilestoneNumber()) {
            $this->prRepository->addMissingMilestoneComment($prId);
        }
    }
}
