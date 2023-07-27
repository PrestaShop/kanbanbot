<?php

namespace App\Shared\Controller;

use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use App\PullRequest\Infrastructure\Adapter\RestPullRequestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthcheckController extends AbstractController
{
    public function __construct(
        private readonly RestPullRequestRepository $pullRequestRepository
    ) {
    }

    /**
     * Endpoint for monitoring team to check if our bot is working properly.
     */
    #[Route('/healthcheck', name: 'healthcheck')]
    public function healthcheck(): JsonResponse
    {
        return $this->json($this->checkBotStatus());
    }

    /**
     * Function to check if our bot is working properly, and generate response.
     *
     * @return array<string, mixed>
     */
    private function checkBotStatus(): array
    {
        // Let's check all services in used.
        // -> We can add more services if needed here.
        $checkServices = [
            'github' => $this->checkGithubAccess(),
        ];

        // Now, we build the global status.
        $status = [
            'status' => !in_array(false, array_values($checkServices)) ? 'OK' : 'KO',
            'version' => $this->getParameter('app.version'),
            'releaseDate' => null,
        ];
        foreach ($checkServices as $serviceName => $serviceStatus) {
            $status[$serviceName] = ['status' => $serviceStatus ? 'up' : 'down'];
        }

        return $status;
    }

    /**
     * Function to check if our Github access is working.
     */
    private function checkGithubAccess(): bool
    {
        try {
            $this->pullRequestRepository->find(
                new PullRequestId('PrestaShop', 'PrestaShop', '1')
            );

            return true;
        } catch (\Exception $ex) {
        }

        return false;
    }
}
