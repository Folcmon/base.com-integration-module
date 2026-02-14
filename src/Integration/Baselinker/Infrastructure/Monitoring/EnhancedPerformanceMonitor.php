<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Monitoring;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Enhanced performance monitor with structured logging for Graylog/ELK integration.
 * Logs in JSON format with proper context for log aggregation systems.
 */
final class EnhancedPerformanceMonitor implements PerformanceMonitorInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?MetricsCollectorInterface $metricsCollector = null,
    ) {
    }

    public function record(string $operation, float $durationMs, int $statusCode, array $context = []): void
    {
        $level = $this->determineLogLevel($statusCode, $durationMs);
        $structuredContext = $this->buildStructuredContext($operation, $durationMs, $statusCode, $context);

        $this->logger->log($level, 'Baselinker API call completed', $structuredContext);

        // Send metrics to Prometheus if available
        $this->metricsCollector?->recordApiRequest($operation, $durationMs, $statusCode);
    }

    private function determineLogLevel(int $statusCode, float $durationMs): string
    {
        // Log as error if status code indicates failure
        if ($statusCode >= 500) {
            return LogLevel::ERROR;
        }

        if ($statusCode >= 400) {
            return LogLevel::WARNING;
        }

        // Log as warning if request is slow (> 5 seconds)
        if ($durationMs > 5000) {
            return LogLevel::WARNING;
        }

        return LogLevel::INFO;
    }

    private function buildStructuredContext(string $operation, float $durationMs, int $statusCode, array $context): array
    {
        return [
            // Core metrics
            'operation' => $operation,
            'duration_ms' => round($durationMs, 2),
            'status_code' => $statusCode,
            'timestamp' => time(),
            'datetime' => date('c'),

            // Performance classification
            'is_slow' => $durationMs > 5000,
            'performance_category' => $this->categorizePerformance($durationMs),

            // Status classification
            'is_success' => $statusCode >= 200 && $statusCode < 300,
            'is_client_error' => $statusCode >= 400 && $statusCode < 500,
            'is_server_error' => $statusCode >= 500,

            // Additional context
            'service' => 'baselinker-integration',
            'integration_type' => 'api',

            // Merge custom context
            ...$context,
        ];
    }

    private function categorizePerformance(float $durationMs): string
    {
        return match (true) {
            $durationMs < 100 => 'excellent',
            $durationMs < 500 => 'good',
            $durationMs < 1000 => 'acceptable',
            $durationMs < 5000 => 'slow',
            default => 'critical',
        };
    }
}

