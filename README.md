# PHP Client for Rqlite

A basic rqlite client for PHP. Supports multiple HTTP adapters (Curl, Guzzle, Basic PHP file stream). Returns strongly typed results. [Rqlite](https://rqlite.io) is a lightweight, distributed relational database, which uses SQLite as its storage engine.

## Installation

```bash
composer require karlomikus/rqlite
```

## Usage

```php
<?php

use Kami\Rqlite\Rqlite;
use Kami\Rqlite\Adapters\Curl;

// Create a http adapter and point it to your rqlite server
// You can create your own adapter by implementing the HttpAdapter interface
$curlAdapter = new Curl('http://localhost:4001');

// Create a Rqlite client instance and pass the adapter as a constructor argument
$rqlite = new Rqlite($curlAdapter);

// Run your SQL queries
// Supports status(), query() and execute() endpoints
$results = $rqlite->query([
    'SELECT * FROM foo',
]);

// Results and rows are streamed as generators
foreach ($results as $result) {
    foreach ($result->rows as $row) {
        var_dump($row);
    }
}

// The top-level time is available after the stream has been fully consumed
var_dump($results->time);
```

## Query options

`query()` accepts a `QueryOptions` object. Options with a `false` or `null`
value are omitted from the request.

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

The available options are `timings` (off by default), `pretty`, `blobAsArray`,
`readConsistencyLevel`, `timeout`, `linearizableTimeout`, `freshness`,
`freshnessStrict`, `raftIndex`, and `errors`. Durations accept Go duration
strings such as `500ms` or `2s`, or a number of seconds.

Results are always requested in associative form.

## Typed rows

`Results` and `AssociativeQueryResult` are generic, so PHPStan (or any tool
that understands generics) can narrow query rows to your own array shape:

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

When no type is specified, rows default to `array<string, mixed>`. A single
result can be annotated the same way with
`AssociativeQueryResult<array{...}>`.

## Streaming

Response bodies are parsed incrementally, so large result sets do not need to be
loaded into memory in full.

- `query()` and `execute()` return `Results`, which is a single-pass iterator.
- Query results and their rows are generators. Consume a result's rows before
  advancing to the next result.
- The top-level `Results::$time` and each query result's `time` are populated
  after the corresponding stream has been consumed.
- Do not collect query results with `iterator_to_array($results)` without
  consuming their rows first. Iterate with nested `foreach` loops instead.

Custom adapters should return a `Kami\Rqlite\Http\Response` whose body is a
`Kami\Rqlite\Http\Body` stream. The Curl adapter spools to `php://temp`, while
the Guzzle and File adapters stream the HTTP response directly.

## Error handling

All client exceptions extend `Kami\Rqlite\Exception\RqliteException`:

- `TransportException` — the request could not be completed (connection failure, timeout, etc.).
- `HttpException` — rqlite returned a non-2xx response. The response is available as `$exception->response`.
- `StatementException` — rqlite reported an error for a specific SQL statement. The zero-based statement index is available as `$exception->statementIndex` and the raw message as `$exception->errorMessage`. If SQLite reported result codes, `$exception->sqliteError` contains `code`, `extendedCode`, and `systemErrno`.
- `DecodingException` — the response body was not valid JSON or had an unexpected shape.
- `RqliteException` — rqlite returned a request-level error (for example `no leader`) or another client-level failure.

Both the legacy `error` field and the structured `error_v2` field introduced in
rqlite v10.6.0 are supported.

Because responses are streamed, decoding and statement errors are raised while
iterating the `Results` and row generators, not necessarily when `query()` or
`execute()` is called. HTTP and transport errors are still raised immediately.

## Handling numbers

Integers larger than `PHP_INT_MAX` are decoded as strings to avoid precision loss.
For example, a `BIGINT` value may come back as `"9223372036854775808"` rather than a
float. Convert it to the type your schema expects.

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
