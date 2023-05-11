<?php

namespace App\GraphQL;

use App\Object\Project;

class ProjectQuery extends Query
{
    /**
     * Returns project information
     *
     * @param string $organization
     * @param int $projectNumber
     * @param string $statusToAssign
     *
     * @return Project
     *
     * @throws \Exception
     */
    public function getProjectData(string $organization, int $projectNumber, string $statusToAssign): Project
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

        $response = $this->getClient()->query($query, [
            'org' => $organization,
            'number' => $projectNumber,
        ]);

        if ($response->hasErrors()) {
            throw new \Exception('Error while getting project data');
        }

        return new Project($response->getDataObject()['organization']->projectV2, $statusToAssign);
    }

    /**
     * Move an item to a project and returns the item node id
     */
    public function moveItemToProjet($projectId, $prNodeId): string
    {
        $query = <<<'QUERY'
            mutation($project:ID!, $pr:ID!) {
              addProjectV2ItemById(input: {projectId: $project, contentId: $pr}) {
                item {
                  id
                }
              }
            }
        QUERY;

        $response = $this->getClient()->query($query, [
            'project' => $projectId,
            'pr' => $prNodeId,
        ]);

        if ($response->hasErrors()) {
            throw new \Exception('Error while moving item to project');
        }

        return $response->getDataObject()['addProjectV2ItemById']->item->id;
    }

    public function updateItemFieldValue($projectId, $itemId, $statusId, $need2ndApprovalStatusId)
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

        $response = $this->getClient()->query($query, [
            'project' => $projectId,
            'item' => $itemId,
            'status_field' => $statusId,
            'status_value' => $need2ndApprovalStatusId,
        ]);

        if ($response->hasErrors()) {
            throw new \Exception('Error while updating item field value');
        }

        return true;
    }
}
