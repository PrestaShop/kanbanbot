<?php

declare(strict_types=1);

namespace App\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\WelcomeNewContributorCommand;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Gateway\PullRequestRepositoryInterface;
use App\Shared\Domain\Gateway\CommitterRepositoryInterface;

class WelcomeNewContributorCommandHandler
{
    public const EXCLUDED_CONTRIBUTORS = [
        'dependabot[bot]',
        'ps-jarvis',
        'github-actions[bot]',
    ];

    public function __construct(
        private readonly CommitterRepositoryInterface $committerRepository,
        private readonly PullRequestRepositoryInterface $prRepository,
    ) {
    }

    public function __invoke(WelcomeNewContributorCommand $command): void
    {
        // We ignore dependabot PRs.
        if (in_array($command->contributor, self::EXCLUDED_CONTRIBUTORS)) {
            return;
        }

        // We check if the committer is a new contributor.
        if ($this->committerRepository->isNewContributor($command->repositoryOwner, $command->repositoryName, $command->contributor)) {
            // If it is, we add a comment to the PR.
            $prId = new PullRequestId($command->repositoryOwner, $command->repositoryName, $command->pullRequestNumber);
            $this->prRepository->addWelcomeComment($prId, $command->contributor);
        }
    }
}
