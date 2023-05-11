<?php

declare(strict_types=1);

namespace App\Command;

use App\Event\DefaultEvent;
use App\Event\MoveAndAssignLabelEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'kanbanbot:waiting-for-ux-pm-dev')]
class WaitingForUxPmDevCommand extends Command
{
    protected $event;

    public function __construct(MoveAndAssignLabelEvent $event)
    {
        parent::__construct();
        $this->event = $event;
        $this->event->setStatus(DefaultEvent::STATUS_WAITING_FOR_UX_PM_DEV);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->event->run();

        return Command::SUCCESS;
    }
}
