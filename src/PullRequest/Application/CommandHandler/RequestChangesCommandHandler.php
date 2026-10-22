<?php

declare(strict_types=1);

namespace App\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\RequestChangesCommand;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Exception\PullRequestNotFoundException;
use App\PullRequest\Domain\Gateway\PullRequestRepositoryInterface;

class RequestChangesCommandHandler
{
    public function __construct(
        private readonly PullRequestRepositoryInterface $prRepository,
    ) {
    }

    public function __invoke(RequestChangesCommand $command): void
    {
        $pullRequest = $this->prRepository->find(new PullRequestId($command->repositoryOwner, $command->repositoryName, $command->pullRequestNumber));
        if (null === $pullRequest) {
            throw new PullRequestNotFoundException();
        }
        $pullRequest->requestChanges();
        $this->prRepository->update($pullRequest);
    }
}
