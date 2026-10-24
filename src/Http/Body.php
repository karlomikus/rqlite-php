<?php

declare(strict_types=1);

namespace Kami\Rqlite\Http;

use InvalidArgumentException;
use RuntimeException;

/**
 * A readable response body backed by a PHP stream.
 *
 * The body is kept as a stream so large responses can be parsed incrementally
 * instead of being loaded into a string first.
 */
final class Body
{
    /** @var resource|null */
    private $stream;

    /**
     * @param resource $stream
     */
    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('The response body must be a stream resource.');
        }

        $this->stream = $stream;
    }

    public static function fromString(string $contents): self
    {
        $stream = fopen('php://temp', 'r+b');

        if ($stream === false) {
            throw new RuntimeException('Unable to create a temporary response body stream.');
        }

        if ($contents !== '') {
            fwrite($stream, $contents);
        }

        rewind($stream);

        return new self($stream);
    }

    public function read(int $length = 8192): string
    {
        if ($this->stream === null) {
            return '';
        }

        $chunk = fread($this->stream, max(1, $length));

        return $chunk === false ? '' : $chunk;
    }

    public function eof(): bool
    {
        return $this->stream === null || feof($this->stream);
    }

    /**
     * Read the remaining contents from the current position.
     */
    public function contents(): string
    {
        if ($this->stream === null) {
            return '';
        }

        $contents = stream_get_contents($this->stream);

        return $contents === false ? '' : $contents;
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->stream = null;
    }

    public function __destruct()
    {
        $this->close();
    }
}
