<?php

declare(strict_types=1);

namespace Kami\Rqlite\Http;

use InvalidArgumentException;

final class Request
{
    /** @var non-empty-string */
    public readonly string $method;

    /** @var non-empty-string */
    public readonly string $uri;

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, string> $headers
     * @param array<mixed>|null $json
     */
    public function __construct(
        string $method,
        string $uri,
        private readonly array $query = [],
        private readonly array $headers = [],
        public readonly ?array $json = null,
        public readonly ?string $rawBody = null,
    ) {
        if ($method === '') {
            throw new InvalidArgumentException('The request method cannot be empty.');
        }

        if ($uri === '') {
            throw new InvalidArgumentException('The request URI cannot be empty.');
        }

        if ($json !== null && $rawBody !== null) {
            throw new InvalidArgumentException('A request cannot contain both a JSON payload and a raw body.');
        }

        $this->method = $method;
        $this->uri = $uri;
    }

    /**
     * Return only the query parameters that have a value.
     *
     * `false` is omitted because rqlite treats the presence of a boolean flag
     * (for example `timings` or `transaction`) as true, regardless of its value.
     *
     * @return array<string, scalar>
     */
    public function query(): array
    {
        $query = [];

        foreach ($this->query as $key => $value) {
            if ($value !== null && $value !== false) {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    /**
     * Return the request headers including the defaults for JSON requests.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        $headers = $this->headers;

        if ($this->json !== null && !$this->hasHeader('Content-Type')) {
            $headers['Content-Type'] = 'application/json';
        }

        if (!$this->hasHeader('Accept')) {
            $headers['Accept'] = 'application/json';
        }

        return $headers;
    }

    public function body(): ?string
    {
        if ($this->json !== null) {
            return json_encode($this->json, flags: JSON_THROW_ON_ERROR);
        }

        return $this->rawBody;
    }

    private function hasHeader(string $name): bool
    {
        foreach (array_keys($this->headers) as $header) {
            if (strcasecmp($header, $name) === 0) {
                return true;
            }
        }

        return false;
    }
}
