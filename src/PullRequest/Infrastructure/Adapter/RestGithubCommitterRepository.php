<?php

declare(strict_types=1);

namespace App\PullRequest\Infrastructure\Adapter;

use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Gateway\CommitterRepositoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RestGithubCommitterRepository implements CommitterRepositoryInterface
{
    public function __construct(private readonly HttpClientInterface $githubClient)
    {
    }

    public function findAll(PullRequestId $pullRequestCardId): array
    {
        return array_map(
            fn(array $committer) => $committer['login'],
            $this->githubClient->request('GET', '/orgs/'.$pullRequestCardId->repositoryOwner.'/teams/committers/members')->toArray()
        );
    }

}
