<?php

declare(strict_types=1);

namespace Tests\Support;

final class HttpResponse
{
    /**
     * @param array<string,list<string>> $headers
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        public readonly array $headers,
        public readonly string $url
    ) {
    }

    public function header(string $name): ?string
    {
        $normalized = strtolower($name);

        return $this->headers[$normalized][0] ?? null;
    }
}
