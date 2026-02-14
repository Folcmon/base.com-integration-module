<?php

declare(strict_types=1);

namespace App\Controller;

use App\Integration\Baselinker\Infrastructure\Monitoring\PrometheusMetricsCollector;
use Prometheus\RenderTextFormat;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Exposes Prometheus metrics endpoint for Grafana scraping.
 */
final class MetricsController extends AbstractController
{
    public function __construct(
        private readonly PrometheusMetricsCollector $metricsCollector,
    ) {
    }

    #[Route('/metrics', name: 'metrics', methods: ['GET'])]
    public function metrics(): Response
    {
        $renderer = new RenderTextFormat();
        $result = $renderer->render($this->metricsCollector->getRegistry()->getMetricFamilySamples());

        return new Response($result, 200, [
            'Content-Type' => RenderTextFormat::MIME_TYPE,
        ]);
    }
}

