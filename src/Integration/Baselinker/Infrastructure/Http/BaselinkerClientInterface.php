<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Infrastructure\Http;

interface BaselinkerClientInterface
{
    /**
     * @return array<string, mixed>
     */
    public function request(string $method, array $parameters): array;
}
