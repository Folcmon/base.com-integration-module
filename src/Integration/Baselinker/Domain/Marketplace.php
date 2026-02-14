<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Domain;

use InvalidArgumentException;

final class Marketplace
{
    public const ALLEGRO = 'allegro';
    public const AMAZON = 'amazon';

    private const SUPPORTED = [
        self::ALLEGRO,
        self::AMAZON,
    ];

    private function __construct(private readonly string $code)
    {
    }

    public static function from(string $code): self
    {
        $normalized = strtolower(trim($code));

        if (!in_array($normalized, self::SUPPORTED, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported marketplace "%s".', $code));
        }

        return new self($normalized);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}
