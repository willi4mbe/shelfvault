<?php

namespace App\Enums;

enum ItemStatus: string
{
    case Owned = 'owned';
    case Loaned = 'loaned';
    case Wanted = 'wanted';
    case Archived = 'archived';
}
