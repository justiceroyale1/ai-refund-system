<?php

namespace App\Exceptions\Payments;

use RuntimeException;

final class UnsupportedPaymentProcessorException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The configured payment processor is not available.');
    }
}
