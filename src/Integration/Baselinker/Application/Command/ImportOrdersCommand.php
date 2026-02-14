<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Application\Command;

use App\Integration\Baselinker\Domain\Marketplace;
use DateTimeImmutable;

final readonly class ImportOrdersCommand
{
    public function __construct(
        public Marketplace        $marketplace,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
    ) {
    }
}
