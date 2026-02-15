<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\Application\Dto;

final readonly class BaselinkerDeepLinkDto
{
    public function __construct(private string $url)
    {
    }

    public function url(): string
    {
        return $this->url;
    }

    public function toArray(): array
    {
        return ['url' => $this->url];
    }
}

