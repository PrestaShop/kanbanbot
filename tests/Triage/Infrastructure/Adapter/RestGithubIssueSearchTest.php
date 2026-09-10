<?php

declare(strict_types=1);

namespace App\Tests\Triage\Infrastructure\Adapter;

use App\Triage\Infrastructure\Adapter\RestGithubIssueSearch;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Exercises the adapter against a mocked transport rather than the live API.
 *
 * The other integration tests in this repository call GitHub for real, but the
 * search endpoint is rate-limited to a handful of unauthenticated requests a
 * minute and CI carries no token, so a live test here would be flaky by
 * construction. What matters is the adapter's own work anyway: the query it
 * builds, how it pages, and how it narrows an untyped payload.
 */
class RestGithubIssueSearchTest extends TestCase
{
    public function testItBuildsAQueryThatExcludesTheOtherSeverities(): void
    {
        $seen = null;
        $client = new MockHttpClient(function (string $method, string $url) use (&$seen): MockResponse {
            $seen = $url;

            return new MockResponse(json_encode(['items' => []]) ?: '');
        });

        (new RestGithubIssueSearch($client))->findLabelled('PrestaShop/PrestaShop', 'Critical', 2023);

        $this->assertIsString($seen);
        $query = urldecode($seen);
        $this->assertStringContainsString('label:Critical', $query);
        foreach (['Major', 'Minor', 'Trivial'] as $other) {
            $this->assertStringContainsString('-label:'.$other, $query, 'a single-label corpus needs the others excluded');
        }
        $this->assertStringContainsString('created:2023-01-01..2023-12-31', $query, 'sharded by year to stay under the 1000-result cap');
    }

    public function testItStopsPagingWhenAPageIsNotFull(): void
    {
        $calls = 0;
        $client = new MockHttpClient(function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse(json_encode([
                'items' => [['number' => 1, 'title' => 't', 'body' => 'b']],
            ]) ?: '');
        });

        $found = (new RestGithubIssueSearch($client))->findLabelled('x/y', 'Minor', 2023);

        $this->assertSame(1, $calls, 'a short page means the last page');
        $this->assertCount(1, $found);
        $this->assertSame('Minor', $found[0]['truth']);
    }

    public function testItSurvivesAPayloadMissingFields(): void
    {
        // A real payload is untyped: a missing title must not produce a broken
        // corpus entry, and an item with no number cannot be identified at all.
        $client = new MockHttpClient(new MockResponse(json_encode([
            'items' => [
                ['number' => 7],
                ['title' => 'no number here'],
                ['number' => 9, 'title' => 'fine', 'body' => 'ok'],
            ],
        ]) ?: ''));

        $found = (new RestGithubIssueSearch($client))->findLabelled('x/y', 'Major', 2023);

        $this->assertCount(2, $found, 'the item without a number is dropped');
        $this->assertSame(7, $found[0]['number']);
        $this->assertSame('', $found[0]['title']);
        $this->assertSame(9, $found[1]['number']);
    }

    public function testItExcludesTheIssueItIsFindingDuplicatesFor(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode([
            'items' => [
                ['number' => 42, 'title' => 'the issue itself'],
                ['number' => 43, 'title' => 'a genuine candidate'],
            ],
        ]) ?: ''));

        $candidates = (new RestGithubIssueSearch($client))
            ->findSimilar('x/y', 'Carrier price ranges display the wrong currency', 42);

        $this->assertCount(1, $candidates);
        $this->assertSame(43, $candidates[0]['number']);
    }

    public function testATitleWithoutEnoughKeywordsSearchesForNothing(): void
    {
        $called = false;
        $client = new MockHttpClient(function () use (&$called): MockResponse {
            $called = true;

            return new MockResponse('{"items":[]}');
        });

        // Only stopwords survive filtering, and searching for those returns the
        // whole tracker rather than a shortlist.
        $candidates = (new RestGithubIssueSearch($client))->findSimilar('x/y', 'the bug', 1);

        $this->assertSame([], $candidates);
        $this->assertFalse($called, 'no query is better than a worthless one');
    }
}
