<?php

namespace App\GraphQL;

class PullRequestQuery extends Query
{
    /**
     * Returns the node id of a pull request
     *
     * @param string $organization
     * @param string $project
     * @param int $pullRequestId
     *
     * @return string
     *
     * @throws \Exception
     */
    public function getNodeId(string $organization, string $project, int $pullRequestId): string
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

        $response = $this->getClient()->query($query,
            [
                'org' => $organization,
                'project' => $project,
                'pr' => $pullRequestId,
            ]
        );

        if ($response->hasErrors()) {
            throw new \Exception('Error while getting Pull Request node ID');
        }

        return $response->getDataObject()['repository']->pullRequest->id;
    }
}
