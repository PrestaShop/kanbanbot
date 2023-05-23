<?php

namespace App\Shared\Factory\CommandFactory;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.shared.command_strategy')]
interface CommandStrategyInterface
{
    /**
     * @param array<mixed> $payload
     */
    public function supports(string $eventType, array $payload): bool;

    /**
     * @param array<mixed> $payload
     * @return object[]
     */
    public function createCommandsFromPayload(array $payload): array;
}