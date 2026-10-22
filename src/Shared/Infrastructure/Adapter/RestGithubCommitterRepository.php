<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Adapter;

use App\Shared\Domain\Gateway\CommitterRepositoryInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RestGithubCommitterRepository implements CommitterRepositoryInterface
{
    public function __construct(
        private readonly HttpClientInterface $githubClient,
        private readonly CacheInterface $cache,
    ) {
    }

    public function findAll(string $organisation): array
    {
        return array_map(
            fn (array $committer) => $committer['login'],
            $this->githubClient->request('GET', '/orgs/'.$organisation.'/teams/committers/members')->toArray()
        );
    }

    public function isNewContributor(string $owner, string $repo, string $committer): bool
    {
        return $this->cache->get(
            'isNewContributor.'.$owner.'.'.$repo.'.'.$committer,
            function (ItemInterface $item) use ($owner, $repo, $committer) {
                $item->expiresAfter(60 * 60 * 6); // cache for 6 hours

                $lastCommit = $this->githubClient->request('GET', '/repos/'.$owner.'/'.$repo.'/commits',
                    [
                        'query' => [
                            'author' => $committer,
                            'per_page' => 1,
                        ],
                    ]
                )->toArray();

                return 0 === count($lastCommit);
            }
        );
    }
}
