<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Factory\CommandFactory\Strategy\Command;

use App\PullRequest\Application\Command\CheckTranslationsCommand;
use App\Shared\Infrastructure\Factory\CommandFactory\CommandStrategyInterface;

class PullRequestSynchronizeStrategy implements CommandStrategyInterface
{
    /**
     * @param array{
     *     action: string,
     *     pull_request: array{
     *          state: string
     *     }
     * } $payload
     */
    public function supports(string $eventType, array $payload): bool
    {
        return 'pull_request' === $eventType and 'synchronize' === $payload['action'] and 'open' === $payload['pull_request']['state'];
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
     *
     * @return array<CheckTranslationsCommand>
     */
    public function createCommandsFromPayload(array $payload): array
    {
        $repoOwner = $payload['pull_request']['base']['repo']['owner']['login'];
        $repoName = $payload['pull_request']['base']['repo']['name'];
        $prNumber = (string) $payload['pull_request']['number'];

        return [
            new CheckTranslationsCommand(
                $repoOwner,
                $repoName,
                $prNumber
            ),
        ];
    }
}
