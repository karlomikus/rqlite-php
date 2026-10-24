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
- `StatementException` — rqlite reported an error for a specific SQL statement. The zero-based statement index is available as `$exception->statementIndex`.
- `DecodingException` — the response body was not valid JSON or had an unexpected shape.
