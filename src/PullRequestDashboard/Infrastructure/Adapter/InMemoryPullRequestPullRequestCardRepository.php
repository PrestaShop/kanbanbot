<?php

declare(strict_types=1);

namespace App\PullRequestDashboard\Infrastructure\Adapter;

use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCard;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCardId;
use App\PullRequestDashboard\Domain\Gateway\PullRequestCardRepositoryInterface;

class InMemoryPullRequestPullRequestCardRepository implements PullRequestCardRepositoryInterface
{
    /** @var PullRequestCard[]  */
    private array $pullRequestCards = [];
    /**
     * @param PullRequestCard[] $pullRequestCards
     */
    public function feed(array $pullRequestCards): void
    {
        foreach ($pullRequestCards as $pullRequestCard) {
            $this->pullRequestCards[$this->getIdByCardId($pullRequestCard->getId())] = $pullRequestCard;
        }
    }

    private function getIdByCardId(PullRequestCardId $cardId): string
    {
        return $cardId->projectNumber.'-'.$cardId->repositoryOwner.'-'.$cardId->repositoryName.'-'.$cardId->pullRequestNumber;
    }

    public function find(PullRequestCardId $pullRequestCardId): ?PullRequestCard
    {
        $pullRequestCard = $this->pullRequestCards[$this->getIdByCardId(new PullRequestCardId($pullRequestCardId->projectNumber, $pullRequestCardId->repositoryOwner, $pullRequestCardId->repositoryName, $pullRequestCardId->pullRequestNumber))] ?? null;
        if ($pullRequestCard instanceof PullRequestCard) {
            $pullRequestCard = clone $pullRequestCard;
        }

        return $pullRequestCard;
    }

    public function update(PullRequestCard $pullRequestCard): void
    {
        $this->pullRequestCards[$this->getIdByCardId($pullRequestCard->getId())] = $pullRequestCard;
    }

}