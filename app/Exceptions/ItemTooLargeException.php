<?php

namespace App\Exceptions;

use Exception;

class ItemTooLargeException extends Exception
{
    public function __construct(
        string $message = 'Item is too large to fit in the room',
        public ?int $productId = null,
        public ?array $itemDimensions = null,
        public ?array $roomDimensions = null
    ) {
        parent::__construct($message, 400);
    }
}
