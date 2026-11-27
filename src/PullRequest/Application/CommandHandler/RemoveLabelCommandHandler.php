<?php

declare(strict_types=1);

namespace App\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\RemoveLabelCommand;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Exception\PullRequestNotFoundException;
use App\PullRequest\Domain\Gateway\PullRequestRepositoryInterface;

class RemoveLabelCommandHandler
{
    public function __construct(
        private readonly PullRequestRepositoryInterface $prRepository,
    ) {
    }

    public function __invoke(RemoveLabelCommand $command): void
    {
        $pullRequest = $this->prRepository->find(new PullRequestId($command->repositoryOwner, $command->repositoryName, $command->pullRequestNumber));
        if (null === $pullRequest) {
            throw new PullRequestNotFoundException();
        }
        if (!in_array($command->label, $pullRequest->getLabels(), true)) {
            return;
        }
        $pullRequest->removeLabel($command->label);
        $this->prRepository->update($pullRequest);
    }
}
