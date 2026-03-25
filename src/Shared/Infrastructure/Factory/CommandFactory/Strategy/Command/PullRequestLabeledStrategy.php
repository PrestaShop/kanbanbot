<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Factory\CommandFactory\Strategy\Command;

use App\PullRequest\Application\Command\NotifyCommunityQACommand;
use App\PullRequestDashboard\Application\Command\MovePullRequestCardToColumnByLabelCommand;
use App\Shared\Infrastructure\Factory\CommandFactory\CommandStrategyInterface;

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
     *
     * @return array<MovePullRequestCardToColumnByLabelCommand|NotifyCommunityQACommand>
     */
    public function createCommandsFromPayload(array $payload): array
    {
        $repoOwner = $payload['pull_request']['base']['repo']['owner']['login'];
        $repoName = $payload['pull_request']['base']['repo']['name'];
        $prNumber = (string) $payload['pull_request']['number'];
        $labelName = (string) $payload['label']['name'];

        $commands = [
            new MovePullRequestCardToColumnByLabelCommand(
                $this->pullRequestDashboardNumber,
                $repoOwner,
                $repoName,
                $prNumber,
                $labelName,
            ),
        ];

        if ('Waiting for QA by community' === $labelName) {
            $commands[] = new NotifyCommunityQACommand(
                $repoOwner,
                $repoName,
                $prNumber,
            );
        }

        return $commands;
    }
}
