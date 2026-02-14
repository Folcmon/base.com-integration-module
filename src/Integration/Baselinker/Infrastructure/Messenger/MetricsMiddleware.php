<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Messenger;

use App\Integration\Baselinker\Infrastructure\Monitoring\MetricsCollectorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Middleware that tracks queue processing metrics for Prometheus/Grafana.
 */
final class MetricsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly MetricsCollectorInterface $metricsCollector,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // Only track metrics for messages being consumed (not when dispatching)
        if (null === $envelope->last(ReceivedStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        $message = $envelope->getMessage();
        $handlerName = $this->getHandlerName($message);
        $start = microtime(true);
        $success = false;

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
            $success = true;

            return $envelope;
        } catch (\Throwable $exception) {
            $this->logger->error('Message processing failed', [
                'handler' => $handlerName,
                'message_class' => get_class($message),
                'exception' => $exception->getMessage(),
                'exception_class' => get_class($exception),
            ]);

            throw $exception;
        } finally {
            $duration = microtime(true) - $start;
            $this->metricsCollector->recordQueueProcessing($handlerName, $duration, $success);

            $this->logger->info('Message processed', [
                'handler' => $handlerName,
                'message_class' => get_class($message),
                'duration_seconds' => round($duration, 3),
                'success' => $success,
            ]);
        }
    }

    private function getHandlerName(object $message): string
    {
        $className = get_class($message);
        $parts = explode('\\', $className);

        return end($parts);
    }
}

