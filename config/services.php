<?php

use App\PullRequest\Domain\Gateway\PullRequestRepositoryInterface;
use App\PullRequest\Infrastructure\Adapter\RestPullRequestRepository;
use App\PullRequestDashboard\Domain\Gateway\PullRequestCardRepositoryInterface;
use App\PullRequestDashboard\Infrastructure\Adapter\GraphqlGithubPullRequestCardRepository;
use App\Shared\Adapter\SpyMessageBus;
use App\Shared\Factory\CommandFactory\CommandFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\MessageBusInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return function (ContainerConfigurator $configurator) {
    $configurator->parameters()
        ->set('pull_request_dashboard_number', '17')
    ;
    $services = $configurator->services();
    $services->defaults()
        ->autowire(true)
        ->autoconfigure(true)
        ->bind('$pullRequestDashboardNumber', '%pull_request_dashboard_number%')
    ;

    $services->load('App\\', '../src/')
        ->exclude([
            '../src/DependencyInjection/',
            '../src/Entity/',
            '../src/Kernel.php',
        ]);

    $services->load('App\\PullRequest\\Application\\CommandHandler\\', '../src/PullRequest/Application/CommandHandler/')
        ->tag('messenger.message_handler');

    $services->load('App\\PullRequestDashboard\\Application\\CommandHandler\\', '../src/PullRequestDashboard/Application/CommandHandler/')
        ->tag('messenger.message_handler');

    $services->alias(PullRequestRepositoryInterface::class, RestPullRequestRepository::class);
    $services->alias(PullRequestCardRepositoryInterface::class, GraphqlGithubPullRequestCardRepository::class);
    $services->set(CommandFactory::class)
        ->args([tagged_iterator('app.shared.command_strategy')])
    ;


    if ('test' === $configurator->env()) {
        $configurator->parameters()
            ->set('test_tmp_dir', '%kernel.project_dir%/var/tests/tmp')
            ->set('sandbox_pr_owner', 'PrestaShop')
            ->set('sandbox_pr_repository', 'PrestaShop')
            ->set('sandbox_pr_number', '32618')
        ;

        $services
            ->defaults()
                ->public()
                ->autowire()
        ;

        $services->set(RestPullRequestRepository::class);
        $services->set(GraphqlGithubPullRequestCardRepository::class);
        $services->alias(MessageBusInterface::class, SpyMessageBus::class);
    }
};
