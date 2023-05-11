<?php

namespace App\Event;

use App\GraphQL\ProjectQuery;
use App\GraphQL\PullRequestQuery;
use App\Object\Project;

class MoveAndAssignLabelEvent extends DefaultEvent
{
    // Need to move these variables elsewhere
    public const ORGANIZATION = 'PrestaShop';
    public const PROJECT = 'PrestaShop';
    public const PR = 32162;
    public const PROJECT_NUMBER = 17;

    private $pullRequest;
    private $project;

    public function __construct(PullRequestQuery $pullRequest, ProjectQuery $project)
    {
        $this->pullRequest = $pullRequest;
        $this->project = $project;
    }

    protected function getPullRequestId(): string
    {
        return $this->pullRequest->getNodeId(self::ORGANIZATION, self::PROJECT, self::PR); // getenv('PR_ID'),
    }

    protected function getProjectData(): Project
    {
        return $this->project->getProjectData(self::ORGANIZATION, self::PROJECT_NUMBER, $this->getStatus());
    }

    protected function movePullRequestToProject($projectId, $prNodeId)
    {
        return $this->project->moveItemToProjet($projectId, $prNodeId);
    }

    protected function assignStatusToPullRequest($projectId, $itemNodeId, $statusId, $need2ndApprovalStatusId)
    {
        return $this->project->updateItemFieldValue($projectId, $itemNodeId, $statusId, $need2ndApprovalStatusId);
    }

    public function run()
    {
        $prNodeID = $this->getPullRequestId();
        $projectData = $this->getProjectData();
        $itemNodeId = $this->movePullRequestToProject($projectData->getId(), $prNodeID);

        return $this->assignStatusToPullRequest($projectData->getId(), $itemNodeId, $projectData->getStatusId(), $projectData->getNeedSecondReviewStatusId());
    }
}
