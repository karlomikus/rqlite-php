<?php

declare(strict_types=1);

use Kami\Rqlite\Rqlite;
use Kami\Rqlite\ErrorFormat;
use Kami\Rqlite\QueryOptions;
use Kami\Rqlite\ExecuteOptions;
use Kami\Rqlite\RequestOptions;
use Kami\Rqlite\BackupOptions;
use Kami\Rqlite\LoadOptions;
use Kami\Rqlite\ReadConsistencyLevel;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Client\ClientInterface;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Kami\Rqlite\Result\SQLiteError;
use Kami\Rqlite\Result\ExecuteResult;
use Kami\Rqlite\Result\RequestResult;
use Kami\Rqlite\Exception\HttpException;
use Kami\Rqlite\Exception\RqliteException;
use Kami\Rqlite\Exception\DecodingException;
use Kami\Rqlite\Exception\StatementException;
use Kami\Rqlite\Exception\TransportException;
use Kami\Rqlite\Result\AssociativeQueryResult;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\Psr7\NoSeekStream;

function unitRqlite(ClientInterface $client): Rqlite
{
    $factory = new HttpFactory();

    return new Rqlite('http://localhost:4001', $client, $factory, $factory);
}

describe('Query', function (): void {
    it('can create simple query', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(static fn(RequestInterface $request): bool => $request->getMethod() === 'POST'
                && $request->getUri()->getPath() === '/db/query'
                && (string) $request->getBody() === '["SELECT * FROM foo"]'
                && $request->getUri()->getQuery() === http_build_query(['associative' => true])))
            ->andReturn(new Response(200, [], '{"results":[{"types":{"id":"integer"},"rows":[{"id":1}],"time":"0.001"}],"time":"0.001"}'));

        $rqlite = unitRqlite($adapter);
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

    it('passes query options through to the request', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(static fn(RequestInterface $request): bool => $request->getMethod() === 'POST'
                && $request->getUri()->getPath() === '/db/query'
                && $request->getUri()->getQuery() === http_build_query([
                    'timings' => true,
                    'pretty' => true,
                    'associative' => true,
                    'blob_array' => true,
                    'level' => 'linearizable',
                    'timeout' => '2s',
                    'linearizable_timeout' => '500ms',
                    'freshness' => '1.5s',
                    'freshness_strict' => true,
                    'raft_index' => true,
                    'errors' => 'v2',
                ])))
            ->andReturn(new Response(200, [], '{"results":[{"types":{"id":"integer"},"rows":[{"id":1}]}]}'));

        $rqlite = unitRqlite($adapter);
        $results = $rqlite->query(['SELECT * FROM foo'], new QueryOptions(
            timings: true,
            pretty: true,
            blobAsArray: true,
            readConsistencyLevel: ReadConsistencyLevel::Linearizable,
            timeout: 2,
            linearizableTimeout: '500ms',
            freshness: 1.5,
            freshnessStrict: true,
            raftIndex: true,
            errors: ErrorFormat::V2,
        ));

        iterator_to_array($results);
    });

    it('omits zero query option durations', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(static fn(RequestInterface $request): bool => $request->getUri()->getQuery() === http_build_query(['associative' => true])))
            ->andReturn(new Response(200, [], '{"results":[{"types":{"id":"integer"},"rows":[{"id":1}]}]}'));

        $rqlite = unitRqlite($adapter);
        $results = $rqlite->query(['SELECT * FROM foo'], new QueryOptions(
            timeout: 0,
            linearizableTimeout: 0.0,
            freshness: 0,
        ));

        iterator_to_array($results);
    });

    it('rejects empty query option durations', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);

        $rqlite = unitRqlite($adapter);

        expect(fn (): \Kami\Rqlite\Result\Results => $rqlite->query(['SELECT * FROM foo'], new QueryOptions(timeout: ' ')))
            ->toThrow(InvalidArgumentException::class, 'A duration option cannot be empty.');
    });

    it('will throw exception on error', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new Response(200, [], '{"results":[{"error":"rqlite error happened"}],"time":"0.001"}'));

        $rqlite = unitRqlite($adapter);
        $results = $rqlite->query(['SELECT * FROM foo']);

        iterator_to_array($results);
    })->throws(StatementException::class);

    it('uses structured error details when error_v2 is present', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new Response(200, [], '{"results":[{"error":"legacy message","error_v2":{"message":"no such table: foo","sqlite":{"code":1,"extended_code":1,"system_errno":2}}}]}'));

        $rqlite = unitRqlite($adapter);
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

    it('throws when rqlite returns a request-level error', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new Response(200, [], '{"error":"no leader"}'));

        $rqlite = unitRqlite($adapter);
        $results = $rqlite->query(['SELECT * FROM foo']);

        expect(fn (): array => iterator_to_array($results))
            ->toThrow(RqliteException::class, 'no leader');
    });

    it('throws a decoding exception when results are missing', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new Response(200, [], '{"time":"0.001"}'));

        $rqlite = unitRqlite($adapter);
        $results = $rqlite->query(['SELECT * FROM foo']);

        expect(fn (): array => iterator_to_array($results))
            ->toThrow(DecodingException::class);
    });

    it('preserves zero query timings and large integers', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new Response(200, [], '{"results":[{"types":{"id":"integer"},"rows":[{"id":9223372036854775808}],"time":"0"}],"time":"0"}'));

        $rqlite = unitRqlite($adapter);
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

