<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\RemoteEvent;

use App\Shared\Infrastructure\Event\GithubEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[AsRemoteEventConsumer(name: 'github')]
class GithubConsumer implements ConsumerInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function consume(RemoteEvent $event): void
    {
        $payload = $event->getPayload();
        $eventType = $payload['event-type'];
        unset($payload['event-type']);

        $payload = json_encode($event->getPayload());

        if (false === $payload) {
            throw new \RuntimeException('Could not encode payload');
        }

        $this->eventDispatcher->dispatch(new GithubEvent(
            eventType: $eventType,
            payload: $payload
        ));
    }
}
