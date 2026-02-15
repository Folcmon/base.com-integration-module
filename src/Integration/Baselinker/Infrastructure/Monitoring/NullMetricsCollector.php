<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Monitoring;

final class NullMetricsCollector implements MetricsCollectorInterface
{
    public function recordApiRequest(string $method, float $durationMs, int $statusCode): void
    {
        // noop
    }

    public function recordApiError(string $method, string $errorType): void
    {
        // noop
    }

    public function recordOrdersImported(string $marketplace, int $count): void
    {
        // noop
    }

    public function recordQueueProcessing(string $handler, float $durationSeconds, bool $success): void
    {
        // noop
    }
}

