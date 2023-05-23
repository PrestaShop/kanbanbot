<?php

declare(strict_types=1);

namespace App\PullRequestDashboard\Infrastructure\Adapter;

use App\PullRequestDashboard\Domain\Aggregate\PullRequest;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCardId;
use App\PullRequestDashboard\Domain\Gateway\PullRequestCardRepositoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// Todo: to test and optimize
class GraphqlGithubPullRequestCardRepository implements PullRequestCardRepositoryInterface
{
    public function __construct(private readonly HttpClientInterface $githubClient)
    {
    }

    public function find(PullRequestCardId $pullRequestCardId): ?PullRequestCard
    {
        $owner = $pullRequestCardId->repositoryOwner;
        $repository = $pullRequestCardId->repositoryName;
        $pullRequestNumber = (int) $pullRequestCardId->pullRequestNumber;
        $projectNumber = (int) $pullRequestCardId->projectNumber;
        $status = 'Waiting for author';

        $prNodeID = $this->getNodeId($owner, $repository, $pullRequestNumber);
        $projectData = $this->getProjectData($owner, $projectNumber, $status);
        $itemNodeId = $this->moveItemToProjet($projectData['projectId'], $prNodeID);

        return PullRequestCard::create(
            $pullRequestCardId,
            $itemNodeId['data']['addProjectV2ItemById']['item']['fieldValueByName']['name'],
            new PullRequest($this->getApprovalCount($pullRequestCardId))
        );
    }


    public function update(PullRequestCard $pullRequestCard): void
    {
        $owner = $pullRequestCard->getId()->repositoryOwner;
        $repository = $pullRequestCard->getId()->repositoryName;
        $pullRequestNumber = (int) $pullRequestCard->getId()->pullRequestNumber;
        $projectNumber = (int) $pullRequestCard->getId()->projectNumber;
        $status = $pullRequestCard->getColumnName();

        $prNodeID = $this->getNodeId($owner, $repository, $pullRequestNumber);
        $projectData = $this->getProjectData($owner, $projectNumber, $status);
        $itemNodeId = $this->moveItemToProjet($projectData['projectId'], $prNodeID)['data']['addProjectV2ItemById']['item']['id'];

        $this->updateItemFieldValue($projectData['projectId'], $itemNodeId, $projectData['statusId'], $projectData['optionId']);
    }

    private function getNodeId(string $organization, string $project, int $pullRequestId): string
    {
        $query = <<<'QUERY'
            query($org: String!, $project: String!, $pr: Int!){
              repository(owner: $org, name: $project) {
                pullRequest(number: $pr) {
                  id
                }
              }
            }
            QUERY;

        $response = $this->githubClient->request('POST', '/graphql', [
            'json' => [
                'query' => $query,
                'variables' => [
                    'org' => $organization,
                    'project' => $project,
                    'pr' => $pullRequestId,
                ],
            ],
        ]);

        return $response->toArray()['data']['repository']['pullRequest']['id'];
    }

    private function getProjectData(string $organization, int $projectNumber, string $statusToAssign): array
    {
        $query = <<<'QUERY'
            query($org: String!, $number: Int!) {
              organization(login: $org){
                projectV2(number: $number) {
                  id
                  fields(first:20) {
                    nodes {
                      ... on ProjectV2Field {
                        id
                        name
                      }
                      ... on ProjectV2SingleSelectField {
                        id
                        name
                        options {
                          id
                          name
                        }
                      }
                    }
                  }
                }
              }
            }
        QUERY;

        $response = $this->githubClient->request('POST', '/graphql', [
            'json' => [
                'query' => $query,
                'variables' => [
                    'org' => $organization,
                    'number' => $projectNumber,
                ],
            ],
        ]);

        // Needed
        $projectId = $response->toArray()['data']['organization']['projectV2']['id'];

        $statusNodes = array_filter(
            $response->toArray()['data']['organization']['projectV2']['fields']['nodes'],
            static fn (array $nodes): bool => 'Status' === $nodes['name']
        );
        $status = reset($statusNodes);

        // Needed
        $statusId = $status['id'];

        $option = array_filter(
            $status['options'],
            static function (array $nodes) use ($statusToAssign): bool {
                return $nodes['name'] === $statusToAssign;
            }
        );

        // Needed
        $optionId = reset($option)['id'];

        return [
            'projectId' => $projectId,
            'statusId' => $statusId,
            'optionId' => $optionId,
        ];
    }

    private function moveItemToProjet($projectId, $prNodeId): array
    {
            $query = <<<'QUERY'
                mutation($project:ID!, $pr:ID!) {
                  addProjectV2ItemById(input: {projectId: $project, contentId: $pr}) {
                    item {
                      id
                      content {
                        ... on PullRequest {
                          number
                        }
                      }
                      fieldValueByName (name: "Status") {
                         ... on ProjectV2ItemFieldSingleSelectValue {
                            name
                          }
                      }
                    }
                  }
                }
            QUERY;

        $response = $this->githubClient->request('POST', '/graphql', [
            'json' => [
                'query' => $query,
                'variables' => [
                    'project' => $projectId,
                    'pr' => $prNodeId,
                ],
            ],
        ]);

        return $response->toArray();
    }

    private function updateItemFieldValue($projectId, $itemId, $statusId, $need2ndApprovalStatusId): void
    {
        $query = <<<'QUERY'
            mutation (
              $project: ID!
              $item: ID!
              $status_field: ID!
              $status_value: String!
            ) {
              set_status: updateProjectV2ItemFieldValue(input: {
                projectId: $project
                itemId: $item
                fieldId: $status_field
                value: {
                  singleSelectOptionId: $status_value
                  }
              }) {
                projectV2Item {
                  id
                  }
              }
            }
        QUERY;

        $this->githubClient->request('POST', '/graphql', [
            'json' => [
                'query' => $query,
                'variables' => [
                    'project' => $projectId,
                    'item' => $itemId,
                    'status_field' => $statusId,
                    'status_value' => $need2ndApprovalStatusId,
                ],
            ],
        ]);
    }

    private function getApprovalCount(PullRequestCardId $pullRequestCardId): int
    {
        $query = <<<'QUERY'
          query($repositoryOwner: String!, $repositoryName: String!, $pullRequestNumber: Int!) {
            repository(owner: $repositoryOwner, name: $repositoryName) {
              pullRequest(number: $pullRequestNumber) {
                id
                number
                reviews (first: 100) {
                  nodes {
                    state
                    author {
                      login
                    }
                  }
                }
              }
            }
          }
        QUERY;


        $response = $this->githubClient->request('POST', '/graphql', [
            'json' => [
                'query' => $query,
                'variables' => [
                    'repositoryOwner' => $pullRequestCardId->repositoryOwner,
                    'repositoryName' => $pullRequestCardId->repositoryName,
                    'pullRequestNumber' => (int) $pullRequestCardId->pullRequestNumber,
                ],
            ],
        ]);

        return count(
            array_filter(
                $response->toArray()['data']['repository']['pullRequest']['reviews']['nodes'],
                static fn (array $nodes): bool => 'APPROVED' === $nodes['state']
            )
        );
    }
}
