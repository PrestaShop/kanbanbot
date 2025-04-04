<?php

use App\PullRequest\Domain\Gateway\PullRequestRepositoryInterface;
use App\PullRequest\Infrastructure\Adapter\RestPullRequestRepository;
use App\PullRequestDashboard\Domain\Gateway\PullRequestCardRepositoryInterface;
use App\PullRequestDashboard\Infrastructure\Adapter\GraphqlGithubPullRequestCardRepository;
use App\Shared\Domain\Gateway\CommitterRepositoryInterface;
use App\Shared\Infrastructure\Adapter\RestGithubCommitterRepository;
use App\Shared\Infrastructure\Adapter\SpyMessageBus;
use App\Shared\Infrastructure\Factory\CommandFactory\CommandFactory;
use App\Shared\Infrastructure\Provider\TranslationsCatalogInterface;
use App\Shared\Infrastructure\Provider\TranslationsCatalogProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\MessageBusInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return function (ContainerConfigurator $configurator) {
    $configurator->parameters()
        ->set('app.version', '1.10.1')
        ->set('pull_request_dashboard_number', '17')
        ->set('columns.ready_for_review', 'Ready for review')
        ->set('columns.reopened', 'Reopened')
        ->set('columns.closed', 'Closed')
        ->set('columns.merged', 'Merged')
        ->set('repo.excluded', [
            'docs',
            'devdocs-site',
            'ps-org-theme',
            'example-modules',
            'ps-docs-theme',
            'open-source',
        ])
        ->set('labels.excluded', ['TE', 'E2E Tests'])
    ;
    $services = $configurator->services();
    $services->defaults()
        ->autowire(true)
        ->autoconfigure(true)
        ->bind('$pullRequestDashboardNumber', '%pull_request_dashboard_number%')
        ->bind('$readyForReviewColumnName', '%columns.ready_for_review%')
        ->bind('$reopenedColumnName', '%columns.reopened%')
        ->bind('$closedColumnName', '%columns.closed%')
        ->bind('$mergedColumnName', '%columns.merged%')
        ->bind('$webhookSecret', '%env(WEBHOOK_SECRET)%')
        ->bind('$appVersion', '%app.version%')
        ->bind('$repoExcluded', '%repo.excluded%')
        ->bind('$labelsExcluded', '%labels.excluded%')
    ;

    $services->load('App\\', '../src/')
        ->exclude([
            '../src/DependencyInjection/',
            '../src/Entity/',
            '../src/Kernel.php',
        ]);

    // Note: The config about messenger.message_handler is not tested, be careful when you want to modify it
    $services->load('App\\PullRequest\\Application\\CommandHandler\\', '../src/PullRequest/Application/CommandHandler/')
        ->tag('messenger.message_handler');

    $services->load('App\\PullRequestDashboard\\Application\\CommandHandler\\', '../src/PullRequestDashboard/Application/CommandHandler/')
        ->tag('messenger.message_handler');

    $services->alias(PullRequestRepositoryInterface::class, RestPullRequestRepository::class);
    $services->alias(PullRequestCardRepositoryInterface::class, GraphqlGithubPullRequestCardRepository::class);
    $services->alias(CommitterRepositoryInterface::class, RestGithubCommitterRepository::class);
    $services->alias(TranslationsCatalogInterface::class, TranslationsCatalogProvider::class);
    $services->set(CommandFactory::class)
        ->args([
            tagged_iterator('app.shared.exclusion_strategy'),
            tagged_iterator('app.shared.command_strategy'),
        ])
    ;

    if ('test' === $configurator->env()) {
        $configurator->parameters()
            ->set('test_tmp_dir', '%kernel.project_dir%/var/tests/tmp')
            ->set('sandbox_pr_owner', 'PrestaShop')
            ->set('sandbox_pr_repository', 'PrestaShop')
            ->set('sandbox_pr_number', '32852')
        ;

        $services
            ->defaults()
                ->public()
                ->autowire()
        ;

        $services->set(RestPullRequestRepository::class);
        $services->set(GraphqlGithubPullRequestCardRepository::class);
        $services->set(RestGithubCommitterRepository::class);
        $services->alias(MessageBusInterface::class, SpyMessageBus::class);
        $services->alias(TranslationsCatalogInterface::class, TranslationsCatalogProvider::class);
    }
};
