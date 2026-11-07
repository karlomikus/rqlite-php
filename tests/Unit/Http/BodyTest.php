<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Utils;
use Kami\Rqlite\Http\Body;
use Psr\Http\Message\StreamInterface;

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

it('implements the PSR-7 stream interface', function () {
    $body = Body::fromString('streamed-body');

    expect($body)->toBeInstanceOf(StreamInterface::class);
    expect($body->getSize())->toBe(13);
    expect($body->isReadable())->toBeTrue();
    expect($body->isWritable())->toBeFalse();
    expect($body->isSeekable())->toBeTrue();
    expect($body->tell())->toBe(0);
    expect((string) $body)->toBe('streamed-body');
    expect($body->tell())->toBe(0);

    expect(fn () => $body->write('no'))->toThrow(RuntimeException::class);
});

it('supports seeking and reads the remaining contents', function () {
    $body = Body::fromString('streamed-body');

    $body->seek(9);
    expect($body->tell())->toBe(9);
    expect($body->getContents())->toBe('body');
    expect($body->eof())->toBeTrue();
});

it('reads a non seekable stream from the current position as a string', function () {
    $body = new Body(new NoSeekStream(Utils::streamFor('streamed-body')));

    expect($body->isSeekable())->toBeFalse();
    expect((string) $body)->toBe('streamed-body');
});

it('accepts a PSR-7 stream', function () {
    $body = new Body(Utils::streamFor('psr-7 body'));

    expect($body->read(5))->toBe('psr-7');
    expect($body->contents())->toBe(' body');
    expect($body->eof())->toBeTrue();
    expect($body->getSize())->toBe(10);
});

it('can be detached back to a resource', function () {
    $body = Body::fromString('detached-body');

    $resource = $body->detach();

    expect($resource)->toBeResource();
    expect($body->eof())->toBeTrue();
    expect($body->read(4))->toBe('');
});

it('rejects invalid stream input', function () {
    new Body('not a stream');
})->throws(InvalidArgumentException::class, 'The response body must be a stream resource or a PSR-7 stream.');
