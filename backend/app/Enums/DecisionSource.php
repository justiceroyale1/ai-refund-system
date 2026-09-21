<?php

namespace App\Enums;

enum DecisionSource: string
{
    case PolicyEngine = 'policy_engine';
    case Human = 'human';
}
