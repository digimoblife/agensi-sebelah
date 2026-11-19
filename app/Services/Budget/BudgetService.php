<?php

namespace App\Services\Budget;

use App\Enums\BudgetState;
use App\Enums\TaskPriority;
use App\Models\Budget;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Monthly per-client AI budget in USD (PRD principle 10, F8.3). */
class BudgetService
{
    public function current(Client $client, ?CarbonImmutable $at = null): Budget
    {
        $month = ($at ?? CarbonImmutable::now())->setTimezone($client->timezone)->startOfMonth()->toDateString();

        return Budget::query()->firstOrCreate(
            ['client_id' => $client->id, 'month' => $month],
            [
                'workspace_id' => $client->workspace_id,
                'llm_limit_usd' => $client->llm_budget_usd ?? config('llm.budget.default_llm_limit_usd'),
                'media_limit_usd' => $client->media_budget_usd ?? config('llm.budget.default_media_limit_usd'),
            ],
        );
    }

    public function decide(Budget $budget, TaskPriority $priority, float $estimatedCost, bool $allowDowngrade): BudgetDecision
    {
        $state = $budget->llmState();
        $exceedsRemaining = (float) $budget->llm_spent_usd + $estimatedCost > (float) $budget->llm_limit_usd;

        return match (true) {
            $priority === TaskPriority::Critical => new BudgetDecision(true, $state !== BudgetState::Normal || $exceedsRemaining, $state, 'critical'),
            $state === BudgetState::Blocked => new BudgetDecision(false, false, $state, 'blocked'),
            $priority === TaskPriority::Normal && ($state === BudgetState::Degraded || $exceedsRemaining) => new BudgetDecision(false, false, $state, 'deferred_non_priority'),
            $state === BudgetState::Degraded || $exceedsRemaining => $allowDowngrade
                ? new BudgetDecision(true, true, $state, 'priority_downgraded')
                : new BudgetDecision(true, false, $state, 'priority'),
            default => new BudgetDecision(true, false, $state, 'ok'),
        };
    }

    /** Adds spend atomically and fires the 80 % / 100 % events once each. */
    public function recordLlmSpend(Budget $budget, float $cost): void
    {
        if ($cost <= 0) {
            return;
        }

        $events = DB::transaction(function () use ($budget, $cost) {
            /** @var Budget $locked */
            $locked = Budget::query()->lockForUpdate()->findOrFail($budget->id);
            $locked->llm_spent_usd = (string) ((float) $locked->llm_spent_usd + $cost);

            $events = [];
            $ratio = (float) $locked->llm_limit_usd > 0 ? (float) $locked->llm_spent_usd / (float) $locked->llm_limit_usd : 1.0;
            if ($ratio >= (float) config('llm.budget.alert_ratio') && $locked->alert_80_sent_at === null) {
                $locked->alert_80_sent_at = CarbonImmutable::now();
                $events[] = 80;
            }
            if ($ratio >= 1.0 && $locked->alert_100_sent_at === null) {
                $locked->alert_100_sent_at = CarbonImmutable::now();
                $events[] = 100;
            }
            $locked->save();
            $budget->setRawAttributes($locked->getAttributes(), true);

            return $events;
        });

        foreach ($events as $percent) {
            BudgetThresholdReached::dispatch($budget, $percent);
        }
    }
}
