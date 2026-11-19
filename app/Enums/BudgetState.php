<?php

namespace App\Enums;

enum BudgetState: string
{
    case Normal = 'normal';     // < 80 %
    case Degraded = 'degraded'; // >= 80 %: non-priority work waits, priority work may drop a tier
    case Blocked = 'blocked';   // >= 100 %: only critical work, on the cheapest model
}
