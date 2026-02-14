<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Http;

use App\Integration\Baselinker\Domain\Exception\BaselinkerResponseException;
use App\Integration\Baselinker\Infrastructure\Monitoring\PerformanceMonitorInterface;
use App\Integration\Baselinker\Infrastructure\Monitoring\MetricsCollectorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class BaselinkerHttpClient implements BaselinkerClientInterface
{
    public function __construct(
        private HttpClientInterface         $httpClient,
        private PerformanceMonitorInterface $performanceMonitor,
        private LoggerInterface             $logger,
        private string                      $baseUrl,
        private string                      $token,
        private ?MetricsCollectorInterface  $metricsCollector = null,
    ) {
    }

    public function request(string $method, array $parameters): array
    {
        $options = (new HttpOptions())
            ->setHeaders([
                'X-BLToken' => $this->token,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])
            ->setBody(http_build_query([
                'method' => $method,
                'parameters' => json_encode($parameters, JSON_THROW_ON_ERROR),
            ]));

        $start = microtime(true);
        $statusCode = 0;
        $errorType = null;

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl, $options->toArray());
            $statusCode = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (DecodingExceptionInterface $exception) {
            $errorType = 'decoding_error';
            $this->logger->error('Baselinker response decoding failed.', [
                'method' => $method,
                'exception' => $exception,
                'error_type' => $errorType,
            ]);
            $this->metricsCollector?->recordApiError($method, $errorType);
            throw new BaselinkerResponseException('Baselinker response decoding failed.', 0, $exception);
        } catch (ClientExceptionInterface | RedirectionExceptionInterface | ServerExceptionInterface | TransportExceptionInterface $exception) {
            $errorType = match (true) {
                $exception instanceof ClientExceptionInterface => 'client_error',
                $exception instanceof ServerExceptionInterface => 'server_error',
                $exception instanceof RedirectionExceptionInterface => 'redirection_error',
                default => 'transport_error',
            };

            $this->logger->error('Baselinker request failed.', [
                'method' => $method,
                'exception' => $exception,
                'error_type' => $errorType,
            ]);
            $this->metricsCollector?->recordApiError($method, $errorType);
            throw new BaselinkerResponseException('Baselinker request failed.', 0, $exception);
        } finally {
            $durationMs = (microtime(true) - $start) * 1000;
            $this->performanceMonitor->record($method, $durationMs, $statusCode, [
                'endpoint' => $this->baseUrl,
                'has_error' => $errorType !== null,
                'error_type' => $errorType,
            ]);
        }

        return $payload;
    }
}
