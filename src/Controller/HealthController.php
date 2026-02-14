<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\DBAL\Connection;

/**
 * Health check endpoint for Kubernetes probes (liveness and readiness).
 */
final class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function liveness(): JsonResponse
    {
        // Liveness probe - checks if app is running
        return new JsonResponse([
            'status' => 'healthy',
            'service' => 'baselinker-integration',
            'timestamp' => time(),
        ]);
    }

    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    public function readiness(): JsonResponse
    {
        // Readiness probe - checks if app is ready to serve traffic
        $checks = [
            'database' => $this->checkDatabase(),
        ];

        $allHealthy = !in_array(false, $checks, true);

        return new JsonResponse([
            'status' => $allHealthy ? 'ready' : 'not_ready',
            'service' => 'baselinker-integration',
            'checks' => $checks,
            'timestamp' => time(),
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            $this->connection->fetchOne('SELECT 1');
            return true;
        } catch (\Exception) {
            return false;
        }
    }
}

