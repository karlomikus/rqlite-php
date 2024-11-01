<?php

declare(strict_types=1);

use Kami\Rqlite\Rqlite;
use Kami\Rqlite\Adapter;
use Kami\Rqlite\Exception\RqliteException;

describe('Query', function () {
    it('can create simple query', function () {
        $adapter = Mockery::mock(Adapter::class);
        $adapter->shouldReceive('post')->once()->andReturn('{"results":[{"types":["int","varchar"],"rows":[[1,"foo"]],"time":"0.001"}],"time":"0.001"}');

        $rqlite = new Rqlite($adapter);
        $result = $rqlite->query(['SELECT * FROM foo']);

        expect($result->results)->toBeArray()->toHaveCount(1);
        expect($result->time)->toBeFloat()->toBe(0.001);
        expect($result->results[0]->rows)->toBeArray()->toHaveCount(1);
    });

    it('will throw exception on error', function () {
        $adapter = Mockery::mock(Adapter::class);
        $adapter->shouldReceive('post')->once()->andReturn('{"results":[{"error":"rqlite error happened"}],"time":"0.001"}');

        $rqlite = new Rqlite($adapter);
        $rqlite->query(['SELECT * FROM foo']);
    })->throws(RqliteException::class);
});
