<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Factory\CommandFactory\Strategy\Command;

use App\PullRequest\Application\Command\RequestChangesCommand;
use App\Shared\Infrastructure\Factory\CommandFactory\CommandStrategyInterface;

class RequestChangesStrategy implements CommandStrategyInterface
{
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
     * @return RequestChangesCommand[]
     */
    public function createCommandsFromPayload(array $payload): array
    {
        return [new RequestChangesCommand(
            $payload['pull_request']['base']['repo']['owner']['login'],
            $payload['pull_request']['base']['repo']['name'],
            (string) $payload['pull_request']['number']
        )];
    }
}
