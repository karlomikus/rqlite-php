<?php

declare(strict_types=1);

use Kami\Rqlite\Http\Body;
use Kami\Rqlite\Http\JsonStream;

it('streams object values and preserves large integers', function () {
    $json = new JsonStream(Body::fromString(
        '{"results":[{"types":{"id":"integer"},"rows":[{"id":9223372036854775808,"note":"a \\"quoted\\" value"}]}],"time":"0.001"}',
    ));

    $json->beginObject();

    expect($json->readKey())->toBe('results');
    $json->beginArray();
    $json->beginObject();

    expect($json->readKey())->toBe('types');
    expect($json->readValue())->toBe(['id' => 'integer']);

    $json->separator('}');

    expect($json->readKey())->toBe('rows');
    $json->beginArray();
    $row = $json->readValue();
    $json->separator(']');
    $json->endArray();

    $json->separator('}');
    $json->endObject();
    $json->separator(']');
    $json->endArray();
    $json->separator('}');

    expect($json->readKey())->toBe('time');
    expect($json->readValue())->toBe('0.001');
    $json->separator('}');
    $json->endObject();
    $json->ensureEnd();

    expect($row)->toBe([
        'id' => '9223372036854775808',
        'note' => 'a "quoted" value',
    ]);
});

it('skips unknown values without decoding them', function () {
    $json = new JsonStream(Body::fromString(
        '{"skip":{"nested":["value",{"deep":true}]},"keep":42}',
    ));

    $json->beginObject();
    expect($json->readKey())->toBe('skip');
    $json->skipValue();
    $json->separator('}');

    expect($json->readKey())->toBe('keep');
    expect($json->readValue())->toBe(42);
    $json->separator('}');
    $json->endObject();
    $json->ensureEnd();
});
