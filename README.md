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
// You can create your own adapter by implementing the Adapter interface
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
