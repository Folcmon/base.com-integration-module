<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Integration\Baselinker\Infrastructure\Http\BaselinkerHttpClient;
use App\Integration\Baselinker\Infrastructure\Monitoring\NullMetricsCollector;
use App\Integration\Baselinker\Infrastructure\Monitoring\PerformanceMonitorInterface;
use App\Integration\Baselinker\Domain\Exception\BaselinkerResponseException;
use JsonException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class BaselinkerHttpClientTest extends TestCase
{
    /**
     * @throws RandomException
     * @throws JsonException
     */
    public function testSendsTokenHeaderAndParsesResponse(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $response->expects(self::once())->method('getStatusCode')->willReturn(200);
        $response->expects(self::once())->method('toArray')->with(false)->willReturn(['status' => 'SUCCESS', 'orders' => []]);

        $httpClient->expects(self::once())
            ->method('request')
            ->with('POST', 'https://api.baselinker.test', self::callback(function ($options) {
                // ensure header X-BLToken present
                return isset($options['headers']['X-BLToken']) && $options['headers']['X-BLToken'] === 'test-token';
            }))
            ->willReturn($response);

        $client = new BaselinkerHttpClient(
            $httpClient,
            $this->createMock(PerformanceMonitorInterface::class),
            $this->createMock(LoggerInterface::class),
            'https://api.baselinker.test',
            'test-token',
            new NullMetricsCollector()
        );

        $payload = $client->request('getOrders', []);

        self::assertIsArray($payload);
        self::assertSame('SUCCESS', $payload['status']);
    }

    public function testRetriesOnServerErrorAndSucceeds(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        $response500 = $this->createMock(ResponseInterface::class);
        $response500->method('getStatusCode')->willReturn(500);
        $response500->method('toArray')->willReturn(['status' => 'ERROR']);
        $response500->method('getHeaders')->willReturn(['retry-after' => ['0']]);

        $response200 = $this->createMock(ResponseInterface::class);
        $response200->method('getStatusCode')->willReturn(200);
        $response200->method('toArray')->willReturn(['status' => 'SUCCESS']);

        $httpClient->expects(self::exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($response500, $response200);

        $client = new BaselinkerHttpClient(
            $httpClient,
            $this->createMock(PerformanceMonitorInterface::class),
            $this->createMock(LoggerInterface::class),
            'https://api.baselinker.test',
            'test-token',
            new NullMetricsCollector()
        );

        $payload = $client->request('getOrders', []);

        self::assertSame('SUCCESS', $payload['status']);
    }

    public function testThrowsOnClientErrorWithoutRetry(): void
    {
        $this->expectException(BaselinkerResponseException::class);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $response400 = $this->createMock(ResponseInterface::class);
        $response400->method('getStatusCode')->willReturn(400);
        $response400->method('toArray')->willReturn(['status' => 'ERROR']);

        $httpClient->expects(self::once())->method('request')->willReturn($response400);

        $client = new BaselinkerHttpClient(
            $httpClient,
            $this->createMock(PerformanceMonitorInterface::class),
            $this->createMock(LoggerInterface::class),
            'https://api.baselinker.test',
            'test-token',
            new NullMetricsCollector()
        );

        $client->request('getOrders', []);
    }
}
