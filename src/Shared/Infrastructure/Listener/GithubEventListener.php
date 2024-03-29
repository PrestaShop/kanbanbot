<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Listener;

use App\Shared\Infrastructure\Event\GithubEvent;
use App\Shared\Infrastructure\Factory\CommandFactory\CommandFactory;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener]
class GithubEventListener
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly CommandFactory $commandFactory,
    ) {
    }

    public function __invoke(GithubEvent $event): void
    {
        $commands = $this->commandFactory->fromEventTypeAndPayload($event->eventType, $event->payload);

        foreach ($commands as $command) {
            $this->commandBus->dispatch($command);
        }
    }
}
