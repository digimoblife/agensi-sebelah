<?php

use App\Enums\BudgetState;
use App\Enums\TaskPriority;
use App\Services\Budget\BudgetService;
use App\Services\Budget\BudgetThresholdReached;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

it('creates the monthly budget in the client timezone with defaults', function () {
    $client = client();

    // 31 Dec 18:00 UTC is already 1 Jan in Jakarta.
    $budget = app(BudgetService::class)->current($client, CarbonImmutable::parse('2026-12-31 18:00:00', 'UTC'));

    expect($budget->month->toDateString())->toBe('2027-01-01')
        ->and((float) $budget->llm_limit_usd)->toBe(15.0);
});

it('decides per state and priority', function (float $spent, TaskPriority $priority, bool $allowed, bool $downgrade) {
    $budget = app(BudgetService::class)->current(client());
    $budget->update(['llm_limit_usd' => 10, 'llm_spent_usd' => $spent]);

    $decision = app(BudgetService::class)->decide($budget->fresh(), $priority, 0.01, allowDowngrade: true);

    expect($decision->allowed)->toBe($allowed)->and($decision->downgrade)->toBe($downgrade);
})->with([
    'normal' => [1, TaskPriority::Normal, true, false],
    'degraded normal waits' => [8.5, TaskPriority::Normal, false, false],
    'degraded priority drops a tier' => [8.5, TaskPriority::Priority, true, true],
    'blocked priority stops' => [10, TaskPriority::Priority, false, false],
    'blocked critical runs cheapest' => [10, TaskPriority::Critical, true, true],
]);

it('records spend and fires each threshold event once', function () {
    Event::fake([BudgetThresholdReached::class]);
    $service = app(BudgetService::class);
    $budget = $service->current(client());
    $budget->update(['llm_limit_usd' => 1]);

    $service->recordLlmSpend($budget, 0.85);
    $service->recordLlmSpend($budget, 0.05);
    $service->recordLlmSpend($budget, 0.20);

    expect((float) $budget->fresh()->llm_spent_usd)->toBe(1.1)
        ->and($budget->fresh()->llmState())->toBe(BudgetState::Blocked);
    Event::assertDispatchedTimes(BudgetThresholdReached::class, 2);
    Event::assertDispatched(BudgetThresholdReached::class, fn ($e) => $e->percent === 80);
    Event::assertDispatched(BudgetThresholdReached::class, fn ($e) => $e->percent === 100);
});
