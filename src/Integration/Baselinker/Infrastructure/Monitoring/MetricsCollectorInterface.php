<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Monitoring;

interface MetricsCollectorInterface
{
    public function recordApiRequest(string $method, float $durationMs, int $statusCode): void;

    public function recordApiError(string $method, string $errorType): void;

    public function recordOrdersImported(string $marketplace, int $count): void;

    public function recordQueueProcessing(string $handler, float $durationSeconds, bool $success): void;
}

