# PHP Client for Rqlite

A basic rqlite client for PHP. Supports multiple HTTP adapters (Curl, Guzzle, Basic PHP file stream). Returns strongly typed results. [Rqlite](https://rqlite.io) is a lightweight, distributed relational database, which uses SQLite as its storage engine.

## Installation

```bash
composer require karlomikus/rqlite
```

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
$result = $rqlite->query([
    'SELECT * FROM foo',
]);

// Get the results
// By default the results from query endpoint are fetch as associative arrays
var_dump($result);
```

## Error handling

All client exceptions extend `Kami\Rqlite\Exception\RqliteException`:

- `TransportException` — the request could not be completed (connection failure, timeout, etc.).
- `HttpException` — rqlite returned a non-2xx response. The response is available as `$exception->response`.
- `StatementException` — rqlite reported an error for a specific SQL statement. The zero-based statement index is available as `$exception->statementIndex` and the raw message as `$exception->errorMessage`. If SQLite reported result codes, `$exception->sqliteError` contains `code`, `extendedCode`, and `systemErrno`.
- `DecodingException` — the response body was not valid JSON or had an unexpected shape.
- `RqliteException` — rqlite returned a request-level error (for example `no leader`) or another client-level failure.

Both the legacy `error` field and the structured `error_v2` field introduced in
rqlite v10.6.0 are supported.

## Handling numbers

Integers larger than `PHP_INT_MAX` are decoded as strings to avoid precision loss.
For example, a `BIGINT` value may come back as `"9223372036854775808"` rather than a
float. Convert it to the type your schema expects.
