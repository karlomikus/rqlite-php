<?php

declare(strict_types=1);

namespace Kami\Rqlite\Http;

use JsonException;
use Kami\Rqlite\Exception\DecodingException;

/**
 * Minimal pull-based JSON reader over a Body stream.
 *
 * It parses structure incrementally and only calls json_decode() for one value
 * at a time. This keeps large row arrays out of memory.
 */
final class JsonStream
{
    private const BUFFER_SIZE = 8192;

    private string $buffer = '';
    private int $position = 0;
    private bool $finished = false;

    public function __construct(private readonly Body $body)
    {
    }

    public function beginObject(): void
    {
        $this->expect('{');
    }

    public function endObject(): void
    {
        $this->expect('}');
    }

    public function beginArray(): void
    {
        $this->expect('[');
    }

    public function endArray(): void
    {
        $this->expect(']');
    }

    /**
     * @param non-empty-string $end
     */
    public function hasMore(string $end): bool
    {
        $this->skipWhitespace();

        return $this->peek() !== $end;
    }

    public function readKey(): string
    {
        $key = $this->readStringValue();
        $this->expect(':');

        return $key;
    }

    public function readValue(): mixed
    {
        $raw = $this->readRawValue();

        try {
            return json_decode(
                $raw,
                true,
                512,
                JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING,
            );
        } catch (JsonException $exception) {
            throw new DecodingException('Invalid JSON value from rqlite.', 0, $exception);
        }
    }

    public function skipValue(): void
    {
        $this->skipWhitespace();
        $first = $this->peek();

        if ($first === null) {
            throw new DecodingException('Unexpected end of JSON input.');
        }

        if ($first === '"') {
            $this->skipString();

            return;
        }

        if ($first === '{' || $first === '[') {
            $this->skipContainer();

            return;
        }

        $this->readRawScalar();
    }

    /**
     * Consume the comma after a value, or leave the closing delimiter in place.
     *
     * @param non-empty-string $end
     */
    public function separator(string $end): void
    {
        $this->skipWhitespace();
        $char = $this->peek();

        if ($char === ',') {
            $this->position++;

            return;
        }

        if ($char === $end) {
            return;
        }

        throw new DecodingException(sprintf(
            'Expected "," or "%s" but found "%s".',
            $end,
            $char ?? 'end of input',
        ));
    }

    public function ensureEnd(): void
    {
        $this->skipWhitespace();

        if ($this->peek() !== null) {
            throw new DecodingException('Unexpected trailing data in the rqlite response.');
        }
    }

    private function expect(string $expected): void
    {
        $this->skipWhitespace();
        $actual = $this->take();

        if ($actual !== $expected) {
            throw new DecodingException(sprintf(
                'Expected "%s" but found "%s".',
                $expected,
                $actual,
            ));
        }
    }

    private function readStringValue(): string
    {
        $raw = $this->readRawString();

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new DecodingException('Invalid JSON string from rqlite.', 0, $exception);
        }

        if (!is_string($decoded)) {
            throw new DecodingException('Expected a JSON string.');
        }

        return $decoded;
    }

    private function readRawValue(): string
    {
        $this->skipWhitespace();
        $first = $this->peek();

        if ($first === null) {
            throw new DecodingException('Unexpected end of JSON input.');
        }

        if ($first === '"') {
            return $this->readRawString();
        }

        if ($first === '{' || $first === '[') {
            return $this->readRawContainer();
        }

        return $this->readRawScalar();
    }

    private function readRawString(): string
    {
        $raw = $this->take();

        if ($raw !== '"') {
            throw new DecodingException('Expected a JSON string.');
        }

        $escaped = false;

        while (true) {
            $char = $this->take();
            $raw .= $char;

            if ($escaped) {
                $escaped = false;

                continue;
            }

            if ($char === '\\') {
                $escaped = true;

                continue;
            }

            if ($char === '"') {
                return $raw;
            }
        }
    }

    private function readRawContainer(): string
    {
        $opening = $this->take();
        $closing = $opening === '{' ? '}' : ']';
        $raw = $opening;
        $expectedClosings = [$closing];
        $inString = false;
        $escaped = false;

        while ($expectedClosings !== []) {
            $char = $this->take();
            $raw .= $char;

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === '{') {
                $expectedClosings[] = '}';

                continue;
            }

            if ($char === '[') {
                $expectedClosings[] = ']';

                continue;
            }

            if ($char === '}' || $char === ']') {
                $expected = array_pop($expectedClosings);

                if ($char !== $expected) {
                    throw new DecodingException('Mismatched JSON container delimiters.');
                }
            }
        }

        return $raw;
    }

    private function readRawScalar(): string
    {
        $raw = '';

        while (($char = $this->peek()) !== null && !in_array($char, [' ', "\t", "\r", "\n", ',', '}', ']'], true)) {
            $raw .= $this->take();
        }

        if ($raw === '') {
            throw new DecodingException('Unexpected end of JSON input.');
        }

        return $raw;
    }

    private function skipString(): void
    {
        $this->expect('"');
        $escaped = false;

        while (true) {
            $char = $this->take();

            if ($escaped) {
                $escaped = false;

                continue;
            }

            if ($char === '\\') {
                $escaped = true;

                continue;
            }

            if ($char === '"') {
                return;
            }
        }
    }

    private function skipContainer(): void
    {
        $opening = $this->take();

        if ($opening !== '{' && $opening !== '[') {
            throw new DecodingException('Expected a JSON container.');
        }

        $expectedClosings = [$opening === '{' ? '}' : ']'];
        $inString = false;
        $escaped = false;

        while ($expectedClosings !== []) {
            $char = $this->take();

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === '{') {
                $expectedClosings[] = '}';

                continue;
            }

            if ($char === '[') {
                $expectedClosings[] = ']';

                continue;
            }

            if ($char === '}' || $char === ']') {
                $expected = array_pop($expectedClosings);

                if ($char !== $expected) {
                    throw new DecodingException('Mismatched JSON container delimiters.');
                }
            }
        }
    }

    private function skipWhitespace(): void
    {
        while (($char = $this->peek()) !== null && ($char === ' ' || $char === "\n" || $char === "\r" || $char === "\t")) {
            $this->position++;
        }
    }

    private function peek(): ?string
    {
        if (!$this->fill()) {
            return null;
        }

        return $this->buffer[$this->position];
    }

    private function take(): string
    {
        $char = $this->peek();

        if ($char === null) {
            throw new DecodingException('Unexpected end of JSON input.');
        }

        $this->position++;

        return $char;
    }

    private function fill(): bool
    {
        if ($this->position < strlen($this->buffer)) {
            return true;
        }

        if ($this->finished) {
            return false;
        }

        while (true) {
            $chunk = $this->body->read(self::BUFFER_SIZE);

            if ($chunk !== '') {
                $this->buffer = $chunk;
                $this->position = 0;

                return true;
            }

            if ($this->body->eof()) {
                $this->finished = true;

                return false;
            }
        }
    }
}
