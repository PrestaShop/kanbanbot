<?php

declare(strict_types=1);

namespace App\Shared\Factory\CommandFactory;

class CommandFactory
{
    /**
     * @param iterable<CommandStrategyInterface> $commandStrategies
     */
    public function __construct(private readonly iterable $commandStrategies)
    {
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

        foreach ($this->commandStrategies as $commandStrategy) {
            /** @var array<mixed> $payload */
            if ($commandStrategy->supports($eventType, $payload)) {
                return $commandStrategy->createCommandsFromPayload($payload);
            }
        }

        return [];
    }
}
