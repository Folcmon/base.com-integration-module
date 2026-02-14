<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Integration\Baselinker\Domain\Marketplace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Marketplace::class)]
final class MarketplaceTest extends TestCase
{
    public function testItNormalizesMarketplaceCode(): void
    {
        $marketplace = Marketplace::from(' AlLeGrO ');

        self::assertSame(Marketplace::ALLEGRO, $marketplace->code());
    }

    public function testItRejectsUnsupportedMarketplace(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Marketplace::from('etsy');
    }
}
