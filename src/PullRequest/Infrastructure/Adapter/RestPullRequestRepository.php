<?php

declare(strict_types=1);

namespace App\PullRequest\Infrastructure\Adapter;

use App\PullRequest\Domain\Aggregate\PullRequest\Approval;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequest;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Gateway\PullRequestRepositoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RestPullRequestRepository implements PullRequestRepositoryInterface
{
    public function __construct(private readonly HttpClientInterface $githubClient)
    {
    }

    public function find(PullRequestId $pullRequestId): ?PullRequest
    {
        $response = $this->githubClient->request('GET', '/repos/'.$pullRequestId->repositoryOwner.'/'.$pullRequestId->repositoryName.'/pulls/'.$pullRequestId->pullRequestNumber);

        return PullRequest::create(
            $pullRequestId,
            array_map(
                static function (array $label): string {
                    return $label['name'];
                },
                $response->toArray()['labels']
            ),
            $this->getApprovals($pullRequestId)
        );
    }

    public function update(PullRequest $pullRequest): void
    {
        $this->githubClient->request(
            'PATCH',
            '/repos/'.$pullRequest->getId()->repositoryOwner.'/'.$pullRequest->getId()->repositoryName.'/issues/'.$pullRequest->getId()->pullRequestNumber,
            [
                'json' => [
                    'labels' => $pullRequest->getLabels(),
                ],
            ]
        );
    }

    /**
     * @return Approval[]
     */
    public function getApprovals(PullRequestId $pullRequestId): array
    {
        $approvals = array_filter(
            $this->githubClient->request('GET', '/repos/' . $pullRequestId->repositoryOwner . '/' . $pullRequestId->repositoryName . '/pulls/' . $pullRequestId->pullRequestNumber . '/reviews')->toArray(),
            static fn(array $nodes): bool => 'APPROVED' === $nodes['state']
        );
        return array_map(
            static fn(array $nodes): Approval => new Approval($nodes['user']['login']),
            $approvals
        );
    }
}
