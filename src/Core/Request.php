<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private array $params = [];

    public function __construct(
        private readonly array $query,
        private readonly array $body,
        private readonly array $server,
        private readonly array $files,
    ) {
    }

    public static function capture(): self
    {
        $body = $_POST;

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw ?: '[]', true);
            if (is_array($decoded)) {
                $body = array_merge($body, $decoded);
            }
        }

        return new self($_GET, $body, $_SERVER, $_FILES);
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return rtrim($path, '/') ?: '/';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $header) {
            if (!empty($this->server[$header])) {
                $ip = explode(',', $this->server[$header])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function wantsJson(): bool
    {
        $accept = $this->server['HTTP_ACCEPT'] ?? '';
        $xrw = $this->server['HTTP_X_REQUESTED_WITH'] ?? '';
        return str_contains($accept, 'application/json') || strtolower($xrw) === 'xmlhttprequest';
    }

    public function csrfToken(): ?string
    {
        return $this->body['_csrf'] ?? $this->server['HTTP_X_CSRF_TOKEN'] ?? null;
    }
}
