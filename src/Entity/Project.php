<?php

declare(strict_types=1);

namespace App\Entity;

use Softonic\GraphQL\DataObjects\Query\Item;

class Project
{
    private $id;
    private $statusId;
    private $needSecondReviewStatusId;

    public function __construct(Item $graphQLObject, $statusToAssign)
    {
        $nodes = $graphQLObject->fields->nodes;
        $status = $nodes->filter(['name' => 'Status']);
        $options = $status->options;

        $this->id = $graphQLObject->id;
        $this->statusId = $status->id[0];
        $this->needSecondReviewStatusId = $options->filter(['name' => $statusToAssign])->id[0];
    }

    public function getId()
    {
        return $this->id;
    }

    public function getStatusId()
    {
        return $this->statusId;
    }

    public function getNeedSecondReviewStatusId()
    {
        return $this->needSecondReviewStatusId;
    }
}
