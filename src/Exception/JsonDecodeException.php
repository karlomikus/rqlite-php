<?php

declare(strict_types=1);

namespace Kami\Rqlite\Exception;

use Exception;

/**
 * Thrown when the response from rqlite contains invalid JSON
 */
class JsonDecodeException extends Exception
{
    private string $response;

    public function setResponse(string $response): self
    {
        $this->response = $response;

        return $this;
    }

    public function getResponse(): string
    {
        return $this->response;
    }
}
