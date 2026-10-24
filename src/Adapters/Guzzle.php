<?php

declare(strict_types=1);

namespace Kami\Rqlite\Adapters;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Kami\Rqlite\HttpAdapter;
use Kami\Rqlite\Http\Request;
use Kami\Rqlite\Http\Response;
use Kami\Rqlite\Exception\TransportException;

class Guzzle implements HttpAdapter
{
    /**
     * @param ClientInterface $client A client configured with the rqlite base URI.
     */
    public function __construct(private readonly ClientInterface $client)
    {
    }

    public function send(Request $request): Response
    {
        $options = [
            'headers' => $request->headers(),
            'query' => $request->query(),
            'http_errors' => false,
            'allow_redirects' => false,
        ];

        if ($request->json !== null) {
            $options['json'] = $request->json;
        } elseif ($request->rawBody !== null) {
            $options['body'] = $request->rawBody;
        }

        try {
            $response = $this->client->request($request->method, $request->uri, $options);
        } catch (GuzzleException $exception) {
            throw new TransportException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }

        return new Response(
            statusCode: $response->getStatusCode(),
            headers: $response->getHeaders(),
            body: (string) $response->getBody(),
        );
    }
}
