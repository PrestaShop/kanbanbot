<?php

namespace App\Event;

class DefaultEvent implements EventInterface
{
    public const STATUS_NEED_SECOND_REVIEW = 'Need 2nd approval';

    public const STATUS_WAITING_FOR_AUTHOR = 'Waiting for author';
    public const STATUS_WAITING_FOR_UX_PM_DEV = 'Waiting for PM/UX/Dev';

    private $status;

    /**
     * @param string $status
     */
    public function setStatus(string $status)
    {
        $this->status = $status;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function run()
    {
    }
}
