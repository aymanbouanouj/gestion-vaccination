<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class HttpClient
{
    /** @var array<string,string> */
    private array $cookies = [];

    public function __construct(private readonly string $baseUrl)
    {
    }

    /**
     * @param array<string,string> $form
     * @param array<string,string> $headers
     */
    public function request(string $method, string $path, array $form = [], array $headers = []): HttpResponse
    {
        $responseHeaders = [];
        $curl = curl_init($this->buildUrl($path));

        $headerLines = [];
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => false,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders, &$headerLines): int {
                $headerLines[] = $line;
                $trimmed = trim($line);
                if ($trimmed === '' || !str_contains($trimmed, ':')) {
                    return strlen($line);
                }

                [$name, $value] = explode(':', $trimmed, 2);
                $normalized = strtolower(trim($name));
                $responseHeaders[$normalized] ??= [];
                $responseHeaders[$normalized][] = trim($value);

                return strlen($line);
            },
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        if ($form !== []) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($form));
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        if ($headers !== []) {
            $formattedHeaders = [];
            foreach ($headers as $name => $value) {
                $formattedHeaders[] = $name . ': ' . $value;
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, $formattedHeaders);
        }

        if ($this->cookies !== []) {
            curl_setopt($curl, CURLOPT_COOKIE, $this->cookieHeader());
        }

        $body = curl_exec($curl);
        if (!is_string($body)) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $url = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
        curl_close($curl);

        $this->storeCookies($responseHeaders['set-cookie'] ?? []);

        return new HttpResponse($statusCode, $body, $responseHeaders, $url);
    }

    public function cookieValue(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function resetCookies(): void
    {
        $this->cookies = [];
    }

    private function buildUrl(string $path): string
    {
        if (preg_match('~^https?://~i', $path) === 1) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            $parts = parse_url($this->baseUrl);
            if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
                throw new RuntimeException('Unable to resolve the HTTP client base origin.');
            }

            $origin = $parts['scheme'] . '://' . $parts['host'];
            if (isset($parts['port'])) {
                $origin .= ':' . $parts['port'];
            }

            return $origin . $path;
        }

        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function cookieHeader(): string
    {
        $pairs = [];
        foreach ($this->cookies as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }

        return implode('; ', $pairs);
    }

    /**
     * @param list<string> $setCookieHeaders
     */
    private function storeCookies(array $setCookieHeaders): void
    {
        foreach ($setCookieHeaders as $header) {
            $firstSegment = trim(explode(';', $header, 2)[0]);
            if ($firstSegment === '' || !str_contains($firstSegment, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $firstSegment, 2);
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            if ($value === 'deleted') {
                unset($this->cookies[$name]);
                continue;
            }

            $this->cookies[$name] = $value;
        }
    }
}
