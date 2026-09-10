<?php

declare(strict_types=1);

namespace App\Triage\Infrastructure\Adapter;

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
     * Words that carry no signal when looking for a duplicate. Searching for
     * "the" and "when" returns the whole tracker.
     */
    private const STOPWORDS = [
        'after', 'and', 'are', 'before', 'bug', 'but', 'can', 'cant', 'does',
        'doesnt', 'error', 'for', 'from', 'has', 'have', 'into', 'issue', 'its',
        'not', 'prestashop', 'problem', 'shop', 'than', 'that', 'the', 'then',
        'there', 'this', 'was', 'were', 'when', 'with', 'you', 'your',
    ];

    public function __construct(private HttpClientInterface $githubClient)
    {
    }

    public function findLabelled(string $repository, string $label, int $year): array
    {
        $others = array_diff(['Critical', 'Major', 'Minor', 'Trivial'], [$label]);
        $exclusions = '';
        foreach ($others as $other) {
            $exclusions .= ' -label:'.$other;
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
            $items = $this->search($query, $page);
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

        $candidates = [];
        foreach ($this->search($query, 1) as $item) {
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
     * @return array<int, array<string, mixed>>
     */
    private function search(string $query, int $page): array
    {
        $response = $this->githubClient->request('GET', '/search/issues', [
            'query' => [
                'q' => $query,
                'per_page' => self::PER_PAGE,
                'page' => $page,
            ],
        ])->toArray();

        $items = $response['items'] ?? [];

        return is_array($items) ? $items : [];
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
