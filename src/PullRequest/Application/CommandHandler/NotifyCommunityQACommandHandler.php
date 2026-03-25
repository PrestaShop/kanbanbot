<?php

declare(strict_types=1);

namespace App\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\NotifyCommunityQACommand;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Gateway\PullRequestRepositoryInterface;

class NotifyCommunityQACommandHandler
{
    public function __construct(
        private readonly PullRequestRepositoryInterface $prRepository
    ) {
    }

    public function __invoke(NotifyCommunityQACommand $command): void
    {
        $prId = new PullRequestId($command->repositoryOwner, $command->repositoryName, $command->pullRequestNumber);

        $this->prRepository->addCommunityQAComment($prId);
    }
}
