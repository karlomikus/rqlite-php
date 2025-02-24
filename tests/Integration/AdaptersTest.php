<?php

declare(strict_types=1);

use Kami\Rqlite\Rqlite;
use Kami\Rqlite\Adapter;
use Kami\Rqlite\Adapters\Curl;
use Kami\Rqlite\Adapters\File;
use Kami\Rqlite\Adapters\Guzzle;

describe('Adapters', function () {
    it('can connect to rqlite via adapter', function (Adapter $adapter) {
        $rqlite = new Rqlite($adapter);
        $res = $rqlite->query(['SELECT 1 + 1 AS result']);

        expect($res->time)->toBeFloat();
        expect($res->results[0]->rows[0]['result'])->toBe(2);
    })->with([
        fn() => new Curl($_ENV['DB_URL']),
        fn() => new File($_ENV['DB_URL']),
        function () {
            $guzzleClient = new \GuzzleHttp\Client([
                'base_uri' => $_ENV['DB_URL'],
            ]);

            return new Guzzle($guzzleClient);
        },
    ]);
});
