<?php

namespace App\Exceptions;

/**
 * UserException - Untuk error operasi user (404, 409, 400)
 */
class UserException extends \RuntimeException
{
    public function __construct(string $message, int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
