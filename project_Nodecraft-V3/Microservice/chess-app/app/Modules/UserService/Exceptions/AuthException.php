<?php

namespace App\Modules\UserService\Exceptions;

/**
 * AuthException — error autentikasi (401, 403)
 */
class AuthException extends \RuntimeException
{
    public function __construct(string $message, int $code = 401, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
