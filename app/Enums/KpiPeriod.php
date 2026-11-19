<?php

namespace App\Enums;

enum KpiPeriod: string
{
    case Week = 'week';
    case Month = 'month';
    case Quarter = 'quarter';
}
