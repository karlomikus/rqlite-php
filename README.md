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
use Kami\Rqlite\Adapters\Guzzle;

$rqlite = new Rqlite(new Guzzle(new Client()));

$result = $rqlite->query([
    'SELECT * FROM foo',
]);

var_dump($result);
```