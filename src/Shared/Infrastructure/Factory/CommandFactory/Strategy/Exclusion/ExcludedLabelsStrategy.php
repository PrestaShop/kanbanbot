<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Factory\CommandFactory\Strategy\Exclusion;

use App\Shared\Infrastructure\Factory\CommandFactory\ExclusionStrategyInterface;

class ExcludedLabelsStrategy implements ExclusionStrategyInterface
{
    public function __construct(
        /** @var array<string> */
        private readonly array $labelsExcluded,
    ) {
    }

    /**
     * @param array{
     *     pull_request: array{
     *          labels: array{
     *              name: string,
     *          }|null,
     *     }
     * } $payload
     */
    public function isExcluded(string $eventType, array $payload): bool
    {
        $labelsOnPR = array_column($payload['pull_request']['labels'] ?? [], 'name');

        return count(array_intersect($labelsOnPR, $this->labelsExcluded)) > 0;
    }
}
