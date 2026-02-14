<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Http;

use App\Integration\Baselinker\Application\Query\FetchOrdersQuery;
use App\Integration\Baselinker\Domain\Exception\BaselinkerResponseException;
use App\Integration\Baselinker\Domain\Order;
use App\Integration\Baselinker\Infrastructure\Http\OrderFilters\MarketplaceOrderFilterRegistry;
use App\Integration\Baselinker\Infrastructure\Monitoring\MetricsCollectorInterface;

final readonly class BaselinkerOrderService
{
    public function __construct(
        private BaselinkerClientInterface      $client,
        private BaselinkerOrderMapper          $mapper,
        private MarketplaceOrderFilterRegistry $filterRegistry,
        private ?MetricsCollectorInterface     $metricsCollector = null,
    ) {
    }

    /**
     * @return array<Order>
     */
    public function fetchOrders(FetchOrdersQuery $query): array
    {
        $filter = $this->filterRegistry->getForMarketplace($query->marketplace);
        $parameters = $filter->buildParameters($query);

        $response = $this->client->request('getOrders', $parameters);

        if (($response['status'] ?? 'ERROR') !== 'SUCCESS') {
            throw new BaselinkerResponseException('Baselinker API returned a non-success response.');
        }

        $orders = [];
        foreach ($response['orders'] ?? [] as $rawOrder) {
            $orders[] = $this->mapper->map($rawOrder, $query->marketplace);
        }

        // Track metrics for imported orders
        if (count($orders) > 0) {
            $this->metricsCollector?->recordOrdersImported($query->marketplace->value, count($orders));
        }

        return $orders;
    }
}
