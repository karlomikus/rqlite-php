<?php

declare(strict_types=1);

use Kami\Rqlite\Rqlite;
use Kami\Rqlite\Adapter;
use Kami\Rqlite\Result\ExecuteResult;
use Kami\Rqlite\Exception\RqliteException;
use Kami\Rqlite\Result\AssociativeQueryResult;

describe('Query', function () {
    it('can create simple query', function () {
        $adapter = Mockery::mock(Adapter::class);
        $adapter->shouldReceive('post')->once()->andReturn('{"results":[{"types":["int","varchar"],"rows":[[1,"foo"]],"time":"0.001"}],"time":"0.001"}');

        $rqlite = new Rqlite($adapter);
        $result = $rqlite->query(['SELECT * FROM foo']);

        expect($result->results)->toBeArray()->toHaveCount(1);
        expect($result->time)->toBeFloat()->toBe(0.001);
        expect($result->results[0])->toBeInstanceOf(AssociativeQueryResult::class);
        expect($result->results[0]->rows)->toBeArray()->toHaveCount(1);
    });

    it('will throw exception on error', function () {
        $adapter = Mockery::mock(Adapter::class);
        $adapter->shouldReceive('post')->once()->andReturn('{"results":[{"error":"rqlite error happened"}],"time":"0.001"}');

        $rqlite = new Rqlite($adapter);
        $rqlite->query(['SELECT * FROM foo']);
    })->throws(RqliteException::class);
});

describe('Execute', function () {
    it('can execute query', function () {
        $adapter = Mockery::mock(Adapter::class);
        $adapter->shouldReceive('post')->once()->andReturn('{"results":[{"last_insert_id":1,"rows_affected":1,"time":"0.00886"}],"time":"0.0152"}');

        $rqlite = new Rqlite($adapter);
        $result = $rqlite->execute(['INSERT INTO foo(name, age) VALUES("fiona", 20)']);

        expect($result->time)->tobeFloat()->toBe(0.0152);
        expect($result->results[0])->toBeInstanceOf(ExecuteResult::class);
        expect($result->results[0]->lastInsertId)->toBe(1);
        expect($result->results[0]->rowsAffected)->toBe(1);
        expect($result->results[0]->time)->toBeFloat()->toBe(0.00886);
    });
});
