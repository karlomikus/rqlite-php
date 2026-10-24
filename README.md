# PHP Client for Rqlite

A PHP client for [rqlite](https://rqlite.io), the lightweight distributed
database built on SQLite. It supports multiple HTTP adapters, streaming
associative results, typed result objects, query/execute/request options, and
structured rqlite errors.

## Requirements

- PHP 8.2, 8.3, 8.4 or 8.5
- One HTTP adapter:
  - `ext-curl` for the Curl adapter
  - Guzzle 7 for the Guzzle adapter
  - `allow_url_fopen` enabled for the File adapter

## Installation

```bash
composer require karlomikus/rqlite
```

## Quick start

```php
<?php

use Kami\Rqlite\Adapters\Curl;
use Kami\Rqlite\Rqlite;

$adapter = new Curl('http://localhost:4001');
$rqlite = new Rqlite($adapter);

$results = $rqlite->query([
    'SELECT * FROM foo',
]);

// Results and rows are streamed as generators.
foreach ($results as $result) {
    foreach ($result->rows as $row) {
        var_dump($row);
    }
}

// The top-level time is populated after the stream is fully consumed.
var_dump($results->time);
```

## Supported features

| Area | Support |
|---|---|
| Endpoints | `/db/query`, `/db/execute`, `/db/request`, `/status` |
| Query results | Associative form only: `types` map and `rows` objects |
| Parameters | Positional and named parameters, forwarded as-is |
| Transactions | Execute and unified request endpoints |
| Timings | Query, execute, and request endpoints |
| Read consistency | `none`, `weak`, `strong`, `linearizable`, `auto` |
| Query options | See [Query options](#query-options) |
| Execute options | See [Execute options](#execute-options) |
| Request options | See [Unified request endpoint](#unified-request-endpoint) |
| Error formats | Legacy `error` and structured `error_v2` |
| Streaming | Response bodies, results, and query/request rows |

Results are always requested in associative form. The standard rqlite
`columns` / `values` form is not currently exposed.

## Statements and parameters

Each statement is an array containing SQL followed by either positional
parameters or a single associative array of named parameters:

```php
// Positional parameters
$rqlite->query([
    ['SELECT id, name FROM users WHERE id = ?', 1],
]);

// Named parameters
$rqlite->query([
    ['SELECT id, name FROM users WHERE name = :name', ['name' => 'fiona']],
]);
```

Parameter arrays are forwarded to rqlite as-is. Use a list for positional
parameters and an associative array for named parameters.

## Query options

`query()` accepts a `QueryOptions` object. Options with a `false` or `null`
value are omitted from the request. Durations accept duration strings such as
`500ms` or `2s`, or a number of seconds; numeric zero is treated as unset.

```php
use Kami\Rqlite\ErrorFormat;
use Kami\Rqlite\QueryOptions;
use Kami\Rqlite\ReadConsistencyLevel;

$results = $rqlite->query(
    ['SELECT * FROM foo'],
    new QueryOptions(
        timings: true,
        pretty: true,
        readConsistencyLevel: ReadConsistencyLevel::Strong,
        timeout: '2s',
        raftIndex: true,
        errors: ErrorFormat::V2,
    ),
);
```

| Option | Wire parameter | Default | Description |
|---|---|---|---|
| `timings` | `timings` | `false` | Include statement timings |
| `pretty` | `pretty` | `false` | Request pretty-printed JSON |
| `blobAsArray` | `blob_array` | `false` | Return BLOBs as arrays of byte values |
| `readConsistencyLevel` | `level` | `null` (omitted) | `ReadConsistencyLevel` enum value |
| `timeout` | `timeout` | `null` | Request-forwarding timeout |
| `linearizableTimeout` | `linearizable_timeout` | `null` | Linearizable read timeout |
| `freshness` | `freshness` | `null` | Read freshness duration |
| `freshnessStrict` | `freshness_strict` | `false` | Require the requested freshness |
| `raftIndex` | `raft_index` | `false` | Ask rqlite to include the Raft index |
| `errors` | `errors` | `null` | `ErrorFormat::V1` or `ErrorFormat::V2` |

`associative` is always sent as `true` and is not configurable.

## Execute options

`execute()` accepts an `ExecuteOptions` object:

```php
use Kami\Rqlite\ExecuteOptions;

$results = $rqlite->execute(
    [
        ['INSERT INTO foo (name) VALUES (?)', 'fiona'],
        ['INSERT INTO foo (name) VALUES (?)', 'bob'],
    ],
    new ExecuteOptions(
        transaction: true,
        timings: true,
        timeout: '5s',
    ),
);
```

| Option | Wire parameter | Default | Description |
|---|---|---|---|
| `transaction` | `transaction` | `false` | Wrap all statements in a transaction |
| `pretty` | `pretty` | `false` | Request pretty-printed JSON |
| `timings` | `timings` | `false` | Include statement timings |
| `queue` | `queue` | `false` | Queue the write |
| `wait` | `wait` | `false` | Wait for a queued write to be applied |
| `timeout` | `timeout` | `null` | Request-forwarding timeout |
| `dbTimeout` | `db_timeout` | `null` | Per-statement SQLite timeout |
| `raftIndex` | `raft_index` | `false` | Ask rqlite to include the Raft index |
| `errors` | `errors` | `null` | `ErrorFormat::V1` or `ErrorFormat::V2` |

`wait` only has an effect when `queue` is enabled.

## Unified request endpoint

`request()` sends read and write statements through `/db/request`:

```php
use Kami\Rqlite\RequestOptions;

$results = $rqlite->request(
    [
        ['INSERT INTO foo (name) VALUES (?)', 'fiona'],
        ['SELECT id, name FROM foo WHERE name = ?', 'fiona'],
    ],
    new RequestOptions(transaction: true),
);

foreach ($results as $result) {
    // Read statements populate rows; write statements leave this empty.
    foreach ($result->rows as $row) {
        var_dump($row);
    }

    // Write statements populate these; read statements leave them null.
    var_dump($result->lastInsertId, $result->rowsAffected);
}
```

| Option | Wire parameter | Default |
|---|---|---|
| `transaction` | `transaction` | `false` |
| `pretty` | `pretty` | `false` |
| `timings` | `timings` | `false` |
| `blobAsArray` | `blob_array` | `false` |
| `readConsistencyLevel` | `level` | `null` (omitted) |
| `timeout` | `timeout` | `null` |
| `linearizableTimeout` | `linearizable_timeout` | `null` |
| `freshness` | `freshness` | `null` |
| `freshnessStrict` | `freshness_strict` | `false` |
| `raftIndex` | `raft_index` | `false` |
| `errors` | `errors` | `null` |

Queued writes (`queue` / `wait`) are not supported by `/db/request`.
Results are always associative. `associative` is not configurable.

## Status

```php
$status = $rqlite->status();
```

`status()` returns the decoded `/status` response as an array.

## Result types

| Method | Result object | Fields |
|---|---|---|
| `query()` | `AssociativeQueryResult` | `types`, `rows` generator, `time` |
| `execute()` | `ExecuteResult` | `lastInsertId`, `rowsAffected`, `time` |
| `request()` | `RequestResult` | `types`, `rows` generator, `lastInsertId`, `rowsAffected`, `time` |

`Results` is a single-pass iterator over result objects and exposes the
top-level `time` after the stream is fully consumed.

## Typed rows

`Results` and the associative result classes are generic, so PHPStan or another
tool that understands generics can narrow rows to your own array shape:

```php
use Kami\Rqlite\Result\AssociativeQueryResult;
use Kami\Rqlite\Result\Results;

/** @var Results<AssociativeQueryResult<array{id: int, name: string}>> $results */
$results = $rqlite->query([
    'SELECT id, name FROM foo',
]);

foreach ($results as $result) {
    foreach ($result->rows as $row) {
        // $row is array{id: int, name: string}
    }
}
```

When no type is specified, rows default to `array<string, mixed>`.
`RequestResult` supports the same row generics.

## Streaming

Response bodies are parsed incrementally, so large result sets do not need to
be loaded into memory in full.

- `query()`, `execute()`, and `request()` return `Results`, a single-pass
  iterator.
- Query and request results and their rows are generators. Consume a result's
  rows before advancing to the next result.
- The top-level `Results::$time` and each query/request result's `time` are
  populated after the corresponding stream has been consumed.
- Do not collect query or request results with `iterator_to_array($results)`
  without consuming their rows first. Iterate with nested `foreach` loops
  instead.

## Available adapters

### Curl

```php
use Kami\Rqlite\Adapters\Curl;

$adapter = new Curl('http://localhost:4001');
```

Requires `ext-curl`. The response body is spooled to `php://temp` so it can be
parsed incrementally.

### Guzzle

```php
use GuzzleHttp\Client;
use Kami\Rqlite\Adapters\Guzzle;

$httpClient = new Client([
    'base_uri' => 'http://localhost:4001',
    // Configure auth, TLS, timeouts, or a proxy here.
]);

$adapter = new Guzzle($httpClient);
```

The adapter uses Guzzle with `http_errors` and redirects disabled, and streams
the response body. Guzzle-specific concerns such as basic auth, TLS, and
timeouts should be configured on the injected client.

### File

```php
use Kami\Rqlite\Adapters\File;

$adapter = new File('http://localhost:4001');
```

Requires `allow_url_fopen`. The response body is read as a stream.

### PSR-3 logger wrapper

```php
use Kami\Rqlite\Adapters\LoggerAdapter;

$adapter = new LoggerAdapter($adapter, $logger);
```

`LoggerAdapter` logs the request method, URI, query parameters, and body at
debug level, plus the response status and duration. It rethrows transport
errors after logging them. Request bodies are logged as-is, so avoid logging
sensitive values at debug level.

### Custom adapters

Implement `Kami\Rqlite\HttpAdapter`:

```php
use Kami\Rqlite\Http\Request;
use Kami\Rqlite\Http\Response;
use Kami\Rqlite\HttpAdapter;

final class MyAdapter implements HttpAdapter
{
    public function send(Request $request): Response
    {
        // Return non-2xx responses as Response objects.
        // Throw TransportException when the request cannot be completed.
    }
}
```

The response body must be a `Kami\Rqlite\Http\Body` stream for incremental
parsing.

## Error handling

All client exceptions extend `Kami\Rqlite\Exception\RqliteException`:

- `TransportException` — the request could not be completed, such as a
  connection failure or timeout.
- `HttpException` — rqlite returned a non-2xx response. The complete response
  is available as `$exception->response`.
- `StatementException` — rqlite reported an error for a specific statement.
  - `$exception->statementIndex` is the zero-based statement index.
  - `$exception->errorMessage` is the raw rqlite message.
  - `$exception->sqliteError` contains `code`, `extendedCode`, and
    `systemErrno` when SQLite reported result codes.
- `DecodingException` — the response was not valid JSON or had an unexpected
  shape.
- `RqliteException` — a request-level error, for example `no leader`.

Both the legacy `error` field and the structured `error_v2` field introduced in
rqlite v10.6.0 are supported. When both are present, `error_v2` is preferred.

The client throws on the first statement-level error instead of returning
per-statement error metadata. Use the `errors` option to control which error
fields rqlite renders; the client can decode either form.

Because responses are streamed, decoding and statement errors may be raised
while iterating `Results` or row generators rather than when `query()`,
`execute()`, or `request()` is called. HTTP and transport errors are raised
immediately.

## Handling large numbers

Integers larger than `PHP_INT_MAX` are decoded as strings to avoid precision
loss. For example, a `BIGINT` value may come back as `"9223372036854775808"`
rather than a float. Convert it to the type your schema expects.

## Not currently supported

The following rqlite features are not implemented yet:

- Non-associative query and request results (`columns` / `types` / `values`)
- Backup, load, and boot endpoints
- `/debug/vars`, `/nodes`, `/readyz`, `/remove`, and version lookup
- Built-in basic auth and TLS configuration in the Curl and File adapters
- Built-in timeout configuration in the Curl and File adapters
- Connection retry and load balancing across multiple nodes
- Single-statement helpers and typed statement builders
- Queued writes on `/db/request`
- Exposing `raft_index` and `sequence_number` from responses
- Per-statement error inspection without throwing

## Development

A minimal PHP CLI image with Composer is included for local development:

```bash
docker compose build
docker compose run --rm php composer install
docker compose run --rm php vendor/bin/pest
```

Unit tests are run by default. Integration tests are excluded from the default
run and execute against the local three-node rqlite cluster:

```bash
docker compose up -d --wait myrqlite-service-1 myrqlite-service-2 myrqlite-service-3
docker compose run --rm php vendor/bin/pest --group=integration
```

The integration test uses the compose service hostnames. When running Pest
outside Docker, point it at the published ports instead:

```bash
RQLITE_URL=http://localhost:4001 \
RQLITE_URL_2=http://localhost:4011 \
RQLITE_URL_3=http://localhost:4021 \
vendor/bin/pest --group=integration
```
