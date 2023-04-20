<?php

namespace App\Command;

use App\Event\NeedSecondReviewEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'kanbanbot:need-second-review')]
class NeedSecondReviewCommand extends Command
{
    protected $event;

    public function __construct(NeedSecondReviewEvent $event)
    {
        parent::__construct();
        $this->event = $event;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->event->run();

        return Command::SUCCESS;
    }
}