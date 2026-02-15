<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Http;

use App\Integration\Baselinker\Domain\Marketplace;
use App\Integration\Baselinker\Domain\Order;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use Throwable;

final class BaselinkerOrderMapper
{
    /**
     * @param array<string, mixed> $payload
     * @throws InvalidArgumentException|Exception
     */
    public function map(array $payload, Marketplace $marketplace): Order
    {
        $externalId = (string) ($payload['order_id'] ?? $payload['id'] ?? '');
        if ($externalId === '') {
            throw new InvalidArgumentException('Baselinker order payload missing order_id.');
        }

        $createdAt = $payload['date_add'] ?? null;
        $timestamp = is_numeric($createdAt) ? (int) $createdAt : time();

        // try to detect marketplace from payload fields if present
        $detectedMarketplace = $marketplace;
        foreach (['source', 'source_name', 'marketplace'] as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                try {
                    $detectedMarketplace = Marketplace::from($payload[$field]);
                    break;
                } catch (Throwable) {
                    // ignore and continue
                }
            }
        }

        return new Order(
            $externalId,
            $detectedMarketplace,
            (string) ($payload['order_status'] ?? $payload['status'] ?? 'new'),
            new DateTimeImmutable('@'.$timestamp),
            (float) ($payload['payment_done'] ?? $payload['total'] ?? 0),
            (string) ($payload['currency'] ?? 'PLN'),
            $payload,
        );
    }
}
