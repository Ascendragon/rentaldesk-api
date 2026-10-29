<?php

namespace App\Enums;

enum AssetStatus: string
{
    case ACTIVE = 'active';

    case MAINTENANCE = 'maintenance';

    case RETIRED = 'retired';
}
