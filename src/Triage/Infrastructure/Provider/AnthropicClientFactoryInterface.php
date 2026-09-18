<?php

declare(strict_types=1);

namespace App\Triage\Infrastructure\Provider;

use Anthropic\Client;
use App\Triage\Domain\Exception\ClassificationFailedException;

/**
 * Builds the Anthropic client the classifier talks through.
 *
 * A seam rather than ceremony: the classifier's retry policy, token
 * accounting and cost arithmetic are the parts most likely to be wrong, and
 * none of them can be tested while the client is built inside the adapter.
 */
interface AnthropicClientFactoryInterface
{
    /**
     * @throws ClassificationFailedException when no API key is configured
     */
    public function create(): Client;
}
