<?php

declare(strict_types=1);

namespace Kami\Rqlite\Adapters;

use Kami\Rqlite\Http\Request;
use Kami\Rqlite\HttpAdapter;
use Kami\Rqlite\Exception\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * HTTP adapter built on the PSR-18 client interface.
 *
 * Works with any PSR-18 client (Guzzle, Symfony HttpClient, and others) and
 * PSR-17 factories. The base URL is owned by this adapter, so the injected
 * client does not need one configured.
 */
class PsrHttpAdapter implements HttpAdapter
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly UriFactoryInterface $uriFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function send(Request $request): ResponseInterface
    {
        try {
            return $this->client->sendRequest($this->buildPsrRequest($request));
        } catch (ClientExceptionInterface $exception) {
            throw new TransportException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }
    }

    private function buildPsrRequest(Request $request): RequestInterface
    {
        $psrRequest = $this->requestFactory->createRequest($request->method, $this->buildUri($request));

        foreach ($request->headers() as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }

        $stream = $request->stream;

        if ($stream !== null) {
            $psrRequest = $psrRequest->withBody($this->toPsrStream($stream));
        } else {
            $body = $request->body();

            if ($body !== null) {
                $psrRequest = $psrRequest->withBody($this->streamFactory->createStream($body));
            }
        }

        return $psrRequest;
    }

    /**
     * @param resource|StreamInterface $stream
     */
    private function toPsrStream($stream): StreamInterface
    {
        if ($stream instanceof StreamInterface) {
            return $stream;
        }

        return $this->streamFactory->createStreamFromResource($stream);
    }

    private function buildUri(Request $request): UriInterface
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($request->uri, '/');
        $query = $request->query();

        $uri = $this->uriFactory->createUri($url);

        if ($query !== []) {
            $uri = $uri->withQuery(http_build_query($query));
        }

        return $uri;
    }
}
