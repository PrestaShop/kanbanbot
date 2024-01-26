<?php

namespace App\Shared\Infrastructure\Factory\CommandFactory\Strategy\Command;

use App\PullRequestDashboard\Application\Command\MovePullRequestCardToColumnByLabelCommand;
use App\Shared\Infrastructure\Factory\CommandFactory\CommandStrategyInterface;

class PullRequestCheckTranslationsStrategy implements CommandStrategyInterface
{
    public function __construct(
        private readonly string $pullRequestDashboardNumber,
    ) {
    }

    public function supports(string $eventType, array $payload): bool
    {
        return true;
    }

    public function createCommandsFromPayload(array $payload): array
    {
        dump($payload);
        die();
        return [new MovePullRequestCardToColumnByLabelCommand(
            $this->pullRequestDashboardNumber,
            $payload['pull_request']['base']['repo']['owner']['login'],
            $payload['pull_request']['base']['repo']['name'],
            (string) $payload['pull_request']['number'],
            (string) $payload['label']['name'],
        )];
    }
}