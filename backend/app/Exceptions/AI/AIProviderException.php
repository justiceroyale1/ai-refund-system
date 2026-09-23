<?php

namespace App\Exceptions\AI;

use RuntimeException;
use Throwable;

abstract class AIProviderException extends RuntimeException
{
    protected function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
