<?php

declare(strict_types=1);

use Kami\Rqlite\Http\Body;

it('reads a body stream incrementally', function () {
    $body = Body::fromString('{"results":[]}');

    expect($body->eof())->toBeFalse();
    expect($body->read(4))->toBe('{"re');
    expect($body->read(4))->toBe('sult');
    expect($body->read(4))->toBe('s":[');
    expect($body->read(4))->toBe(']}');
    expect($body->read(4))->toBe('');
    expect($body->eof())->toBeTrue();
});

it('returns the remaining contents and can be closed', function () {
    $body = Body::fromString('streamed-body');

    expect($body->contents())->toBe('streamed-body');

    $body->close();

    expect($body->contents())->toBe('');
    expect($body->eof())->toBeTrue();
});
