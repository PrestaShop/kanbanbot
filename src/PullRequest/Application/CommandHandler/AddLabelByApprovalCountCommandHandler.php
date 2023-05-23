<?php

declare(strict_types=1);

namespace App\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\AddLabelByApprovalCountCommand;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Exception\PullRequestNotFoundException;
use App\PullRequest\Domain\Gateway\CommitterRepositoryInterface;
use App\PullRequest\Domain\Gateway\PullRequestRepositoryInterface;

class AddLabelByApprovalCountCommandHandler
{

    public function __construct(
        private readonly PullRequestRepositoryInterface $pullRequestRepository,
        private readonly CommitterRepositoryInterface $committerRepository
    )
    {
    }

    public function __invoke(AddLabelByApprovalCountCommand $command): void
    {
        $pullRequest = $this->pullRequestRepository->find(new PullRequestId($command->repositoryOwner, $command->repositoryName, $command->pullRequestNumber));
        if (null === $pullRequest) {
            throw new PullRequestNotFoundException();
        }
        $pullRequest->addLabelByApprovalCount($this->committerRepository->findAll($pullRequest->getId()));
        $this->pullRequestRepository->update($pullRequest);
    }
}