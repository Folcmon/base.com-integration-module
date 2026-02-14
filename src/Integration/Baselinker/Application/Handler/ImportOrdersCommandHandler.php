<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Application\Handler;

use App\Integration\Baselinker\Application\Command\ImportOrdersCommand;
use App\Integration\Baselinker\Application\Query\FetchOrdersQuery;
use App\Integration\Baselinker\Domain\OrderRepository;
use App\Integration\Baselinker\Infrastructure\Http\BaselinkerOrderService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ImportOrdersCommandHandler
{
    public function __construct(
        private BaselinkerOrderService $orderService,
        private OrderRepository        $orderRepository,
    ) {
    }

    public function __invoke(ImportOrdersCommand $command): void
    {
        $orders = $this->orderService->fetchOrders(
            new FetchOrdersQuery($command->marketplace, $command->from, $command->to)
        );

        foreach ($orders as $order) {
            $this->orderRepository->save($order);
        }
    }
}
