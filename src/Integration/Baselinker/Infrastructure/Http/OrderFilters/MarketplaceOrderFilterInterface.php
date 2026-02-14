<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Http\OrderFilters;

use App\Integration\Baselinker\Application\Query\FetchOrdersQuery;
use App\Integration\Baselinker\Domain\Marketplace;

interface MarketplaceOrderFilterInterface
{
    public function supports(Marketplace $marketplace): bool;

    /**
     * @return array<string, mixed>
     */
    public function buildParameters(FetchOrdersQuery $query): array;
}
