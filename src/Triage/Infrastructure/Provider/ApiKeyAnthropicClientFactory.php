<?php

declare(strict_types=1);

namespace App\Triage\Infrastructure\Provider;

use Anthropic\Client;
use Anthropic\RequestOptions;
use App\Triage\Domain\Exception\ClassificationFailedException;

/**
 * The real client, authenticated with the organisation API key.
 *
 * Retries are left to the SDK, which reads `retry-after` off a 429 instead of
 * guessing at a backoff. A second retry loop in the adapter on top of this one
 * would multiply the attempts rather than add to them.
 */
final class ApiKeyAnthropicClientFactory implements AnthropicClientFactoryInterface
{
    private const MAX_RETRIES = 3;

    private ?Client $client = null;

    public function __construct(private readonly string $anthropicApiKey)
    {
    }

    public function create(): Client
    {
        if (null === $this->client) {
            if ('' === $this->anthropicApiKey) {
                throw new ClassificationFailedException('ANTHROPIC_API_KEY is not configured');
            }

            $this->client = new Client(
                apiKey: $this->anthropicApiKey,
                requestOptions: RequestOptions::with(maxRetries: self::MAX_RETRIES),
            );
        }

        return $this->client;
    }
}
