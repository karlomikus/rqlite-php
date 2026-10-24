<?php

declare(strict_types=1);

use Kami\Rqlite\Rqlite;
use Kami\Rqlite\Http\Request;
use Kami\Rqlite\Http\Response;
use Kami\Rqlite\HttpAdapter;
use Kami\Rqlite\Result\SQLiteError;
use Kami\Rqlite\Result\ExecuteResult;
use Kami\Rqlite\Exception\HttpException;
use Kami\Rqlite\Exception\RqliteException;
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
        $results = $rqlite->query(['SELECT * FROM foo']);

        $items = [];
        $rows = [];

        foreach ($results as $result) {
            $items[] = $result;
            $rows = iterator_to_array($result->rows);
        }

        expect($items)->toHaveCount(1);
        expect($results->time)->toBeFloat()->toBe(0.001);
        expect($items[0])->toBeInstanceOf(AssociativeQueryResult::class);
        expect($items[0]->time)->toBe(0.001);
        expect($rows)->toBeArray()->toHaveCount(1);
    });

    it('will throw exception on error', function () {
        $adapter = Mockery::mock(HttpAdapter::class);
        $adapter->shouldReceive('send')
            ->once()
            ->andReturn(new Response(200, [], '{"results":[{"error":"rqlite error happened"}],"time":"0.001"}'));

        $rqlite = new Rqlite($adapter);
        $results = $rqlite->query(['SELECT * FROM foo']);

        iterator_to_array($results);
    })->throws(StatementException::class);

    it('uses structured error details when error_v2 is present', function () {
        $adapter = Mockery::mock(HttpAdapter::class);
        $adapter->shouldReceive('send')
            ->once()
            ->andReturn(new Response(200, [], '{"results":[{"error":"legacy message","error_v2":{"message":"no such table: foo","sqlite":{"code":1,"extended_code":1,"system_errno":2}}}]}'));

        $rqlite = new Rqlite($adapter);
        $results = $rqlite->query(['SELECT * FROM foo']);

        try {
            iterator_to_array($results);
        } catch (StatementException $exception) {
            expect($exception->statementIndex)->toBe(0);
            expect($exception->errorMessage)->toBe('no such table: foo');
            expect($exception->sqliteError)->toBeInstanceOf(SQLiteError::class);
            expect($exception->sqliteError?->code)->toBe(1);
            expect($exception->sqliteError?->extendedCode)->toBe(1);
            expect($exception->sqliteError?->systemErrno)->toBe(2);

            return;
        }

        $this->fail('Expected a StatementException to be thrown.');
    });

    it('throws when rqlite returns a request-level error', function () {
        $adapter = Mockery::mock(HttpAdapter::class);
        $adapter->shouldReceive('send')
            ->once()
            ->andReturn(new Response(200, [], '{"error":"no leader"}'));

        $rqlite = new Rqlite($adapter);
        $results = $rqlite->query(['SELECT * FROM foo']);

        expect(fn () => iterator_to_array($results))
            ->toThrow(RqliteException::class, 'no leader');
    });

    it('throws a decoding exception when results are missing', function () {
        $adapter = Mockery::mock(HttpAdapter::class);
        $adapter->shouldReceive('send')
            ->once()
            ->andReturn(new Response(200, [], '{"time":"0.001"}'));

        $rqlite = new Rqlite($adapter);
        $results = $rqlite->query(['SELECT * FROM foo']);

        expect(fn () => iterator_to_array($results))
            ->toThrow(DecodingException::class);
    });

    it('preserves zero query timings and large integers', function () {
        $adapter = Mockery::mock(HttpAdapter::class);
        $adapter->shouldReceive('send')
            ->once()
            ->andReturn(new Response(200, [], '{"results":[{"types":{"id":"integer"},"rows":[{"id":9223372036854775808}],"time":"0"}],"time":"0"}'));

        $rqlite = new Rqlite($adapter);
        $results = $rqlite->query(['SELECT 9223372036854775808 AS id']);

        $result = null;
        $rows = [];

        foreach ($results as $item) {
            $result = $item;
            $rows = iterator_to_array($item->rows);
        }

        expect($results->time)->toBe(0.0);
        expect($result?->time)->toBe(0.0);
        expect($rows[0]['id'])->toBe('9223372036854775808');
    });
});

describe('Execute', function () {
    it('can execute query', function () {
        $adapter = Mockery::mock(HttpAdapter::class);
        $adapter->shouldReceive('send')
            ->once()
            ->andReturn(new Response(200, [], '{"results":[{"last_insert_id":1,"rows_affected":1,"time":"0.00886"}],"time":"0.0152"}'));

        $rqlite = new Rqlite($adapter);
        $results = $rqlite->execute(['INSERT INTO foo(name, age) VALUES("fiona", 20)']);

        $items = iterator_to_array($results->results);

        expect($results->time)->tobeFloat()->toBe(0.0152);
        expect($items[0])->toBeInstanceOf(ExecuteResult::class);
        expect($items[0]->lastInsertId)->toBe(1);
        expect($items[0]->rowsAffected)->toBe(1);
        expect($items[0]->time)->toBeFloat()->toBe(0.00886);
    });

    it('preserves zero rows affected and zero timing', function () {
        $adapter = Mockery::mock(HttpAdapter::class);
        $adapter->shouldReceive('send')
            ->once()
            ->andReturn(new Response(200, [], '{"results":[{"last_insert_id":0,"rows_affected":0,"time":"0"}],"time":"0"}'));

        $rqlite = new Rqlite($adapter);
        $results = $rqlite->execute(['UPDATE foo SET name = "fiona" WHERE id = 99']);

        $items = iterator_to_array($results->results);

        expect($results->time)->toBe(0.0);
        expect($items[0]->rowsAffected)->toBe(0);
        expect($items[0]->time)->toBe(0.0);
    });

    it('does not send false boolean flags', function () {
        $adapter = Mockery::mock(HttpAdapter::class);
        $adapter->shouldReceive('send')
            ->once()
            ->with(Mockery::on(static function (Request $request): bool {
                return $request->method === 'POST'
                    && $request->uri === '/db/execute'
                    && !array_key_exists('timings', $request->query())
                    && ($request->query()['associative'] ?? null) === true;
            }))
            ->andReturn(new Response(200, [], '{"results":[{"last_insert_id":0,"rows_affected":0,"time":"0"}],"time":"0"}'));

        $rqlite = new Rqlite($adapter);
        iterator_to_array($rqlite->execute(['CREATE TABLE foo (id INTEGER)'], timings: false)->results);
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
            expect($exception->response->text())->toBe('service unavailable');

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
        $results = $rqlite->query(['SELECT * FROM foo']);

        expect(fn () => iterator_to_array($results))->toThrow(DecodingException::class);
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
