<?php

declare(strict_types=1);

namespace App\Shared\Listener;

use App\Shared\Event\GithubEvent;
use App\Shared\Factory\CommandFactory\CommandFactory;
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

        $command = $this->commandFactory->fromEventTypeAndPayload($event->eventType, $event->payload);

        if (null !== $command) {
            $this->commandBus->dispatch($command);
        }
    }
}
