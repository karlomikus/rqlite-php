<?php

declare(strict_types=1);

namespace Kami\Rqlite\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * A readable response body backed by a PHP stream resource or a PSR-7 stream.
 *
 * The body is kept as a stream so large responses can be parsed incrementally
 * instead of being loaded into a string first. It implements PSR-7's
 * StreamInterface so bodies interoperate with other PSR-7 aware libraries.
 *
 * The body is read-only: write() throws and isWritable() reports false. The
 * stream is closed automatically when the body is garbage collected.
 */
final class Body implements StreamInterface
{
    /** @var resource|StreamInterface|null */
    private $stream;

    /**
     * @param resource|StreamInterface $stream
     */
    public function __construct($stream)
    {
        if (!is_resource($stream) && !$stream instanceof StreamInterface) {
            throw new InvalidArgumentException('The response body must be a stream resource or a PSR-7 stream.');
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
        $stream = $this->stream;

        if ($stream === null) {
            return '';
        }

        if ($stream instanceof StreamInterface) {
            return $stream->read(max(1, $length));
        }

        $chunk = fread($stream, max(1, $length));

        return $chunk === false ? '' : $chunk;
    }

    public function eof(): bool
    {
        $stream = $this->stream;

        if ($stream === null) {
            return true;
        }

        if ($stream instanceof StreamInterface) {
            return $stream->eof();
        }

        return feof($stream);
    }

    /**
     * Read the remaining contents from the current position.
     */
    public function contents(): string
    {
        $stream = $this->stream;

        if ($stream === null) {
            return '';
        }

        if ($stream instanceof StreamInterface) {
            return $stream->getContents();
        }

        $contents = stream_get_contents($stream);

        return $contents === false ? '' : $contents;
    }

    public function close(): void
    {
        $stream = $this->stream;

        if ($stream instanceof StreamInterface) {
            $stream->close();
        } elseif (is_resource($stream)) {
            fclose($stream);
        }

        $this->stream = null;
    }

    /**
     * @return resource|null
     */
    public function detach()
    {
        $stream = $this->stream;
        $this->stream = null;

        if ($stream instanceof StreamInterface) {
            $detached = $stream->detach();

            return is_resource($detached) ? $detached : null;
        }

        if (is_resource($stream)) {
            return $stream;
        }

        return null;
    }

    public function getSize(): ?int
    {
        $stream = $this->stream;

        if ($stream === null) {
            return null;
        }

        if ($stream instanceof StreamInterface) {
            return $stream->getSize();
        }

        $stats = fstat($stream);

        return $stats === false ? null : $stats['size'];
    }

    public function tell(): int
    {
        $stream = $this->activeStream();

        if ($stream instanceof StreamInterface) {
            return $stream->tell();
        }

        $position = ftell($stream);

        if ($position === false) {
            throw new RuntimeException('Unable to determine the position of the response body stream.');
        }

        return $position;
    }

    public function isSeekable(): bool
    {
        $stream = $this->stream;

        if ($stream === null) {
            return false;
        }

        if ($stream instanceof StreamInterface) {
            return $stream->isSeekable();
        }

        $meta = stream_get_meta_data($stream);

        return $meta['seekable'];
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $stream = $this->activeStream();

        if ($stream instanceof StreamInterface) {
            $stream->seek($offset, $whence);

            return;
        }

        $seeked = fseek($stream, $offset, $whence);

        if ($seeked !== 0) {
            throw new RuntimeException('Unable to seek the response body stream.');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new RuntimeException('The response body is read-only.');
    }

    public function isReadable(): bool
    {
        return $this->stream !== null;
    }

    public function getContents(): string
    {
        return $this->contents();
    }

    /**
     * @return array|mixed|null
     */
    public function getMetadata(?string $key = null)
    {
        $stream = $this->stream;

        if ($stream === null) {
            return $key === null ? [] : null;
        }

        if ($stream instanceof StreamInterface) {
            return $stream->getMetadata($key);
        }

        $meta = stream_get_meta_data($stream);

        return $key === null ? $meta : ($meta[$key] ?? null);
    }

    public function __toString(): string
    {
        if ($this->stream === null || !$this->isSeekable()) {
            return $this->contents();
        }

        $position = $this->tell();
        $this->rewind();
        $contents = $this->contents();
        $this->seek($position);

        return $contents;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @return resource|StreamInterface
     */
    private function activeStream()
    {
        $stream = $this->stream;

        if ($stream === null) {
            throw new RuntimeException('The response body stream is detached.');
        }

        return $stream;
    }
}
