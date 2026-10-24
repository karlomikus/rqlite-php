<?php

declare(strict_types=1);

use Kami\Rqlite\Rqlite;
use Kami\Rqlite\Http\Request;
use Kami\Rqlite\Http\Response;
use Kami\Rqlite\HttpAdapter;
use Kami\Rqlite\Result\ExecuteResult;
use Kami\Rqlite\Exception\HttpException;
use Kami\Rqlite\Exception\DecodingException;
use Kami\Rqlite\Exception\StatementException;
use Kami\Rqlite\Exception\TransportException;
use Kami\Rqlite\Result\AssociativeQueryResult;

describe('Query', function () {
    it('can create simple query', function () {
        $adapter = Mockery::mock(HttpAdapter::class);
        $adapter->shouldReceive('send')
            ->once()
            ->with(Mockery::on(static function (Request $request): bool {
                return $request->method === 'POST'
                    && $request->uri === '/db/query'
                    && $request->json === ['SELECT * FROM foo']
                    && $request->query() === ['timings' => true, 'associative' => true];
            }))
            ->andReturn(new Response(200, [], '{"results":[{"types":{"id":"integer"},"rows":[{"id":1}],"time":"0.001"}],"time":"0.001"}'));

        $rqlite = new Rqlite($adapter);
        $result = $rqlite->query(['SELECT * FROM foo']);

        expect($result->results)->toBeArray()->toHaveCount(1);
        expect($result->time)->toBeFloat()->toBe(0.001);
        expect($result->results[0])->toBeInstanceOf(AssociativeQueryResult::class);
        expect($result->results[0]->rows)->toBeArray()->toHaveCount(1);
    });

    it('will throw exception on error', function () {
        $adapter = Mockery::mock(HttpAdapter::class);
        $adapter->shouldReceive('send')
            ->once()
            ->andReturn(new Response(200, [], '{"results":[{"error":"rqlite error happened"}],"time":"0.001"}'));

        $rqlite = new Rqlite($adapter);
        $rqlite->query(['SELECT * FROM foo']);
    })->throws(StatementException::class);
});

describe('Execute', function () {
    it('can execute query', function () {
        $adapter = Mockery::mock(HttpAdapter::class);
        $adapter->shouldReceive('send')
            ->once()
            ->andReturn(new Response(200, [], '{"results":[{"last_insert_id":1,"rows_affected":1,"time":"0.00886"}],"time":"0.0152"}'));

        $rqlite = new Rqlite($adapter);
        $result = $rqlite->execute(['INSERT INTO foo(name, age) VALUES("fiona", 20)']);

        expect($result->time)->tobeFloat()->toBe(0.0152);
        expect($result->results[0])->toBeInstanceOf(ExecuteResult::class);
        expect($result->results[0]->lastInsertId)->toBe(1);
        expect($result->results[0]->rowsAffected)->toBe(1);
        expect($result->results[0]->time)->toBeFloat()->toBe(0.00886);
    });
});

describe('HTTP transport errors', function () {
    it('throws an http exception for non-2xx responses', function () {
        $adapter = Mockery::mock(HttpAdapter::class);
        $adapter->shouldReceive('send')
            ->once()
            ->andReturn(new Response(503, [], 'service unavailable'));

        $rqlite = new Rqlite($adapter);

        try {
            $rqlite->query(['SELECT * FROM foo']);
        } catch (HttpException $exception) {
            expect($exception->response->statusCode)->toBe(503);
            expect($exception->response->body)->toBe('service unavailable');

            return;
        }

        $this->fail('Expected an HttpException to be thrown.');
    });

    it('throws a decoding exception for invalid json', function () {
        $adapter = Mockery::mock(HttpAdapter::class);
        $adapter->shouldReceive('send')
            ->once()
            ->andReturn(new Response(200, [], 'not-json'));

        $rqlite = new Rqlite($adapter);

        expect(fn () => $rqlite->query(['SELECT * FROM foo']))->toThrow(DecodingException::class);
    });

    it('propagates transport exceptions', function () {
        $adapter = Mockery::mock(HttpAdapter::class);
        $adapter->shouldReceive('send')
            ->once()
            ->andThrow(new TransportException('connection refused'));

        $rqlite = new Rqlite($adapter);

        expect(fn () => $rqlite->query(['SELECT * FROM foo']))->toThrow(TransportException::class);
    });
});

describe('Status', function () {
    it('fetches the server status', function () {
        $adapter = Mockery::mock(HttpAdapter::class);
        $adapter->shouldReceive('send')
            ->once()
            ->with(Mockery::on(static function (Request $request): bool {
                return $request->method === 'GET'
                    && $request->uri === '/status'
                    && $request->json === null;
            }))
            ->andReturn(new Response(200, [], '{"store":{"ready":true}}'));

        $rqlite = new Rqlite($adapter);

        expect($rqlite->status())->toBe(['store' => ['ready' => true]]);
    });

    it('throws an http exception when the status endpoint fails', function () {
        $adapter = Mockery::mock(HttpAdapter::class);
        $adapter->shouldReceive('send')
            ->once()
            ->andReturn(new Response(500, [], 'internal error'));

        $rqlite = new Rqlite($adapter);

        expect(fn () => $rqlite->status())->toThrow(HttpException::class);
    });
});
