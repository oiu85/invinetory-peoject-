<?php

namespace App\Exceptions;

use Exception;

class LayoutGenerationException extends Exception
{
    public function __construct(
        string $message = 'Failed to generate layout',
        public ?string $algorithm = null,
        public ?array $details = null
    ) {
        parent::__construct($message, 500);
    }
}
