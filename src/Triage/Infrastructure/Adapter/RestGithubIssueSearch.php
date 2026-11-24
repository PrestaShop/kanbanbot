<?php

declare(strict_types=1);

namespace App\Triage\Infrastructure\Adapter;

use App\Triage\Domain\Aggregate\Issue\Severity;
use App\Triage\Domain\Exception\CorpusTruncatedException;
use App\Triage\Domain\Gateway\IssueSearchInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Finds issues through GitHub's REST search endpoint.
 *
 * REST rather than GraphQL here on purpose: the corpus needs only a number, a
 * title, a body and labels, and the search endpoint returns exactly that one
 * page at a time. A GraphQL search would have to page through everything
 * before returning, dragging comments and reviews along for each result.
 */
final class RestGithubIssueSearch implements IssueSearchInterface
{
    /**
     * GitHub's search endpoint caps at 100 results per page and 1000 overall.
     */
    private const PER_PAGE = 100;

    private const MAX_PAGES = 10;

    /**
     * The ceiling those two imply, and the point past which a shard is
     * silently incomplete.
     */
    private const RESULT_CAP = self::PER_PAGE * self::MAX_PAGES;

    /**
     * Search is capped at 30 requests a minute, well below the rest of the
     * REST API. A full corpus fetch is a few hundred of them, so they are
     * paced rather than left to the client's retry policy, which gives up
     * after three attempts.
     */
    private const MIN_INTERVAL_MICROSECONDS = 2_100_000;

    private ?float $lastRequestAt = null;

    /**
     * Words that carry no signal when looking for a duplicate. Searching for
     * "the" and "when" returns the whole tracker.
     */
    private const STOPWORDS = [
        'after', 'and', 'are', 'before', 'bug', 'but', 'can', 'cant', 'does',
        'doesnt', 'error', 'for', 'from', 'has', 'have', 'into', 'issue', 'its',
        'not', 'prestashop', 'problem', 'shop', 'than', 'that', 'the', 'then',
        'there', 'this', 'was', 'were', 'when', 'with', 'you', 'your',
    ];

    public function __construct(
        private readonly HttpClientInterface $githubClient,
        /** Zero in tests, where there is no real endpoint to be polite to. */
        private readonly int $minIntervalMicroseconds = self::MIN_INTERVAL_MICROSECONDS,
    ) {
    }

    public function findLabelled(string $repository, string $label, int $year): array
    {
        // Taken from the enum rather than restated: a fifth level added to
        // Severity has to start excluding the other four here too, and a
        // hardcoded list would keep working while quietly counting it twice.
        $exclusions = '';
        foreach (Severity::cases() as $other) {
            if ($other->value !== $label) {
                $exclusions .= ' -label:'.$other->value;
            }
        }

        $query = sprintf(
            'repo:%s is:issue is:closed label:%s%s created:%d-01-01..%d-12-31',
            $repository,
            $label,
            $exclusions,
            $year,
            $year
        );

        $found = [];
        for ($page = 1; $page <= self::MAX_PAGES; ++$page) {
            [$items, $totalCount] = $this->search($query, $page);
            if (1 === $page && $totalCount > self::RESULT_CAP) {
                throw new CorpusTruncatedException($label, $year, $totalCount, self::RESULT_CAP);
            }
            foreach ($items as $item) {
                $number = $this->intOf($item, 'number');
                if (null === $number) {
                    continue;
                }
                $found[] = [
                    'number' => $number,
                    'title' => $this->stringOf($item, 'title'),
                    'body' => $this->stringOf($item, 'body'),
                    'truth' => $label,
                ];
            }
            if (count($items) < self::PER_PAGE) {
                break;
            }
        }

        return $found;
    }

    public function findSimilar(string $repository, string $title, int $excludeNumber): array
    {
        $keywords = $this->titleKeywords($title);
        if (count($keywords) < 2) {
            return [];
        }

        $query = sprintf(
            'repo:%s is:issue is:open in:title %s',
            $repository,
            implode(' ', $keywords)
        );

        [$items] = $this->search($query, 1);

        $candidates = [];
        foreach ($items as $item) {
            $number = $this->intOf($item, 'number');
            if (null === $number || $number === $excludeNumber) {
                continue;
            }
            $candidates[] = ['number' => $number, 'title' => $this->stringOf($item, 'title')];
            if (count($candidates) >= 10) {
                break;
            }
        }

        return $candidates;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function intOf(array $item, string $key): ?int
    {
        $value = $item[$key] ?? null;

        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : null);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function stringOf(array $item, string $key): string
    {
        $value = $item[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function search(string $query, int $page): array
    {
        $this->pace();

        $response = $this->githubClient->request('GET', '/search/issues', [
            'query' => [
                'q' => $query,
                'per_page' => self::PER_PAGE,
                'page' => $page,
            ],
        ])->toArray();

        $items = $response['items'] ?? [];
        $totalCount = $response['total_count'] ?? 0;

        return [
            is_array($items) ? $items : [],
            is_int($totalCount) ? $totalCount : 0,
        ];
    }

    /**
     * Holds the configured gap between two search requests.
     */
    private function pace(): void
    {
        if ($this->minIntervalMicroseconds <= 0) {
            return;
        }

        if (null !== $this->lastRequestAt) {
            $elapsed = (int) ((microtime(true) - $this->lastRequestAt) * 1_000_000);
            if ($elapsed < $this->minIntervalMicroseconds) {
                usleep($this->minIntervalMicroseconds - $elapsed);
            }
        }

        $this->lastRequestAt = microtime(true);
    }

    /**
     * @return array<int, string>
     */
    private function titleKeywords(string $title, int $limit = 5): array
    {
        preg_match_all('/[A-Za-z][A-Za-z0-9_-]{2,}/', mb_strtolower($title), $matches);

        $keywords = [];
        foreach ($matches[0] as $word) {
            if (in_array($word, self::STOPWORDS, true) || in_array($word, $keywords, true)) {
                continue;
            }
            $keywords[] = $word;
            if (count($keywords) === $limit) {
                break;
            }
        }

        return $keywords;
    }
}
