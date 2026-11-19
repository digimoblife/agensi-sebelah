<?php

namespace App\Services\Budget;

use App\Enums\BudgetState;

final readonly class BudgetDecision
{
    public function __construct(
        public bool $allowed,
        public bool $downgrade,
        public BudgetState $state,
        public string $reason,
    ) {}
}
