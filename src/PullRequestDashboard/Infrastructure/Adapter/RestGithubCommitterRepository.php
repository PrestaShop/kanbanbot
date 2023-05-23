<?php

declare(strict_types=1);

namespace App\PullRequestDashboard\Infrastructure\Adapter;

use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\Approval;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequest;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCard;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCardId;
use App\PullRequestDashboard\Domain\Gateway\CommitterRepositoryInterface;
use App\PullRequestDashboard\Domain\Gateway\PullRequestCardRepositoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// Todo: to test
class RestGithubCommitterRepository implements CommitterRepositoryInterface
{
    public function __construct(private readonly HttpClientInterface $githubClient)
    {
    }

    public function findAll(PullRequestCardId $pullRequestCardId): array
    {
        return array_map(
            fn(array $committer) => $committer['login'],
            $this->githubClient->request('GET', '/orgs/'.$pullRequestCardId->repositoryOwner.'/teams/committers/members')->toArray()
        );
    }

}
