<?php

declare(strict_types=1);

namespace App\Tests\PullRequestDashboard\Infrastructure\Adapter;

use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\Approval;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequest;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCardId;
use App\PullRequestDashboard\Infrastructure\Adapter\GraphqlGithubPullRequestCardRepository;
use App\PullRequestDashboard\Domain\Aggregate\PullRequestCard\PullRequestCard;
use App\PullRequestDashboard\Infrastructure\Adapter\RestGithubCommitterRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RestGithubCommitterRepositoryTest extends KernelTestCase
{
    public function testFindAllMethod(): void
    {
        $kernel = self::bootKernel();
        /** @var RestGithubCommitterRepository $restGithubCommitterRepository */
        $restGithubCommitterRepository = $kernel->getContainer()->get(RestGithubCommitterRepository::class);
        $expectedCommitters = [
            '0x346e3730', 'FabienPapet', 'Hlavtox', 'PululuK', 'SharakPL', 'NeOMakinG', 'atomiix', 'boherm', 'eternoendless',
            'jolelievre', 'kpodemski', 'lartist', 'marsaldev', 'matks', 'matthieu-rolland', 'mflasquin', 'mparvazi', 'nicosomb',
            'sowbiba', 'zuk3975'
        ];
        $actualCommitters = $restGithubCommitterRepository->findAll(
            new PullRequestCardId(
                projectNumber: '17',
                repositoryOwner: 'PrestaShop',
                repositoryName: 'PrestaShop',
                pullRequestNumber: '32618'
            )
        );
        sort($expectedCommitters);
        sort($actualCommitters);
        $this->assertEquals(
            $expectedCommitters,
            $actualCommitters
        );
    }
}
