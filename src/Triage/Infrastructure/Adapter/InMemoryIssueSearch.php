<?php

declare(strict_types=1);

namespace App\Triage\Infrastructure\Adapter;

use App\Triage\Domain\Gateway\IssueSearchInterface;

/**
 * Test double holding a fixed corpus, so handler tests never touch GitHub.
 *
 * The real search is called once per label *per year*, and returns a different
 * slice each time. This double therefore serves each label exactly once and
 * returns nothing for the following years - otherwise the handler's year loop
 * multiplies the fixture by the number of years, which is silent and makes
 * every count in a test wrong.
 */
final class InMemoryIssueSearch implements IssueSearchInterface
{
    /**
     * @var array<string, true>
     */
    private array $served = [];

    /**
     * @param array<int, array{number: int, title: string, body: string, truth: string}> $issues
     * @param array<int, array{number: int, title: string}>                              $similar
     */
    public function __construct(
        private readonly array $issues = [],
        private readonly array $similar = [],
    ) {
    }

    public function findLabelled(string $repository, string $label, int $year): array
    {
        if (isset($this->served[$label])) {
            return [];
        }
        $this->served[$label] = true;

        return array_values(array_filter(
            $this->issues,
            static fn (array $i): bool => $i['truth'] === $label
        ));
    }

    public function findSimilar(string $repository, string $title, int $excludeNumber): array
    {
        return array_values(array_filter(
            $this->similar,
            static fn (array $i): bool => $i['number'] !== $excludeNumber
        ));
    }
}
