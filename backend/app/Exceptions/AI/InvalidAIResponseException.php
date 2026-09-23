<?php

namespace App\Exceptions\AI;

use Throwable;

final class InvalidAIResponseException extends AIProviderException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('The AI analysis provider returned an invalid response.', $previous);
    }
}
