<?php

declare(strict_types=1);

namespace Kami\Rqlite\Adapters;

use CurlHandle;
use Kami\Rqlite\Http\Body;
use Kami\Rqlite\HttpAdapter;
use Kami\Rqlite\Http\Request;
use Kami\Rqlite\Http\Response;
use Kami\Rqlite\Exception\TransportException;

class Curl implements HttpAdapter
{
    public function __construct(private readonly string $baseUrl)
    {
        if (!extension_loaded('curl')) {
            throw new TransportException('The cURL extension is required to use the Curl adapter.');
        }
    }

    public function send(Request $request): Response
    {
        $curl = curl_init();
        if ($curl === false) {
            throw new TransportException('Unable to initialize cURL.');
        }

        $responseHeaders = [];
        $url = $this->buildUrl($request);
        $headers = [];

        foreach ($request->headers() as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }

        $bodyStream = fopen('php://temp', 'w+b');
        if ($bodyStream === false) {
            curl_close($curl);

            throw new TransportException('Unable to create a response body stream.');
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FILE => $bodyStream,
            CURLOPT_HEADERFUNCTION => static function (CurlHandle $curl, string $header) use (&$responseHeaders): int {
                $length = strlen($header);
                $header = trim($header);

                if ($header === '' || str_starts_with($header, 'HTTP/')) {
                    return $length;
                }

                if (str_contains($header, ':')) {
                    [$name, $value] = explode(':', $header, 2);
                    $responseHeaders[strtolower(trim($name))][] = trim($value);
                }

                return $length;
            },
        ];

        $body = $request->body();
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($curl, $options);

        try {
            $success = curl_exec($curl);

            if ($success === false) {
                $error = curl_error($curl);

                throw new TransportException(
                    $error !== '' ? $error : 'The cURL request failed.',
                    curl_errno($curl),
                );
            }

            $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        } catch (\Throwable $exception) {
            fclose($bodyStream);

            throw $exception;
        } finally {
            curl_close($curl);
        }

        rewind($bodyStream);

        return new Response(
            statusCode: $statusCode,
            headers: $this->normalizeHeaders($responseHeaders),
            body: new Body($bodyStream),
        );
    }

    /**
     * @return non-empty-string
     */
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
     * @param array<string, array<string>> $headers
     * @return array<string, string|array<string>>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $normalized[$name] = count($values) === 1 ? $values[0] : $values;
        }

        return $normalized;
    }
}
