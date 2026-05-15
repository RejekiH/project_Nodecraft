<?php

namespace App\Modules\RoomService\Exceptions;

use RuntimeException;

/**
 * RoomException
 *
 * Exception untuk error terkait logika room:
 *   - Room tidak ditemukan
 *   - Room sudah penuh
 *   - User sudah berada di room lain
 *   - Status room tidak valid untuk operasi
 */
class RoomException extends RuntimeException
{
    public function __construct(string $message, int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
