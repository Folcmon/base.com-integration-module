<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Http\OrderFilters;

use App\Integration\Baselinker\Application\Query\FetchOrdersQuery;
use App\Integration\Baselinker\Domain\Marketplace;

final class AllegroOrderFilter implements MarketplaceOrderFilterInterface
{
    public function supports(Marketplace $marketplace): bool
    {
        return $marketplace->code() === Marketplace::ALLEGRO;
    }

    public function buildParameters(FetchOrdersQuery $query): array
    {
        return array_filter([
            'filter_order_source' => Marketplace::ALLEGRO,
            'date_confirmed_from' => $query->from?->getTimestamp(),
            'date_confirmed_to' => $query->to?->getTimestamp(),
        ], static fn ($value) => $value !== null);
    }
}
