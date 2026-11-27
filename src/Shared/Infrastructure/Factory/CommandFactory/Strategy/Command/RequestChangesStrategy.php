<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Factory\CommandFactory\Strategy\Command;

use App\PullRequest\Application\Command\AddLabelByApprovalCountCommand;
use App\PullRequest\Application\Command\RequestChangesCommand;
use App\PullRequestDashboard\Application\Command\MovePullRequestCardToColumnByLabelCommand;
use App\Shared\Infrastructure\Factory\CommandFactory\CommandStrategyInterface;

class RequestChangesStrategy implements CommandStrategyInterface
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
        return 'pull_request_review' === $eventType and 'submitted' === $payload['action'] and 'changes_requested' === $payload['review']['state'];
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
     *         number: int
     *     }
     * } $payload
     *
     * @return array<AddLabelByApprovalCountCommand|RequestChangesCommand|MovePullRequestCardToColumnByLabelCommand>
     */
    public function createCommandsFromPayload(array $payload): array
    {
        $repoOwner = $payload['pull_request']['base']['repo']['owner']['login'];
        $repoName = $payload['pull_request']['base']['repo']['name'];
        $prNumber = (string) $payload['pull_request']['number'];

        // Order matters: the approval count runs first so that RequestChanges always ends with "Waiting for QA" removed (QA KO).
        return [
            new AddLabelByApprovalCountCommand($repoOwner, $repoName, $prNumber),
            new RequestChangesCommand($repoOwner, $repoName, $prNumber),
            // GitHub sends no "labeled" event when "Waiting for author" was already there, so move the card explicitly.
            new MovePullRequestCardToColumnByLabelCommand($this->pullRequestDashboardNumber, $repoOwner, $repoName, $prNumber, 'Waiting for author'),
        ];
    }
}
