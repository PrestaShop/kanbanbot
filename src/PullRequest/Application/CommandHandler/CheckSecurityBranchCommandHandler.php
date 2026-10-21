<?php

declare(strict_types=1);

namespace App\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\CheckSecurityBranchCommand;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Exception\PullRequestNotFoundException;
use App\PullRequest\Domain\Gateway\PullRequestRepositoryInterface;

class CheckSecurityBranchCommandHandler
{
    // Define the minimum full maintained version of PrestaShop
    private const MIN_MAINTAINED_VERSION = '9'; // v9 and above are maintained for now!

    // Define the minimum version to have security PRs
    private const MIN_SECURITY_VERSION = '8'; // v8 and above can have security PR!

    public function __construct(
        private readonly PullRequestRepositoryInterface $prRepository,
    ) {
    }

    public function __invoke(CheckSecurityBranchCommand $command): void
    {
        // This command is only for PrestaShop/PrestaShop repository
        if ('PrestaShop' !== $command->repositoryOwner || 'PrestaShop' !== $command->repositoryName) {
            return;
        }

        // Only for security branches (if not develop, lower than min maintained version, or greater than min security branch version)
        if (
            'develop' === $command->branchName
            || version_compare($command->branchName, self::MIN_MAINTAINED_VERSION, '>=')
            || version_compare($command->branchName, self::MIN_SECURITY_VERSION, '<')
        ) {
            return;
        }

        // Retrieve the PullRequest object
        $prId = new PullRequestId($command->repositoryOwner, $command->repositoryName, $command->pullRequestNumber);
        $pullRequest = $this->prRepository->find($prId);
        if (null === $pullRequest) {
            throw new PullRequestNotFoundException();
        }

        $this->prRepository->addSecurityBranchComment($prId, $command->branchName);
    }
}
