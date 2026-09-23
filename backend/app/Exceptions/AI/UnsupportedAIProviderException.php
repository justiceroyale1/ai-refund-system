<?php

namespace App\Exceptions\AI;

final class UnsupportedAIProviderException extends AIProviderException
{
    public function __construct()
    {
        parent::__construct('The configured AI analysis provider is not available.');
    }
}
