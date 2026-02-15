<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Monitoring;

final class MetricsCollectorFactory
{
    public function __construct(private ?PrometheusMetricsCollector $prometheusCollector)
    {
    }

    public function create(): MetricsCollectorInterface
    {
        $enable = (bool) (getenv('ENABLE_PROMETHEUS') ?: false);

        if ($enable && $this->prometheusCollector !== null) {
            return $this->prometheusCollector;
        }

        return new NullMetricsCollector();
    }
}
