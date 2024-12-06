<?php

declare(strict_types=1);

namespace App\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\CheckHookCommand;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Exception\PullRequestNotFoundException;
use App\PullRequest\Domain\Gateway\PullRequestRepositoryInterface;

class CheckHookCommandHandler
{
    public function __construct(
        private readonly PullRequestRepositoryInterface $prRepository,
    ) {
    }

    public function __invoke(CheckHookCommand $command): void
    {
        // This command is only for PrestaShop/PrestaShop repository
        if ('PrestaShop' !== $command->repositoryOwner || 'PrestaShop' !== $command->repositoryName) {
            return;
        }

        // We get the PR
        $prId = new PullRequestId($command->repositoryOwner, $command->repositoryName, $command->pullRequestNumber);
        $pullRequest = $this->prRepository->find($prId);
        if (null === $pullRequest) {
            throw new PullRequestNotFoundException();
        }

        // and its diff
        $prDiff = $this->prRepository->getDiff($prId);

        // If we have some modifications about hooks, we must add label "Hook
        // Contribution" to this PR
        if ($prDiff->hasHooksModifications()) {
            $pullRequest->hookContribution();
            $this->prRepository->update($pullRequest);
        }
    }
}
