<?php

namespace App\Listeners;

use App\Services\Budget\BudgetThresholdReached;
use App\Services\Telegram\AdminNotifier;

class NotifyBudgetThreshold
{
    public function __construct(private readonly AdminNotifier $admins) {}

    public function handle(BudgetThresholdReached $event): void
    {
        $budget = $event->budget;
        $this->admins->send(__('bot.admin_budget', [
            'client' => e((string) $budget->client?->name),
            'percent' => $event->percent,
            'spent' => number_format((float) $budget->llm_spent_usd, 2),
            'limit' => number_format((float) $budget->llm_limit_usd, 2),
        ]));
    }
}
