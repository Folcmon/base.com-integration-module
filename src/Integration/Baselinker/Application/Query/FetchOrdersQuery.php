<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Application\Query;

use App\Integration\Baselinker\Domain\Marketplace;
use DateTimeImmutable;

final readonly class FetchOrdersQuery
{
    public function __construct(
        public Marketplace        $marketplace,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
    ) {
    }
}
