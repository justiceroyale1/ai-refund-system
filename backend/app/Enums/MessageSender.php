<?php

namespace App\Enums;

enum MessageSender: string
{
    case Customer = 'customer';
    case Assistant = 'assistant';
    case System = 'system';
}
