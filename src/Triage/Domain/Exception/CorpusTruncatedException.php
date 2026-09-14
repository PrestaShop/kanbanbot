<?php

declare(strict_types=1);

namespace App\Triage\Domain\Exception;

/**
 * A single shard of the labelled corpus exceeded what search will return.
 *
 * Fatal rather than a warning. The year sharding exists precisely so that no
 * shard is truncated; once one is, the class balance of the corpus is wrong,
 * every rate computed from it is wrong, and nothing downstream would show it.
 */
final class CorpusTruncatedException extends \RuntimeException
{
    public function __construct(string $label, int $year, int $totalCount, int $cap)
    {
        parent::__construct(sprintf(
            'Search reports %d "%s" issues in %d but returns at most %d. '
            .'The corpus for that year is truncated and the class balance is wrong. '
            .'Shard that year more finely before trusting any number from this run.',
            $totalCount,
            $label,
            $year,
            $cap
        ));
    }
}
