<?php

declare(strict_types=1);

namespace App\Tests\PullRequest\Infrastructure\Adapter;

use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Infrastructure\Adapter\RestGithubCommitterRepository;
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
            new PullRequestId(
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
