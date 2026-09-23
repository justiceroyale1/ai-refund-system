<?php

namespace App\Exceptions\AI;

use Throwable;

final class AIProviderUnavailableException extends AIProviderException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('The AI analysis provider is temporarily unavailable.', $previous);
    }
}
