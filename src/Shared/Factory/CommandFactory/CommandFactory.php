<?php

declare(strict_types=1);

namespace App\Shared\Factory\CommandFactory;

use Exception;

class CommandFactory
{
    /**
     * @param iterable<CommandStrategyInterface> $commandStrategies
     */
    public function __construct(private readonly iterable $commandStrategies)
    {
    }

    public function fromEventTypeAndPayload(string $eventType, string $payload): ?object
    {
        $payload = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error on json');
        }
        foreach ($this->commandStrategies as $commandStrategy) {
            if ($commandStrategy->supports($eventType, $payload)) {
                return $commandStrategy->createCommandFromPayload($payload);
            }
        }

        return null;
    }
}