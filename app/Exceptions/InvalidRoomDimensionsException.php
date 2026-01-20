<?php

namespace App\Exceptions;

use Exception;

class InvalidRoomDimensionsException extends Exception
{
    public function __construct(string $message = 'Invalid room dimensions provided')
    {
        parent::__construct($message, 400);
    }
}
