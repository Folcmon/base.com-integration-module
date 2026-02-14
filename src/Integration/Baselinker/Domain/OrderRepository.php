<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Domain;

interface OrderRepository
{
    public function save(Order $order): void;

    /**
     * @return array<Order>
     */
    public function findByMarketplace(Marketplace $marketplace): array;
}
