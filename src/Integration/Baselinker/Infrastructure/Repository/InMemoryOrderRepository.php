<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Repository;

use App\Integration\Baselinker\Domain\Marketplace;
use App\Integration\Baselinker\Domain\Order;
use App\Integration\Baselinker\Domain\OrderRepository;

final class InMemoryOrderRepository implements OrderRepository
{
    /**
     * @var array<string, Order>
     */
    private array $orders = [];

    public function save(Order $order): void
    {
        $key = sprintf('%s:%s', $order->marketplace()->code(), $order->externalId());
        $this->orders[$key] = $order;
    }

    public function findByMarketplace(Marketplace $marketplace): array
    {
        return array_values(array_filter(
            $this->orders,
            static fn (Order $order) => $order->marketplace()->equals($marketplace)
        ));
    }
}
