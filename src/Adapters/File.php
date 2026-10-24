<?php

declare(strict_types=1);

namespace Kami\Rqlite\Adapters;

use Kami\Rqlite\HttpAdapter;
use Kami\Rqlite\Http\Request;
use Kami\Rqlite\Http\Response;
use Kami\Rqlite\Exception\TransportException;

class File implements HttpAdapter
{
    public function __construct(private readonly string $baseUrl)
    {
        if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN) !== true) {
            throw new TransportException('The allow_url_fopen directive must be enabled to use the File adapter.');
        }
    }

    public function send(Request $request): Response
    {
        $url = $this->buildUrl($request);
        $headers = [];

        foreach ($request->headers() as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }

        $context = [
            'http' => [
                'method' => $request->method,
                'header' => $headers,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
        ];

        $body = $request->body();
        if ($body !== null) {
            $context['http']['content'] = $body;
        }

        error_clear_last();
        $responseBody = @file_get_contents($url, false, stream_context_create($context));

        if ($responseBody === false) {
            $error = error_get_last();

            throw new TransportException($error['message'] ?? 'The file transport request failed.');
        }

        /** @var list<string> $rawHeaders */
        $rawHeaders = $http_response_header;
        [$statusCode, $responseHeaders] = $this->parseHeaders($rawHeaders);

        return new Response(
            statusCode: $statusCode,
            headers: $responseHeaders,
            body: $responseBody,
        );
    }

    private function buildUrl(Request $request): string
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($request->uri, '/');
        $query = $request->query();

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /**
     * @param list<string> $rawHeaders
     * @return array{int, array<string, string|array<string>>}
     */
    private function parseHeaders(array $rawHeaders): array
    {
        $statusCode = 0;
        $headers = [];

        foreach ($rawHeaders as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                $statusCode = (int) $matches[1];
                $headers = [];

                continue;
            }

            if (str_contains($header, ':')) {
                [$name, $value] = explode(':', $header, 2);
                $headers[strtolower(trim($name))][] = trim($value);
            }
        }

        $normalized = [];

        foreach ($headers as $name => $values) {
            $normalized[$name] = count($values) === 1 ? $values[0] : $values;
        }

        return [$statusCode, $normalized];
    }
}
