<?php

declare(strict_types=1);

use Kami\Rqlite\Http\Response;

it('reports whether the status code is successful', function () {
    expect((new Response(200))->isSuccessful())->toBeTrue();
    expect((new Response(299))->isSuccessful())->toBeTrue();
    expect((new Response(199))->isSuccessful())->toBeFalse();
    expect((new Response(300))->isSuccessful())->toBeFalse();
    expect((new Response(500))->isSuccessful())->toBeFalse();
});

it('reads headers case-insensitively', function () {
    $response = new Response(200, [
        'Content-Type' => ['application/json'],
        'X-Test' => 'yes',
    ]);

    expect($response->header('content-type'))->toBe('application/json');
    expect($response->header('x-test'))->toBe('yes');
    expect($response->header('missing'))->toBeNull();
});
