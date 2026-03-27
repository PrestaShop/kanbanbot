<?php

declare(strict_types=1);

namespace App\PullRequest\Infrastructure\Adapter;

use App\PullRequest\Domain\Aggregate\PullRequest\Approval;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequest;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestDiff;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Domain\Gateway\PullRequestRepositoryInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;

class RestPullRequestRepository implements PullRequestRepositoryInterface
{
    public function __construct(
        private readonly HttpClientInterface $githubClient,
        private readonly Environment $twig,
    ) {
    }

    public function find(PullRequestId $pullRequestId): ?PullRequest
    {
        $response = $this->githubClient->request('GET', '/repos/'.$pullRequestId->repositoryOwner.'/'.$pullRequestId->repositoryName.'/pulls/'.$pullRequestId->pullRequestNumber);
        $responseArr = $response->toArray();

        return PullRequest::create(
            $pullRequestId,
            array_map(
                static function (array $label): string {
                    return $label['name'];
                },
                $responseArr['labels']
            ),
            $this->getApprovals($pullRequestId),
            $responseArr['base']['ref'],
            $responseArr['body'],
            $responseArr['milestone']['number'] ?? null,
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
        $reviews = $this->githubClient->request('GET', '/repos/'.$pullRequestId->repositoryOwner.'/'.$pullRequestId->repositoryName.'/pulls/'.$pullRequestId->pullRequestNumber.'/reviews')->toArray();

        $approvals = [];
        foreach ($reviews as $review) {
            if ('APPROVED' === $review['state']) {
                $approvals[$review['user']['login']] = new Approval($review['user']['login']);
            } elseif ('CHANGES_REQUESTED' === $review['state'] || 'DISMISSED' === $review['state']) {
                unset($approvals[$review['user']['login']]);
            }
        }

        return $approvals;
    }

    public function getDiff(PullRequestId $pullRequestId): PullRequestDiff
    {
        $diff = $this->githubClient->request(
            'GET',
            '/repos/'.$pullRequestId->repositoryOwner.'/'.$pullRequestId->repositoryName.'/pulls/'.$pullRequestId->pullRequestNumber,
            ['headers' => ['Accept' => 'application/vnd.github.v3.diff']]
        );

        return PullRequestDiff::parseDiff($pullRequestId, $diff->getContent());
    }

    /**
     * @param array<string, array<int, string|null>> $newTranslations
     * @param string[]                               $newDomains
     */
    public function addTranslationsComment(PullRequestId $pullRequestId, array $newTranslations, array $newDomains): void
    {
        // First, we need to check if the comment already exists.
        $alreadyExistComment = $this->getExistingComment($pullRequestId, '<!-- PR_WORDING -->');
        $validatedWordings = [];

        // if we have found one PR WORDING comment, in all comments:
        if ($alreadyExistComment) {
            // We need to determine if some wording is already validated with a tick.
            $matches = [];
            preg_match_all('/^- (?:\[(x| )\].*)?\s*`(.*)`((?:\s{4,}- \[.\] .*)+)/mi', $alreadyExistComment['body'], $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $wordings = [];
                preg_match_all('/^\s+- \[x\] `(.*)`/mi', $match[3], $wordings, PREG_SET_ORDER);
                foreach ($wordings as $wording) {
                    $validatedWordings[$match[2]][] = $wording[1];
                }
            }
        }

        // Then we need to format the new comment with the new translations keys.
        $comment = $this->twig->render('pr_comments/wordings.html.twig', [
            'alreadyValidatedWordings' => $validatedWordings,
            'newTranslations' => $newTranslations,
            'newDomains' => $newDomains,
        ]);

        // Finally, we need to add or edit the comment to the PR.
        if ($alreadyExistComment) {
            // Edit $t9nCommentIdExist comment
            $this->githubClient->request('PATCH', '/repos/'.$pullRequestId->repositoryOwner.'/'.$pullRequestId->repositoryName.'/issues/comments/'.$alreadyExistComment['id'], [
                'json' => ['body' => $comment],
            ]);
        } else {
            // add new comment
            $this->githubClient->request('POST', '/repos/'.$pullRequestId->repositoryOwner.'/'.$pullRequestId->repositoryName.'/issues/'.$pullRequestId->pullRequestNumber.'/comments', [
                'json' => ['body' => $comment],
            ]);
        }
    }

    public function addWelcomeComment(PullRequestId $pullRequestId, string $contributor): void
    {
        // First, we need to check if the comment already exists, and if we have already welcomed the contributor, we do nothing.
        $alreadyExistComment = $this->getExistingComment($pullRequestId, '<!-- PR_WELCOME -->');

        // If the comment does not exist, we need to add it.
        if (!$alreadyExistComment) {
            // Then we need to format the new comment with the new contributor.
            $welcomeComment = $this->twig->render('pr_comments/welcome.html.twig', [
                'contributor' => $contributor,
                'repositoryOwner' => $pullRequestId->repositoryOwner,
                'repositoryName' => $pullRequestId->repositoryName,
            ]);

            // We add the comment to the PR.
            $this->githubClient->request('POST', '/repos/'.$pullRequestId->repositoryOwner.'/'.$pullRequestId->repositoryName.'/issues/'.$pullRequestId->pullRequestNumber.'/comments', [
                'json' => ['body' => $welcomeComment],
            ]);
        }
    }

    public function addTableDescriptionErrorsComment(PullRequestId $pullRequestId, ConstraintViolationListInterface $errors, bool $isLinkedIssuesNeeded): void
    {
        // First, we need to check if the comment already exists
        $alreadyExistComment = $this->getExistingComment($pullRequestId, '<!-- PR_TABLE_DESCRIPTION_ERROR -->');

        // Then we need to format the new comment with the new translations keys.
        $comment = $this->twig->render('pr_comments/table_errors.html.twig', [
            'errors' => $errors,
            'linkedIssuesNeeded' => $isLinkedIssuesNeeded,
        ]);

        // Finally, we need to add or edit the comment to the PR.
        if ($alreadyExistComment) {
            // Edit $t9nCommentIdExist comment
            $this->githubClient->request('PATCH', '/repos/'.$pullRequestId->repositoryOwner.'/'.$pullRequestId->repositoryName.'/issues/comments/'.$alreadyExistComment['id'], [
                'json' => ['body' => $comment],
            ]);
        } else {
            // add new comment
            $this->githubClient->request('POST', '/repos/'.$pullRequestId->repositoryOwner.'/'.$pullRequestId->repositoryName.'/issues/'.$pullRequestId->pullRequestNumber.'/comments', [
                'json' => ['body' => $comment],
            ]);
        }
    }

    public function removeTableDescriptionErrorsComment(PullRequestId $pullRequestId): void
    {
        // First, we need to check if the comment already exists
        $alreadyExistComment = $this->getExistingComment($pullRequestId, '<!-- PR_TABLE_DESCRIPTION_ERROR -->');

        // If the comment exists, we remove it.
        if ($alreadyExistComment) {
            $this->githubClient->request('DELETE', '/repos/'.$pullRequestId->repositoryOwner.'/'.$pullRequestId->repositoryName.'/issues/comments/'.$alreadyExistComment['id']);
        }
    }

    public function addMissingMilestoneComment(PullRequestId $pullRequestId): void
    {
        // First, we need to check if the comment already exists
        $alreadyExistComment = $this->getExistingComment($pullRequestId, '<!-- PR_MISSING_MILESTONE -->');

        // If the comment does not exist, we need to add it.
        if (!$alreadyExistComment) {
            $comment = $this->twig->render('pr_comments/missing_milestone.html.twig');
            $this->githubClient->request('POST', '/repos/'.$pullRequestId->repositoryOwner.'/'.$pullRequestId->repositoryName.'/issues/'.$pullRequestId->pullRequestNumber.'/comments', [
                'json' => ['body' => $comment],
            ]);
        }
    }

    /**
     * @return ?array{
     *     id: string,
     *     body: string
     * }
     */
    private function getExistingComment(PullRequestId $pullRequestId, string $commentMarker): ?array
    {
        $comments = $this->githubClient->request('GET', '/repos/'.$pullRequestId->repositoryOwner.'/'.$pullRequestId->repositoryName.'/issues/'.$pullRequestId->pullRequestNumber.'/comments')->toArray();

        foreach ($comments as $comment) {
            if (str_contains($comment['body'], $commentMarker)) {
                return $comment;
            }
        }

        return null;
    }

    public function isMilestoneNeeded(PullRequestId $pullRequestId): bool
    {
        $response = $this->githubClient->request('GET', '/repos/'.$pullRequestId->repositoryOwner.'/'.$pullRequestId->repositoryName.'/milestones?state=open&per_page=1')->toArray();

        return 0 !== count($response);
    }

    public function addSecurityBranchComment(PullRequestId $pullRequestId, string $targetedBranch): void
    {
        // First, we need to check if the comment already exists
        $alreadyExistComment = $this->getExistingComment($pullRequestId, '<!-- SECURITY_PR_ONLY -->');

        // If the comment does not exist, we need to add it.
        if (!$alreadyExistComment) {
            $comment = $this->twig->render('pr_comments/security_pr_only.html.twig', [
                'targetedBranch' => $targetedBranch,
            ]);
            $this->githubClient->request('POST', '/repos/'.$pullRequestId->repositoryOwner.'/'.$pullRequestId->repositoryName.'/issues/'.$pullRequestId->pullRequestNumber.'/comments', [
                'json' => ['body' => $comment],
            ]);
        }
    }

    public function addCommunityQAComment(PullRequestId $pullRequestId): void
    {
        // First, we need to check if the comment already exists
        $alreadyExistComment = $this->getExistingComment($pullRequestId, '<!-- PR_COMMUNITY_QA -->');

        // If the comment does not exist, we need to add it.
        if (!$alreadyExistComment) {
            $comment = $this->twig->render('pr_comments/community_qa.html.twig');
            $this->githubClient->request('POST', '/repos/'.$pullRequestId->repositoryOwner.'/'.$pullRequestId->repositoryName.'/issues/'.$pullRequestId->pullRequestNumber.'/comments', [
                'json' => ['body' => $comment],
            ]);
        }
    }
}
