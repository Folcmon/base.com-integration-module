<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Monitoring;

interface PerformanceMonitorInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function record(string $operation, float $durationMs, int $statusCode, array $context = []): void;
}
