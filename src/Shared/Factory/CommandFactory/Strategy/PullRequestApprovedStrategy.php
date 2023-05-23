<?php

declare(strict_types=1);

namespace App\Shared\Factory\CommandFactory\Strategy;

use App\PullRequest\Application\Command\AddLabelByApprovalCountCommand;
use App\PullRequestDashboard\Application\Command\MovePullRequestCardToColumnByApprovalCountCommand;
use App\Shared\Factory\CommandFactory\CommandStrategyInterface;

class PullRequestApprovedStrategy implements CommandStrategyInterface
{

    public function __construct(
        private readonly string $pullRequestDashboardNumber,
    ) {
    }

    /**
     * @param array{
     *     action: string,
     *     review: array{
     *       state: string
     *     }
     * } $payload
     */
    public function supports(string $eventType, array $payload): bool
    {
        return 'pull_request_review' === $eventType and 'submitted' === $payload['action'] and $payload['review']['state'] === 'approved';
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
     * } $payload
     * @return array<MovePullRequestCardToColumnByApprovalCountCommand|AddLabelByApprovalCountCommand>
     */
    public function createCommandsFromPayload(array $payload): array
    {
        return [
            new MovePullRequestCardToColumnByApprovalCountCommand(
                $this->pullRequestDashboardNumber,
                $payload['pull_request']['base']['repo']['owner']['login'],
                $payload['pull_request']['base']['repo']['name'],
                (string) $payload['pull_request']['number']
            ),
            new AddLabelByApprovalCountCommand(
                $payload['pull_request']['base']['repo']['owner']['login'],
                $payload['pull_request']['base']['repo']['name'],
                (string) $payload['pull_request']['number']
            )
        ];
    }
}