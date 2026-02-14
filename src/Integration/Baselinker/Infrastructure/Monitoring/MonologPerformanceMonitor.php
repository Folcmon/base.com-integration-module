<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Monitoring;

use Psr\Log\LoggerInterface;

final readonly class MonologPerformanceMonitor implements PerformanceMonitorInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function record(string $operation, float $durationMs, int $statusCode, array $context = []): void
    {
        $this->logger->info('Baselinker API call completed.', array_merge($context, [
            'operation' => $operation,
            'duration_ms' => round($durationMs, 2),
            'status_code' => $statusCode,
        ]));
    }
}
