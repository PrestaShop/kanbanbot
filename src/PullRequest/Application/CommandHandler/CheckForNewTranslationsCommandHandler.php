<?php

namespace App\PullRequest\Application\CommandHandler;

use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Exception\PullRequestNotFoundException;
use App\PullRequest\Domain\Gateway\PullRequestRepositoryInterface;
use App\PullRequestDashboard\Application\Command\MovePullRequestCardToColumnByLabelCommand;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCardId;
use App\PullRequestDashboard\Domain\Exception\PullRequestCardNotFoundException;
use App\PullRequestDashboard\Domain\Gateway\PullRequestCardRepositoryInterface;

class CheckForNewTranslationsCommandHandler
{
    public function __construct(
        private readonly PullRequestRepositoryInterface $prRepository,
        private readonly PullRequestCardRepositoryInterface $pullRequestCardRepository
    ) {
    }

    public function __invoke(MovePullRequestCardToColumnByLabelCommand $command): void
    {
        $pullRequest = $this->prRepository->find(new PullRequestId($command->repositoryOwner, $command->repositoryName, $command->pullRequestNumber));
        if (null === $pullRequest) {
            throw new PullRequestNotFoundException();
        }

        $bodyParser = new BodyParser($pullRequest->getBody());
        if ($bodyParser->isMergeCategory()) {
            return;
        }

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