describe('Execute', function (): void {
    it('can execute query', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new Response(200, [], '{"results":[{"last_insert_id":1,"rows_affected":1,"time":"0.00886"}],"time":"0.0152"}'));

        $rqlite = unitRqlite($adapter);
        $results = $rqlite->execute(['INSERT INTO foo(name, age) VALUES("fiona", 20)']);

        $items = iterator_to_array($results->results);

        expect($results->time)->tobeFloat()->toBe(0.0152);
        expect($items[0])->toBeInstanceOf(ExecuteResult::class);
        expect($items[0]->lastInsertId)->toBe(1);
        expect($items[0]->rowsAffected)->toBe(1);
        expect($items[0]->time)->toBeFloat()->toBe(0.00886);
    });

    it('preserves zero rows affected and zero timing', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new Response(200, [], '{"results":[{"last_insert_id":0,"rows_affected":0,"time":"0"}],"time":"0"}'));

        $rqlite = unitRqlite($adapter);
        $results = $rqlite->execute(['UPDATE foo SET name = "fiona" WHERE id = 99']);

        $items = iterator_to_array($results->results);

        expect($results->time)->toBe(0.0);
        expect($items[0]->rowsAffected)->toBe(0);
        expect($items[0]->time)->toBe(0.0);
    });

    it('passes execute options through to the request', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(static fn(RequestInterface $request): bool => $request->getMethod() === 'POST'
                && $request->getUri()->getPath() === '/db/execute'
                && $request->getUri()->getQuery() === http_build_query([
                    'timings' => true,
                    'pretty' => true,
                    'db_timeout' => '1s',
                    'timeout' => '2s',
                    'transaction' => true,
                    'queue' => true,
                    'wait' => true,
                    'raft_index' => true,
                    'errors' => 'v1',
                ])))
            ->andReturn(new Response(200, [], '{"results":[{"last_insert_id":0,"rows_affected":0,"time":"0"}],"time":"0"}'));

        $rqlite = unitRqlite($adapter);
        $results = $rqlite->execute(['CREATE TABLE foo (id INTEGER)'], new ExecuteOptions(
            transaction: true,
            pretty: true,
            timings: true,
            queue: true,
            wait: true,
            timeout: '2s',
            dbTimeout: 1,
            raftIndex: true,
            errors: ErrorFormat::V1,
        ));

        iterator_to_array($results->results);
    });

    it('does not send false or associative execute flags', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(static fn(RequestInterface $request): bool => $request->getMethod() === 'POST'
                && $request->getUri()->getPath() === '/db/execute'
                && $request->getUri()->getQuery() === http_build_query([])))
            ->andReturn(new Response(200, [], '{"results":[{"last_insert_id":0,"rows_affected":0,"time":"0"}],"time":"0"}'));

        $rqlite = unitRqlite($adapter);
        iterator_to_array($rqlite->execute(['CREATE TABLE foo (id INTEGER)'])->results);
    });
});

