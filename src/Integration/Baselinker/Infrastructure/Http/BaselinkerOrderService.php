<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Http;

use App\Integration\Baselinker\Application\Query\FetchOrdersQuery;
use App\Integration\Baselinker\Domain\Exception\BaselinkerResponseException;
use App\Integration\Baselinker\Domain\Order;
use App\Integration\Baselinker\Domain\Marketplace;
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
            $this->metricsCollector?->recordOrdersImported($query->marketplace->code(), count($orders));
        }

        return $orders;
    }

    /**
     * Paginowana metoda zwracająca zamówienia filtrowane po e-mailu.
     * @return array{orders: array<Order>, meta: array<string,mixed>}
     */
    public function getOrdersByEmail(string $email, int $page = 1, int $perPage = 50): array
    {
        $parameters = [
            'search_by' => 'buyer_email',
            'search_query' => $email,
            'page' => $page,
            'limit' => $perPage,
        ];

        $response = $this->client->request('getOrders', $parameters);

        if (($response['status'] ?? 'ERROR') !== 'SUCCESS') {
            throw new BaselinkerResponseException('Baselinker API returned a non-success response for getOrdersByEmail.');
        }

        $orders = [];
        $marketplace = Marketplace::from(Marketplace::ALLEGRO); // default fallback

        foreach ($response['orders'] ?? [] as $rawOrder) {
            // try to guess marketplace from payload if available
            if (isset($rawOrder['source']) && is_string($rawOrder['source'])) {
                try {
                    $marketplace = Marketplace::from($rawOrder['source']);
                } catch (\Throwable $e) {
                    // ignore and use default
                }
            }

            $orders[] = $this->mapper->map($rawOrder, $marketplace);
        }

        $total = (int) ($response['total'] ?? count($orders));
        $hasMore = ($page * $perPage) < $total;

        return ['orders' => $orders, 'meta' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'hasMore' => $hasMore]];
    }

    /**
     * Mapuje/pobiera źródło marketplace z payloadu zamówienia Baselinker.
     */
    public function getOrderSources(array $payload): ?string
    {
        // possible fields: source, source_name, marketplace
        if (isset($payload['source']) && is_string($payload['source'])) {
            return $payload['source'];
        }

        if (isset($payload['source_name']) && is_string($payload['source_name'])) {
            return $payload['source_name'];
        }

        if (isset($payload['marketplace']) && is_string($payload['marketplace'])) {
            return $payload['marketplace'];
        }

        return null;
    }
}
