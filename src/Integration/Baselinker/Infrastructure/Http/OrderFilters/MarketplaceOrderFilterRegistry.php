<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Http\OrderFilters;

use App\Integration\Baselinker\Domain\Marketplace;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class MarketplaceOrderFilterRegistry
{
    /**
     * @param iterable<MarketplaceOrderFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator('app.baselinker.marketplace_filter')]
        private iterable $filters,
    ) {
    }

    public function getForMarketplace(Marketplace $marketplace): MarketplaceOrderFilterInterface
    {
        foreach ($this->filters as $filter) {
            if ($filter->supports($marketplace)) {
                return $filter;
            }
        }

        throw new RuntimeException(sprintf('No marketplace filter registered for "%s".', $marketplace->code()));
    }
}
