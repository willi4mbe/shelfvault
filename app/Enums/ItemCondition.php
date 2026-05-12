<?php

namespace App\Enums;

enum ItemCondition: string
{
    case New = 'new';
    case VeryGood = 'very_good';
    case Good = 'good';
    case Acceptable = 'acceptable';
    case Damaged = 'damaged';
}
