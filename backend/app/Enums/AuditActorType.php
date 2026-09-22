<?php

namespace App\Enums;

enum AuditActorType: string
{
    case Customer = 'customer';
    case User = 'user';
    case System = 'system';
}
