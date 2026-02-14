<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Integration\Baselinker\Application\Command\ImportOrdersCommand;
use App\Integration\Baselinker\Application\Handler\ImportOrdersCommandHandler;
use App\Integration\Baselinker\Domain\Marketplace;
use App\Integration\Baselinker\Domain\OrderRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(ImportOrdersCommandHandler::class)]
final class ImportOrdersCommandHandlerTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testItImportsOrdersIntoRepository(): void
    {
        self::bootKernel();

        $response = new MockResponse(json_encode([
            'status' => 'SUCCESS',
            'orders' => [
                [
                    'order_id' => 'A-1',
                    'order_status' => 'paid',
                    'date_add' => 1700000000,
                    'payment_done' => 120.0,
                    'currency' => 'PLN',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $mockClient = new MockHttpClient($response);
        self::getContainer()->set(HttpClientInterface::class, $mockClient);

        $handler = self::getContainer()->get(ImportOrdersCommandHandler::class);
        $repository = self::getContainer()->get(OrderRepository::class);

        $handler(new ImportOrdersCommand(Marketplace::from(Marketplace::ALLEGRO)));

        $orders = $repository->findByMarketplace(Marketplace::from(Marketplace::ALLEGRO));

        self::assertCount(1, $orders);
        self::assertSame('A-1', $orders[0]->externalId());
    }
}
