<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Monitoring;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Exception\MetricsRegistrationException;
use Prometheus\Histogram;
use Prometheus\Storage\Redis;

final class PrometheusMetricsCollector implements MetricsCollectorInterface
{
    private const NAMESPACE = 'baselinker';

    private ?CollectorRegistry $registry = null;
    private ?Histogram $requestDuration = null;
    private ?Counter $requestsTotal = null;
    private ?Counter $errorsTotal = null;
    private ?Counter $ordersImported = null;
    private ?Histogram $queueProcessingTime = null;
    private bool $enabled = false;

    public function __construct(Redis $redisStorage)
    {
        try {
            $this->registry = new CollectorRegistry($redisStorage);

            // API request duration in milliseconds
            $this->requestDuration = $this->registry->getOrRegisterHistogram(
                self::NAMESPACE,
                'api_request_duration_milliseconds',
                'Duration of Baselinker API requests in milliseconds',
                ['method', 'status_code'],
                [10, 50, 100, 250, 500, 1000, 2500, 5000, 10000] // buckets in ms
            );

            // Total API requests
            $this->requestsTotal = $this->registry->getOrRegisterCounter(
                self::NAMESPACE,
                'api_requests_total',
                'Total number of Baselinker API requests',
                ['method', 'status']
            );

            // API errors
            $this->errorsTotal = $this->registry->getOrRegisterCounter(
                self::NAMESPACE,
                'api_errors_total',
                'Total number of Baselinker API errors',
                ['method', 'error_type']
            );

            // Orders imported
            $this->ordersImported = $this->registry->getOrRegisterCounter(
                self::NAMESPACE,
                'orders_imported_total',
                'Total number of orders imported from marketplaces',
                ['marketplace']
            );

            // Queue processing time
            $this->queueProcessingTime = $this->registry->getOrRegisterHistogram(
                self::NAMESPACE,
                'queue_processing_duration_seconds',
                'Duration of queue message processing in seconds',
                ['handler', 'status'],
                [0.1, 0.5, 1, 2.5, 5, 10, 30, 60, 120]
            );

            $this->enabled = true;
        } catch (\Throwable $e) {
            // If Prometheus or Redis is not available, disable metrics but don't break app
            $this->enabled = false;
        }
    }

    public function recordApiRequest(string $method, float $durationMs, int $statusCode): void
    {
        if (! $this->enabled || $this->requestDuration === null || $this->requestsTotal === null) {
            return;
        }

        $status = $statusCode >= 200 && $statusCode < 300 ? 'success' : 'error';

        $this->requestDuration->observe($durationMs, [$method, (string) $statusCode]);
        $this->requestsTotal->inc([$method, $status]);
    }

    public function recordApiError(string $method, string $errorType): void
    {
        if (! $this->enabled || $this->errorsTotal === null) {
            return;
        }

        $this->errorsTotal->inc([$method, $errorType]);
    }

    public function recordOrdersImported(string $marketplace, int $count): void
    {
        if (! $this->enabled || $this->ordersImported === null) {
            return;
        }

        $this->ordersImported->incBy($count, [$marketplace]);
    }

    public function recordQueueProcessing(string $handler, float $durationSeconds, bool $success): void
    {
        if (! $this->enabled || $this->queueProcessingTime === null) {
            return;
        }

        $status = $success ? 'success' : 'failure';
        $this->queueProcessingTime->observe($durationSeconds, [$handler, $status]);
    }

    public function getRegistry(): ?CollectorRegistry
    {
        return $this->registry;
    }
}
