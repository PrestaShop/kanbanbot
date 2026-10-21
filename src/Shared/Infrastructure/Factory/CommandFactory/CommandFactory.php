<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Factory\CommandFactory;

class CommandFactory
{
    /**
     * @param iterable<ExclusionStrategyInterface> $exclusionStrategies
     * @param iterable<CommandStrategyInterface>   $commandStrategies
     */
    public function __construct(
        private readonly iterable $exclusionStrategies,
        private readonly iterable $commandStrategies,
    ) {
    }

    /**
     * @return object[]
     */
    public function fromEventTypeAndPayload(string $eventType, string $payload): array
    {
        $payload = json_decode($payload, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \Exception('Error on json');
        }

        foreach ($this->exclusionStrategies as $exclusionStrategy) {
            /** @var array<mixed> $payload */
            if ($exclusionStrategy->isExcluded($eventType, $payload)) {
                return [];
            }
        }

        foreach ($this->commandStrategies as $commandStrategy) {
            /** @var array<mixed> $payload */
            if ($commandStrategy->supports($eventType, $payload)) {
                return $commandStrategy->createCommandsFromPayload($payload);
            }
        }

        return [];
    }
}
