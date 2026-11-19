<?php

namespace App\Services\Budget;

use App\Models\Budget;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired once per month per threshold (80 % alert, 100 % fallback). The Telegram alert listener comes in week 4. */
final class BudgetThresholdReached
{
    use Dispatchable;

    public function __construct(public readonly Budget $budget, public readonly int $percent) {}
}
