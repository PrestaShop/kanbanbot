<?php

declare(strict_types=1);

namespace App\Event;

use App\GraphQL\ProjectQuery;
use App\GraphQL\PullRequestQuery;
use App\Entity\Project;

class MoveAndAssignLabelEvent extends DefaultEvent
{
    private $pullRequest;
    private $project;

    public function __construct(PullRequestQuery $pullRequest, ProjectQuery $project)
    {
        $this->pullRequest = $pullRequest;
        $this->project = $project;
    }

    protected function getPullRequestId(): string
    {
        return $this->pullRequest->getNodeId(getenv('ORGANIZATION'), getenv('PROJECT'), intval(getenv('PR_ID')));
    }

    protected function getProjectData(): Project
    {
        return $this->project->getProjectData(getenv('ORGANIZATION'), intval(getenv('PROJECT_NUMBER')), $this->getStatus());
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