describe('Request', function (): void {
    it('returns mixed read and write results', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new Response(200, [], '{"results":[{"last_insert_id":1,"rows_affected":1,"time":"0.001"},{"types":{"id":"integer"},"rows":[{"id":1}],"time":"0.002"}],"time":"0.003"}'));

        $rqlite = unitRqlite($adapter);
        $results = $rqlite->request([
            'INSERT INTO foo(name) VALUES("fiona")',
            'SELECT id FROM foo',
        ]);

        $items = [];
        $rows = [];

        foreach ($results as $result) {
            $items[] = $result;
            $rows[] = iterator_to_array($result->rows);
        }

        expect($results->time)->toBe(0.003);
        expect($items)->toHaveCount(2);
        expect($items[0])->toBeInstanceOf(RequestResult::class);
        expect($items[0]->lastInsertId)->toBe(1);
        expect($items[0]->rowsAffected)->toBe(1);
        expect($items[0]->time)->toBe(0.001);
        expect($rows[0])->toBe([]);
        expect($items[1]->types)->toBe(['id' => 'integer']);
        expect($items[1]->time)->toBe(0.002);
        expect($rows[1])->toBe([['id' => 1]]);
    });

    it('passes request options through to the request', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(static fn(RequestInterface $request): bool => $request->getMethod() === 'POST'
                && $request->getUri()->getPath() === '/db/request'
                && $request->getUri()->getQuery() === http_build_query([
                    'transaction' => true,
                    'timings' => true,
                    'pretty' => true,
                    'associative' => true,
                    'blob_array' => true,
                    'level' => 'auto',
                    'timeout' => '2s',
                    'linearizable_timeout' => '500ms',
                    'freshness' => '1.5s',
                    'freshness_strict' => true,
                    'raft_index' => true,
                    'errors' => 'v2',
                ])))
            ->andReturn(new Response(200, [], '{"results":[{"rows_affected":0}],"time":"0"}'));

        $rqlite = unitRqlite($adapter);
        $results = $rqlite->request(['UPDATE foo SET name = "fiona" WHERE id = 99'], new RequestOptions(
            transaction: true,
            pretty: true,
            timings: true,
            blobAsArray: true,
            readConsistencyLevel: ReadConsistencyLevel::Auto,
            timeout: '2s',
            linearizableTimeout: '500ms',
            freshness: 1.5,
            freshnessStrict: true,
            raftIndex: true,
            errors: ErrorFormat::V2,
        ));

        iterator_to_array($results);
    });

    it('decodes associative write results with null rows', function (): void {
        // rqlite renders `rows` as `null` for write statements when the request
        // is made in associative form (`associative=true`).
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new Response(200, [], '{"results":[{"last_insert_id":6,"rows_affected":1,"rows":null},{"types":{"id":"integer","title":"text"},"rows":[{"id":6,"title":"x"}]}],"time":"0"}'));

        $rqlite = unitRqlite($adapter);
        $results = $rqlite->request([
            'INSERT INTO foo(title) VALUES(?)',
            'SELECT id, title FROM foo',
        ]);

        $items = [];
        $rows = [];

        foreach ($results as $result) {
            $items[] = $result;
            $rows[] = iterator_to_array($result->rows);
        }

        expect($items)->toHaveCount(2);
        expect($items[0]->lastInsertId)->toBe(6);
        expect($items[0]->rowsAffected)->toBe(1);
        expect($rows[0])->toBe([]);
        expect($items[1]->types)->toBe(['id' => 'integer', 'title' => 'text']);
        expect($rows[1])->toBe([['id' => 6, 'title' => 'x']]);
    });

    it('throws when a request statement fails', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new Response(200, [], '{"results":[{"error_v2":{"message":"no such table: foo","sqlite":{"code":1}}}]}'));

        $rqlite = unitRqlite($adapter);
        $results = $rqlite->request(['SELECT * FROM foo']);

        expect(fn (): array => iterator_to_array($results))
            ->toThrow(StatementException::class, 'no such table: foo');
    });
});

