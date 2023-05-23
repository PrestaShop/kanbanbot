<?php

declare(strict_types=1);

namespace App\Shared\Factory\CommandFactory\Strategy;

use App\PullRequestDashboard\Application\Command\MovePullRequestCardToColumnByApprovalCountCommand;
use App\Shared\Factory\CommandFactory\CommandStrategyInterface;

class PullRequestApprovedStrategy/* implements CommandStrategyInterface*/
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

    public function createCommandFromPayload(array $payload): MovePullRequestCardToColumnByApprovalCountCommand
    {
        return new MovePullRequestCardToColumnByApprovalCountCommand(
            $this->pullRequestDashboardNumber,
            $payload['pull_request']['base']['repo']['owner']['login'],
            $payload['pull_request']['base']['repo']['name'],
            (string) $payload['pull_request']['number']
        );
    }
}