# PHP Client for Rqlite

WIP

## Installation

```bash
composer require karlomikus/rqlite
```

## Usage

```php
<?php

use Kami\Rqlite\Rqlite;
use Kami\Rqlite\Adapters\Curl;

// 1. Create a http adapter and point it to your rqlite server, curl and guzzle are provided
$curlAdapter = new Curl('http://localhost:4001');

// 2. Create a Rqlite client instance
$rqlite = new Rqlite($curlAdapter);

// 3. Run queries
$result = $rqlite->query([
    'SELECT * FROM foo',
]);

// 4. Inspect the result
// $result is an instance of either Kami\Rqlite\Result\ExecuteResult or Kami\Rqlite\Result\AssociativeQueryResult
var_dump($result);
```