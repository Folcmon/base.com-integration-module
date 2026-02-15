<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Integration\Baselinker\Domain\Marketplace;
use App\Integration\Baselinker\Infrastructure\Http\BaselinkerOrderMapper;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BaselinkerOrderMapper::class)]
final class BaselinkerOrderMapperTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testItMapsPayloadToOrder(): void
    {
        $mapper = new BaselinkerOrderMapper();
        $marketplace = Marketplace::from(Marketplace::ALLEGRO);

        $order = $mapper->map([
            'order_id' => 123,
            'order_status' => 'paid',
            'date_add' => 1700000000,
            'payment_done' => 99.99,
            'currency' => 'PLN',
        ], $marketplace);

        self::assertSame('123', $order->externalId());
        self::assertSame('paid', $order->status());
        self::assertSame('PLN', $order->currency());
        self::assertSame(99.99, $order->totalAmount());
        self::assertSame($marketplace->code(), $order->marketplace()->code());
    }

    public function testItRejectsMissingOrderId(): void
    {
        $mapper = new BaselinkerOrderMapper();

        $this->expectException(\InvalidArgumentException::class);

        $mapper->map(['status' => 'new'], Marketplace::from(Marketplace::ALLEGRO));
    }

    public function testDetectsMarketplaceFromSourceField(): void
    {
        $mapper = new BaselinkerOrderMapper();
        $marketplace = Marketplace::from(Marketplace::ALLEGRO);

        $order = $mapper->map([
            'order_id' => 321,
            'order_status' => 'paid',
            'date_add' => 1700000000,
            'payment_done' => 10.00,
            'currency' => 'PLN',
            'source' => 'amazon',
        ], $marketplace);

        self::assertSame('321', $order->externalId());
        self::assertSame(Marketplace::AMAZON, $order->marketplace()->code());
    }
}
