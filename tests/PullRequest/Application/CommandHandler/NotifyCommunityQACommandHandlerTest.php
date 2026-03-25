<?php

declare(strict_types=1);

namespace App\Tests\PullRequest\Application\CommandHandler;

use App\PullRequest\Application\Command\NotifyCommunityQACommand;
use App\PullRequest\Application\CommandHandler\NotifyCommunityQACommandHandler;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Infrastructure\Adapter\InMemoryPullRequestRepository;
use PHPUnit\Framework\TestCase;

class NotifyCommunityQACommandHandlerTest extends TestCase
{
    private NotifyCommunityQACommandHandler $notifyCommunityQACommandHandler;
    private InMemoryPullRequestRepository $prRepository;

    protected function setUp(): void
    {
        $this->prRepository = $this->getMockBuilder(InMemoryPullRequestRepository::class)
           ->onlyMethods([
               'addCommunityQAComment',
           ])
           ->getMock();
        $this->notifyCommunityQACommandHandler = new NotifyCommunityQACommandHandler($this->prRepository);
    }

    public function testHandle(): void
    {
        $pullRequestId = new PullRequestId(
            repositoryOwner: 'PrestaShop',
            repositoryName: 'PrestaShop',
            pullRequestNumber: '12345'
        );

        // @phpstan-ignore-next-line
        $this->prRepository
            ->expects($this->once())
            ->method('addCommunityQAComment')
            ->with($pullRequestId);

        $this->notifyCommunityQACommandHandler->__invoke(new NotifyCommunityQACommand(
            repositoryOwner: $pullRequestId->repositoryOwner,
            repositoryName: $pullRequestId->repositoryName,
            pullRequestNumber: $pullRequestId->pullRequestNumber,
        ));
    }
}
