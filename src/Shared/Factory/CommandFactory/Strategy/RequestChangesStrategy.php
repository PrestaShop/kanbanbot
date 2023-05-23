<?php

declare(strict_types=1);

namespace App\Shared\Factory\CommandFactory\Strategy;

use App\PullRequest\Application\Command\RequestChangesCommand;
use App\Shared\Factory\CommandFactory\CommandStrategyInterface;

//Todo: use enum
class RequestChangesStrategy implements CommandStrategyInterface
{

    /**
     * @param array{
     *     action: string,
     *     }
     * } $payload
     */
    public function supports(string $eventType, array $payload): bool
    {
        return 'pull_request_review' === $eventType and 'submitted' === $payload['action'] and $payload['review']['state'] === 'changes_requested';
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
     */
    public function createCommandFromPayload(array $payload): RequestChangesCommand
    {
        return new RequestChangesCommand(
            $payload['pull_request']['base']['repo']['owner']['login'],
            $payload['pull_request']['base']['repo']['name'],
            (string) $payload['pull_request']['number']
        );
    }
}