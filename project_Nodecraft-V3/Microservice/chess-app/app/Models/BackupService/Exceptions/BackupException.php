<?php

namespace App\Modules\BackupService\Exceptions;

use RuntimeException;

/**
 * BackupException
 * Dilempar saat proses backup gagal.
 */
class BackupException extends RuntimeException
{
    public function __construct(string $message, int $code = 500)
    {
        parent::__construct($message, $code);
    }
}
