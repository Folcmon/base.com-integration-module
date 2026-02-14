<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Domain;

final class Order
{
    public function __construct(
        private readonly string $externalId,
        private readonly Marketplace $marketplace,
        private readonly string $status,
        private readonly \DateTimeImmutable $createdAt,
        private readonly float $totalAmount,
        private readonly string $currency,
        private readonly array $payload,
    ) {
    }

    public function externalId(): string
    {
        return $this->externalId;
    }

    public function marketplace(): Marketplace
    {
        return $this->marketplace;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function totalAmount(): float
    {
        return $this->totalAmount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function payload(): array
    {
        return $this->payload;
    }
}
