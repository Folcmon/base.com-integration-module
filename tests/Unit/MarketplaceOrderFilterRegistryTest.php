<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Integration\Baselinker\Application\Query\FetchOrdersQuery;
use App\Integration\Baselinker\Domain\Marketplace;
use App\Integration\Baselinker\Infrastructure\Http\OrderFilters\MarketplaceOrderFilterInterface;
use App\Integration\Baselinker\Infrastructure\Http\OrderFilters\MarketplaceOrderFilterRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarketplaceOrderFilterRegistry::class)]
final class MarketplaceOrderFilterRegistryTest extends TestCase
{
    public function testItResolvesMatchingFilter(): void
    {
        $registry = new MarketplaceOrderFilterRegistry([
            new TestFilter(Marketplace::ALLEGRO),
            new TestFilter(Marketplace::AMAZON),
        ]);

        $filter = $registry->getForMarketplace(Marketplace::from(Marketplace::AMAZON));

        self::assertInstanceOf(TestFilter::class, $filter);
        self::assertSame(Marketplace::AMAZON, $filter->marketplaceCode());
    }

    public function testItThrowsWhenNoFilter(): void
    {
        $this->expectException(\RuntimeException::class);

        $registry = new MarketplaceOrderFilterRegistry([new TestFilter(Marketplace::ALLEGRO)]);
        $registry->getForMarketplace(Marketplace::from(Marketplace::AMAZON));
    }
}

final class TestFilter implements MarketplaceOrderFilterInterface
{
    public function __construct(private readonly string $marketplaceCode)
    {
    }

    public function supports(Marketplace $marketplace): bool
    {
        return $marketplace->code() === $this->marketplaceCode;
    }

    public function buildParameters(FetchOrdersQuery $query): array
    {
        return [];
    }

    public function marketplaceCode(): string
    {
        return $this->marketplaceCode;
    }
}
