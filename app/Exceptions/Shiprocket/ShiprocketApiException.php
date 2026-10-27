<?php

namespace App\Exceptions\Shiprocket;

use Exception;

class ShiprocketApiException extends Exception
{
    public function __construct(
        string $message = 'Shiprocket API error',
        int $code = 0,
        protected ?array $responsePayload = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getResponsePayload(): ?array
    {
        return $this->responsePayload;
    }
}