describe('HTTP transport errors', function (): void {
    it('accepts only 2xx responses for regular and raw requests', function (int $status, bool $successful): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')->twice()->andReturn(new Response($status, [], '{}'));
        $rqlite = unitRqlite($adapter);

        if ($successful) {
            expect($rqlite->status())->toBe([]);
            $rqlite->load('SELECT 1');
        } else {
            expect(fn (): array => $rqlite->status())->toThrow(HttpException::class);
            expect(fn () => $rqlite->load('SELECT 1'))->toThrow(HttpException::class);
        }
    })->with([[199, false], [200, true], [299, true], [300, false]]);

    it('throws an http exception for non-2xx responses', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $response = new Response(503, ['X-Test' => ['one', 'two']], 'service unavailable', '1.1', 'Unavailable');
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andReturn($response);

        $rqlite = unitRqlite($adapter);

        try {
            $rqlite->query(['SELECT * FROM foo']);
        } catch (HttpException $exception) {
            expect($exception->response)->toBe($response);
            expect($exception->getCode())->toBe(503);
            expect($exception->getMessage())->toBe('rqlite returned HTTP status 503');
            expect($exception->response->getStatusCode())->toBe(503);
            expect($exception->response->getHeaderLine('x-test'))->toBe('one, two');
            expect($exception->response->getReasonPhrase())->toBe('Unavailable');
            expect($exception->response->getBody()->getContents())->toBe('service unavailable');

            return;
        }

        $this->fail('Expected an HttpException to be thrown.');
    });

    it('throws a decoding exception for invalid json', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new Response(200, [], 'not-json'));

        $rqlite = unitRqlite($adapter);
        $results = $rqlite->query(['SELECT * FROM foo']);

        expect(fn (): array => iterator_to_array($results))->toThrow(DecodingException::class);
    });

    it('wraps PSR-18 transport exceptions', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $cause = new \GuzzleHttp\Exception\ConnectException('connection refused', new \GuzzleHttp\Psr7\Request('GET', 'http://localhost/status'));
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andThrow($cause);

        $rqlite = unitRqlite($adapter);

        try {
            $rqlite->query(['SELECT * FROM foo']);
            $this->fail('Expected a transport exception.');
        } catch (TransportException $exception) {
            expect($exception->getPrevious())->toBe($cause);
            expect($exception->getMessage())->toBe('connection refused');
        }
    });
});

it('parses a non-seekable PSR-7 response stream lazily', function (): void {
    $largeValue = str_repeat('x', 20000);
    $source = Utils::streamFor('{"results":[{"types":{"value":"text"},"rows":[{"value":"' . $largeValue . '"}]}]}');
    $body = new NoSeekStream($source);
    $adapter = Mockery::mock(ClientInterface::class);
    $adapter->shouldReceive('sendRequest')->once()->andReturn(new Response(200, [], $body));

    $results = (unitRqlite($adapter))->query(['SELECT value']);
    expect($source->tell())->toBe(0);

    foreach ($results as $result) {
        expect($source->tell())->toBeLessThan($source->getSize());
        foreach ($result->rows as $row) {
            expect($row)->toBe(['value' => $largeValue]);
        }
    }

    expect($source->eof())->toBeTrue();
});

describe('Status', function (): void {
    it('fetches the server status', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(static fn(RequestInterface $request): bool => $request->getMethod() === 'GET'
                && $request->getUri()->getPath() === '/status'
                && !$request->hasHeader('Content-Type')))
            ->andReturn(new Response(200, [], '{"store":{"ready":true}}'));

        $rqlite = unitRqlite($adapter);

        expect($rqlite->status())->toBe(['store' => ['ready' => true]]);
    });

    it('throws an http exception when the status endpoint fails', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new Response(500, [], 'internal error'));

        $rqlite = unitRqlite($adapter);

        expect(fn (): array => $rqlite->status())->toThrow(HttpException::class);
    });
});

