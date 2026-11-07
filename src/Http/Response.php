<?php

declare(strict_types=1);

namespace Kami\Rqlite\Http;

final readonly class Response
{
    public Body $body;

    /**
     * @param array<string, string|array<string>> $headers
     */
    public function __construct(
        public int $statusCode,
        public array $headers = [],
        string|Body $body = '',
    ) {
        $this->body = is_string($body) ? Body::fromString($body) : $body;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $header => $value) {
            if (strcasecmp($header, $name) !== 0) {
                continue;
            }

            return is_array($value) ? implode(', ', $value) : $value;
        }

        return null;
    }

    /**
     * Read the remaining response body as a string.
     */
    public function text(): string
    {
        return $this->body->contents();
    }
}
