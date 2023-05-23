<?php

declare(strict_types=1);

namespace App\Shared\Factory\CommandFactory\Strategy;

use App\PullRequestDashboard\Application\Command\MovePullRequestCardToColumnByLabelCommand;
use App\Shared\Factory\CommandFactory\CommandStrategyInterface;

class PullRequestLabeledStrategy implements CommandStrategyInterface
{

    public function __construct(
        private readonly string $pullRequestDashboardNumber,
    ) {
    }

    /**
     * @param array{
     *     action: string
     * } $payload
     */
    public function supports(string $eventType, array $payload): bool
    {
        return 'pull_request' === $eventType and 'labeled' === $payload['action'];
    }

    /**
     * @param array{
     *     pull_request: array{
     *         base: array{
     *             repo: array{
     *                 name: string,
     *                 owner: array{
     *                     login: string
     *                 }
     *             }
     *         },
     *         number: int,
     *     },
     *     label: array{
     *        name: string
     *     }
     * } $payload
     * @return MovePullRequestCardToColumnByLabelCommand[]
     */
    public function createCommandsFromPayload(array $payload): array
    {
        return [new MovePullRequestCardToColumnByLabelCommand(
            $this->pullRequestDashboardNumber,
            $payload['pull_request']['base']['repo']['owner']['login'],
            $payload['pull_request']['base']['repo']['name'],
            (string) $payload['pull_request']['number'],
            (string) $payload['label']['name'],
        )];
    }
}