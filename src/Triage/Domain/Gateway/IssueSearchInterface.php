<?php

declare(strict_types=1);

namespace App\Triage\Domain\Gateway;

/**
 * @phpstan-type LabelledIssue array{number: int, title: string, body: string, truth: string}
 */
interface IssueSearchInterface
{
    /**
     * Closed issues carrying exactly one of the given severity labels.
     *
     * Sharded by the caller rather than fetched in one query: GitHub search
     * returns at most 1000 results, and `Minor` alone exceeds that over five
     * years, so an unsharded fetch comes back silently truncated with the
     * class balance wrong.
     *
     * @return array<int, LabelledIssue>
     *
     * @throws \App\Triage\Domain\Exception\CorpusTruncatedException when a shard holds
     *                                                               more than search will
     *                                                               return, which makes the
     *                                                               class balance wrong
     */
    public function findLabelled(string $repository, string $label, int $year): array;

    /**
     * Open issues whose titles share keywords with the given one.
     *
     * @return array<int, array{number: int, title: string}>
     */
    public function findSimilar(string $repository, string $title, int $excludeNumber): array;
}