describe('Backup', function (): void {
    it('returns a non-seekable backup stream without reading it', function (): void {
        $source = Utils::streamFor('backup data');
        $body = new NoSeekStream($source);
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')->once()->andReturn(new Response(200, [], $body));

        $result = unitRqlite($adapter)->backup();
        expect($result)->toBe($body);
        expect($source->tell())->toBe(0);
        expect($result->getContents())->toBe('backup data');
        $result->close();
    });

    it('returns a readable backup body that closes the original PSR-7 stream', function (): void {
        $source = Utils::streamFor('backup data');
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')->once()->andReturn(new Response(200, [], $source));

        $body = (unitRqlite($adapter))->backup();
        expect($body)->toBe($source);
        expect($body->getContents())->toBe('backup data');
        $body->close();
        expect($source->isReadable())->toBeFalse();
    });

    it('sends a backup request and returns the body', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(static fn(RequestInterface $request): bool => $request->getMethod() === 'GET'
                && $request->getUri()->getPath() === '/db/backup'
                && $request->getUri()->getQuery() === http_build_query([])))
            ->andReturn(new Response(200, [], 'SQLite format fake backup data'));

        $rqlite = unitRqlite($adapter);

        expect($rqlite->backup()->getContents())->toBe('SQLite format fake backup data');
    });

    it('passes backup options through to the request', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(static fn(RequestInterface $request): bool => $request->getMethod() === 'GET'
                && $request->getUri()->getPath() === '/db/backup'
                && $request->getUri()->getQuery() === http_build_query([
                    'fmt' => 'sql',
                    'vacuum' => true,
                    'compress' => true,
                    'noleader' => true,
                    'redirect' => true,
                ])))
            ->andReturn(new Response(200, [], 'CREATE TABLE foo (id INTEGER);'));

        $rqlite = unitRqlite($adapter);

        expect($rqlite->backup(new BackupOptions(
            format: 'sql',
            vacuum: true,
            compress: true,
            noLeader: true,
            redirect: true,
        ))->getContents())->toBe('CREATE TABLE foo (id INTEGER);');
    });

    it('rejects an unknown backup format', function (): void {
        new BackupOptions(format: 'json');
    })->throws(InvalidArgumentException::class, 'The backup format must be "sql" or null.');

    it('throws an http exception when the backup endpoint fails', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new Response(500, [], 'backup failed'));

        $rqlite = unitRqlite($adapter);

        expect(fn (): StreamInterface => $rqlite->backup())->toThrow(HttpException::class);
    });
});

