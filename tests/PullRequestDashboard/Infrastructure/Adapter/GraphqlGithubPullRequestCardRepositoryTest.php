<?php

declare(strict_types=1);

namespace App\Tests\PullRequestDashboard\Infrastructure\Adapter;

use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\Approval;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequest;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCard;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCardId;
use App\PullRequestDashboard\Infrastructure\Adapter\GraphqlGithubPullRequestCardRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class GraphqlGithubPullRequestCardRepositoryTest extends KernelTestCase
{
    public function testUpdateMethod(): void
    {
        // Todo: this doesn't test anything. It's just a proof of concept.
        $kernel = self::bootKernel();
        /** @var GraphqlGithubPullRequestCardRepository $graphqlGithubPRRepository */
        $graphqlGithubPRRepository = $kernel->getContainer()->get(GraphqlGithubPullRequestCardRepository::class);
        $graphqlGithubPRRepository->find(new PullRequestCardId(
            projectNumber: '17',
            repositoryOwner: 'PrestaShop',
            repositoryName: 'PrestaShop',
            pullRequestNumber: '32852'
        ));

        $graphqlGithubPRRepository->update(
            PullRequestCard::create(
                new PullRequestCardId(
                    projectNumber: '17',
                    repositoryOwner: 'PrestaShop',
                    repositoryName: 'PrestaShop',
                    pullRequestNumber: '32618'
                ),
                columnName: 'Waiting for author',
                pullRequest: new PullRequest([new Approval('lartist')]),
            )
        );
        $this->assertTrue(true);
    }
}
