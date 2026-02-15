<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Http;

use App\Integration\Baselinker\Domain\Exception\BaselinkerResponseException;
use App\Integration\Baselinker\Infrastructure\Monitoring\PerformanceMonitorInterface;
use App\Integration\Baselinker\Infrastructure\Monitoring\MetricsCollectorInterface;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Throwable;

final readonly class BaselinkerHttpClient implements BaselinkerClientInterface
{
    public function __construct(
        private HttpClientInterface         $httpClient,
        private PerformanceMonitorInterface $performanceMonitor,
        private LoggerInterface             $logger,
        private string                      $baseUrl,
        private string                      $token,
        private ?MetricsCollectorInterface  $metricsCollector = null,
        private ?RateLimiterFactory         $rateLimiterFactory = null,
        private ?CacheInterface             $cache = null,
    ) {
    }

    /**
     * @throws RandomException
     * @throws \JsonException
     */
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

        // cache key for idempotent/listing requests
        $cacheKey = 'bl_' . md5($method . '|' . json_encode($parameters));

        // short-circuit cache if available
        if ($this->cache !== null) {
            try {
                $cached = $this->cache->get($cacheKey, function () use ($method) {
                    // cache miss -> return null
                    return null;
                });
                if ($cached !== null) {
                    // cache hit
                    return $cached;
                }
                // cache miss - continue
            } catch (Throwable $e) {
                // cache failure shouldn't block requests
                $this->logger->warning('Cache read failed for Baselinker client.', ['exception' => $e]);
            }
        }

        $start = microtime(true);
        $statusCode = 0;
        $errorType = null;

        $maxRetries = 3;
        $attempt = 0;

        do {
            $attempt++;

            // Rate limiting: consume a token if limiter configured
            if ($this->rateLimiterFactory !== null) {
                try {
                    $limiter = $this->rateLimiterFactory->create('baselinker_api');
                    $limit = $limiter->consume(1);
                    if (! $limit->isAccepted()) {
                        $this->metricsCollector?->recordApiError($method, 'rate_limited');
                        throw new BaselinkerResponseException('Rate limit exceeded for Baselinker API.');
                    }
                } catch (BaselinkerResponseException $e) {
                    throw $e;
                } catch (Throwable $e) {
                    $this->logger->warning('Rate limiter failure, proceeding without limiting.', ['exception' => $e]);
                }
            }

            try {
                $response = $this->httpClient->request('POST', $this->baseUrl, $options->toArray());
                $statusCode = $response->getStatusCode();
                $payload = $response->toArray(false);

                // treat non-success status codes
                if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
                    $errorType = 'client_error';
                    $this->metricsCollector?->recordApiError($method, $errorType);
                    $this->logger->error('Baselinker client error.', ['method' => $method, 'status' => $statusCode, 'payload' => $payload]);
                    throw new BaselinkerResponseException('Baselinker client error.', $statusCode);
                }

                if ($statusCode >= 500 || $statusCode === 429) {
                    // server or rate limit - retryable
                    $errorType = $statusCode === 429 ? 'rate_limit' : 'server_error';
                    $this->logger->warning('Baselinker temporary error, will retry.', ['method' => $method, 'status' => $statusCode]);
                    $this->metricsCollector?->recordApiError($method, $errorType);

                    // respect Retry-After header if present
                    $retryAfterHeader = $response->getHeaders(false)['retry-after'][0] ?? null;
                    if ($retryAfterHeader !== null) {
                        $delay = is_numeric($retryAfterHeader) ? (int) $retryAfterHeader : 0;
                    } else {
                        // exponential backoff with jitter (ms)
                        $baseMs = 200;
                        $delayMs = $baseMs * (2 ** ($attempt - 1));
                        $jitterMs = random_int(0, 100);
                        $delay = (int) ceil(($delayMs + $jitterMs) / 1000);
                    }

                    if ($attempt > $maxRetries) {
                        throw new BaselinkerResponseException('Baselinker service unavailable after retries.', $statusCode);
                    }

                    // sleep before retry (convert to seconds)
                    usleep($delay * 1000000);
                    continue;
                }

                // success - store in cache if cache configured
                if ($this->cache !== null) {
                    try {
                        $this->cache->delete($cacheKey); // ensure stale not kept
                        // store with short TTL (30s) by using get with callback
                        $this->cache->get($cacheKey, function () use ($payload) {
                            return $payload;
                        });
                    } catch (Throwable $e) {
                        $this->logger->warning('Cache write failed for Baselinker client.', ['exception' => $e]);
                    }
                }

                // finished successfully
                break;

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

                // retry on server/transport errors
                if ($exception instanceof ServerExceptionInterface || $exception instanceof TransportExceptionInterface) {
                    if ($attempt > $maxRetries) {
                        throw new BaselinkerResponseException('Baselinker request failed after retries.', 0, $exception);
                    }

                    $baseMs = 200;
                    $delayMs = $baseMs * (2 ** ($attempt - 1));
                    $jitterMs = random_int(0, 100);
                    usleep(((int) ceil(($delayMs + $jitterMs))) * 1000);
                    continue;
                }

                // client and redirection errors are treated as permanent
                throw new BaselinkerResponseException('Baselinker request failed.', 0, $exception);
            }

        } while ($attempt <= $maxRetries);

        try {
            $durationMs = (microtime(true) - $start) * 1000;
            $this->performanceMonitor->record($method, $durationMs, $statusCode, [
                'endpoint' => $this->baseUrl,
                'has_error' => $errorType !== null,
                'error_type' => $errorType,
                'attempts' => $attempt,
            ]);
        } catch (Throwable $e) {
            $this->logger->warning('Performance monitor failed for Baselinker client.', ['exception' => $e]);
        }

        return $payload;
    }
}