describe('Load', function (): void {
    it('detects sql text and sends a plain load request', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(static function (RequestInterface $request): bool {
                $stream = $request->getBody();

                return $request->getMethod() === 'POST'
                    && $request->getUri()->getPath() === '/db/load'
                    && $request->getUri()->getQuery() === http_build_query([])
                    && $request->getHeaderLine('Content-Type') === 'text/plain'
                    && $stream instanceof StreamInterface
                    && $stream->getContents() === 'CREATE TABLE foo (id INTEGER);';
            }))
            ->andReturn(new Response(200));

        $rqlite = unitRqlite($adapter);
        $rqlite->load('CREATE TABLE foo (id INTEGER);');
    });

    it('detects a sqlite file and sends an octet stream load request', function (): void {
        $data = 'SQLite format fake database bytes';

        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(static function (RequestInterface $request) use ($data): bool {
                $stream = $request->getBody();

                return $request->getMethod() === 'POST'
                    && $request->getUri()->getPath() === '/db/load'
                    && $request->getHeaderLine('Content-Type') === 'application/octet-stream'
                    && $stream instanceof StreamInterface
                    && $stream->getContents() === $data;
            }))
            ->andReturn(new Response(200));

        $rqlite = unitRqlite($adapter);
        $rqlite->load($data);
    });

    it('rewinds a seekable stream before sending it', function (): void {
        $data = 'CREATE TABLE foo (id INTEGER);';
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $data);

        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(static function (RequestInterface $request) use ($data): bool {
                $stream = $request->getBody();

                return $request->getHeaderLine('Content-Type') === 'text/plain'
                    && $stream instanceof StreamInterface
                    && $stream->getContents() === $data;
            }))
            ->andReturn(new Response(200));

        $rqlite = unitRqlite($adapter);
        $rqlite->load($stream);
    });

    it('supports a non seekable stream', function (): void {
        $data = 'SQLite format fake database bytes';
        [$source, $writer] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($writer, $data);
        fclose($writer);
        expect(stream_get_meta_data($source)['seekable'])->toBeFalse();

        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(static function (RequestInterface $request) use ($data): bool {
                $stream = $request->getBody();

                return $request->getHeaderLine('Content-Type') === 'application/octet-stream'
                    && $stream instanceof StreamInterface
                    && $stream->getContents() === $data;
            }))
            ->andReturn(new Response(200));

        $rqlite = unitRqlite($adapter);
        $rqlite->load($source);
        expect(is_resource($source))->toBeFalse();
    });

    it('accepts a PSR-7 stream', function (): void {
        $data = 'CREATE TABLE foo (id INTEGER);';

        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(static function (RequestInterface $request) use ($data): bool {
                $stream = $request->getBody();

                return $request->getHeaderLine('Content-Type') === 'text/plain'
                    && $stream instanceof StreamInterface
                    && $stream->getContents() === $data;
            }))
            ->andReturn(new Response(200));

        $rqlite = unitRqlite($adapter);
        $rqlite->load(Utils::streamFor($data));
    });

    it('passes load options through to the request', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(static fn(RequestInterface $request): bool => $request->getUri()->getPath() === '/db/load'
                && $request->getUri()->getQuery() === http_build_query(['redirect' => true])))
            ->andReturn(new Response(200));

        $rqlite = unitRqlite($adapter);
        $rqlite->load('CREATE TABLE foo (id INTEGER);', new LoadOptions(redirect: true));
    });

    it('rejects invalid upload data', function (): void {
        $rqlite = unitRqlite(Mockery::mock(ClientInterface::class));

        expect(fn () => $rqlite->load(123))->toThrow(InvalidArgumentException::class);
    });

    it('throws an http exception when the load endpoint fails', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new Response(500, [], 'load failed'));

        $rqlite = unitRqlite($adapter);

        expect(fn () => $rqlite->load('CREATE TABLE foo (id INTEGER);'))->toThrow(HttpException::class);
    });
});

describe('Boot', function (): void {
    it('sends a boot request with the stream data', function (): void {
        $data = 'SQLite format fake database bytes';

        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->with(Mockery::on(static function (RequestInterface $request) use ($data): bool {
                $stream = $request->getBody();

                return $request->getMethod() === 'POST'
                    && $request->getUri()->getPath() === '/boot'
                    && $request->getUri()->getQuery() === http_build_query([])
                    && $request->getHeaderLine('Content-Type') === 'application/octet-stream'
                    && $stream instanceof StreamInterface
                    && $stream->getContents() === $data;
            }))
            ->andReturn(new Response(200));

        $rqlite = unitRqlite($adapter);
        $rqlite->boot($data);
    });

    it('rejects invalid boot data', function (): void {
        $rqlite = unitRqlite(Mockery::mock(ClientInterface::class));

        expect(fn () => $rqlite->boot(['not', 'a', 'stream']))->toThrow(InvalidArgumentException::class);
    });

    it('throws an http exception when the boot endpoint fails', function (): void {
        $adapter = Mockery::mock(ClientInterface::class);
        $adapter->shouldReceive('sendRequest')
            ->once()
            ->andReturn(new Response(400, [], 'must be a single-node system'));

        $rqlite = unitRqlite($adapter);

        expect(fn () => $rqlite->boot('SQLite format fake database bytes'))->toThrow(HttpException::class);
    });
});
