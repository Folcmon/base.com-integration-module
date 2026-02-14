<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Application\Handler;

use App\Integration\Baselinker\Application\Query\FetchOrdersQuery;
use App\Integration\Baselinker\Domain\Order;
use App\Integration\Baselinker\Infrastructure\Http\BaselinkerOrderService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FetchOrdersQueryHandler
{
    public function __construct(private BaselinkerOrderService $orderService)
    {
    }

    /**
     * @return array<Order>
     */
    public function __invoke(FetchOrdersQuery $query): array
    {
        return $this->orderService->fetchOrders($query);
    }
}
