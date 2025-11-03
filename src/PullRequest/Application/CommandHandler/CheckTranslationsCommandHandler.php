<?php

declare(strict_types=1);

namespace App\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\CheckTranslationsCommand;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Exception\PullRequestNotFoundException;
use App\PullRequest\Domain\Gateway\PullRequestRepositoryInterface;
use App\Shared\Infrastructure\Provider\TranslationsCatalogProvider;

class CheckTranslationsCommandHandler
{
    public function __construct(
        private readonly PullRequestRepositoryInterface $prRepository,
        private readonly TranslationsCatalogProvider $catalogProvider,
    ) {
    }

    public function __invoke(CheckTranslationsCommand $command): void
    {
        // We get the PR and its diff.
        $prId = new PullRequestId($command->repositoryOwner, $command->repositoryName, $command->pullRequestNumber);
        $pullRequest = $this->prRepository->find($prId);
        if (null === $pullRequest) {
            throw new PullRequestNotFoundException();
        }

        $prDiff = $this->prRepository->getDiff($prId);
        $translations = $prDiff->getTranslations();

        // We check the PR target branch to determine the PrestaShop version catalog to retrieve.
        $PSVersion = $this->catalogProvider->getCatalogVersionByPullRequest($pullRequest);

        // Then, for each domain, we check if there are new translations keys.
        $newTranslationsKeys = [];
        $newDomains = [];
        foreach ($translations as $domain => $keys) {
            $catalog = $this->catalogProvider->getTranslationsCatalog('en-US', $domain, $PSVersion);
            if (empty($catalog)) {
                $newDomains[] = $domain;
            }

            $new = array_values(array_diff($keys, $catalog));
            if (!empty($new)) {
                $newTranslationsKeys[$domain] = $new;
            }
        }

        // If we have new translations keys, we add a comment to the PR.
        if (!empty($newTranslationsKeys)) {
            $this->prRepository->addTranslationsComment($prId, $newTranslationsKeys, $newDomains);
            // $pullRequest->waitingForWording();
            $this->prRepository->update($pullRequest);
        }
    }
}
