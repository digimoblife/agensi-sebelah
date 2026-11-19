<?php

use App\Enums\LlmCallStatus;
use App\Enums\LlmProvider;
use App\Enums\TaskPriority;
use App\Models\LlmCall;
use App\Models\ModelCatalogEntry;
use App\Services\Budget\BudgetService;
use App\Services\Llm\Exceptions\BudgetBlocked;
use App\Services\Llm\Exceptions\NoRoutableModel;
use App\Services\Llm\Exceptions\OutputInvalid;
use App\Services\Llm\LlmGateway;
use App\Services\Llm\ProviderErrorCode;
use App\Services\Llm\RoleRequest;

function brandBrief(): array
{
    $data = json_decode(file_get_contents(resource_path('schemas/examples/brand_brief.v1.json')), true);
    unset($data['meta']);

    return $data;
}

function copyRequest(int $clientId, array $overrides = []): RoleRequest
{
    return new RoleRequest(...$overrides + [
        'clientId' => $clientId,
        'roleKey' => 'copywriter',
        'taskRef' => 'content_plan@1:batch-1',
        'artifacts' => ['brand_brief' => brandBrief()],
        'facts' => [['id' => 2, 'text' => 'Kelas maksimal 10 peserta.']],
        'task' => ['plan_item_keys' => ['dec-01-process']],
    ]);
}

it('returns a validated payload, logs the call and records spend', function () {
    $client = client();
    routeRole('copywriter');

    $result = app(LlmGateway::class)->run(copyRequest($client->id));

    $call = LlmCall::query()->sole();
    expect($result->payload['items'][0]['plan_item_key'])->toBe('dec-01-process')
        ->and($result->llmCallId)->toBe($call->id)
        ->and($call->status)->toBe(LlmCallStatus::Ok)
        ->and($call->prompt_key)->toBe('copywriter')
        ->and($call->prompt_version)->toBe(1)
        ->and($call->cost_usd)->toBeGreaterThan(0)
        ->and((float) app(BudgetService::class)->current($client)->llm_spent_usd)->toBe((float) $call->cost_usd);
});

it('puts the brand brief and facts in the cacheable prefix, deterministically', function () {
    $client = client();
    routeRole('copywriter');
    $fake = fakeLlm();

    app(LlmGateway::class)->run(copyRequest($client->id));
    app(LlmGateway::class)->run(copyRequest($client->id, ['taskRef' => 'other']));

    [$first, $second] = $fake->requests;
    expect($first->cachedContext)->toContain('## Brand Brief')->toContain('[kb:2] Kelas maksimal 10 peserta.')
        ->and($first->cachedContext)->toBe($second->cachedContext)
        ->and($first->task)->not->toContain('Brand Brief')
        ->and($first->jsonSchema)->not->toHaveKey('$defs')
        ->and($first->effort)->toBe('low');
});

it('retries once with validation feedback, then succeeds', function () {
    $client = client();
    routeRole('copywriter');
    $valid = json_decode(file_get_contents(resource_path('schemas/examples/content_batch.v1.json')), true);
    fakeLlm()->respond(['items' => [['plan_item_key' => 'dec-01-process']]])->respond($valid);

    $result = app(LlmGateway::class)->run(copyRequest($client->id));

    expect(LlmCall::query()->orderBy('id')->pluck('status')->all())->toBe([LlmCallStatus::SchemaInvalid, LlmCallStatus::Ok])
        ->and(fakeLlm()->requests[1]->task)->toContain('Output sebelumnya tidak valid')
        ->and($result->usedFallback)->toBeFalse();
});

it('hands over to manual handling after two invalid outputs', function () {
    $client = client();
    routeRole('copywriter');
    fakeLlm()->respond('not json')->respond('{"items": []}');

    expect(fn () => app(LlmGateway::class)->run(copyRequest($client->id)))->toThrow(OutputInvalid::class);
    expect(LlmCall::query()->where('status', LlmCallStatus::SchemaInvalid)->count())->toBe(2);
});

it('rejects knowledge-base references the model was not given', function () {
    $client = client();
    routeRole('copywriter');
    $output = json_decode(file_get_contents(resource_path('schemas/examples/content_batch.v1.json')), true);
    $output['items'][0]['fact_refs'] = ['kb:999'];
    fakeLlm()->respond($output)->respond($output);

    expect(fn () => app(LlmGateway::class)->run(copyRequest($client->id)))
        ->toThrow(OutputInvalid::class, 'kb:999');
});

it('falls back to the second model when the provider fails', function () {
    $client = client();
    $primary = ModelCatalogEntry::factory()->create();
    $fallback = ModelCatalogEntry::factory()->create(['provider' => 'deepseek']);
    routeRole('copywriter', $primary, $fallback);
    fakeLlm(LlmProvider::Anthropic)->fail(ProviderErrorCode::RateLimited);

    $result = app(LlmGateway::class)->run(copyRequest($client->id));

    $calls = LlmCall::query()->orderBy('id')->get();
    expect($result->usedFallback)->toBeTrue()
        ->and($result->modelId)->toBe($fallback->model_id)
        ->and($calls->pluck('status')->all())->toBe([LlmCallStatus::RateLimited, LlmCallStatus::Ok])
        ->and($calls[1]->fallback_from_call_id)->toBe($calls[0]->id);
});

it('skips models without prices, missing capabilities or credentials', function (array $attributes) {
    $client = client();
    routeRole('copywriter', ModelCatalogEntry::factory()->create($attributes));

    expect(fn () => app(LlmGateway::class)->run(copyRequest($client->id)))->toThrow(NoRoutableModel::class);
})->with([
    'no prices' => [['price_input_per_mtok' => null]],
    'inactive' => [['active' => false]],
    'no text capability' => [['capabilities' => ['image']]],
]);

it('blocks non-priority work when the budget is degraded and logs it', function () {
    $client = client();
    routeRole('copywriter');
    app(BudgetService::class)->current($client)->update(['llm_limit_usd' => 1, 'llm_spent_usd' => 0.9]);

    expect(fn () => app(LlmGateway::class)->run(copyRequest($client->id)))->toThrow(BudgetBlocked::class);
    expect(LlmCall::query()->sole()->status)->toBe(LlmCallStatus::BudgetBlocked)
        ->and(fakeLlm()->requests)->toBeEmpty();
});

it('still runs critical work on a blocked budget', function () {
    $client = client();
    routeRole('copywriter');
    app(BudgetService::class)->current($client)->update(['llm_limit_usd' => 1, 'llm_spent_usd' => 1]);

    $result = app(LlmGateway::class)->run(copyRequest($client->id, ['priority' => TaskPriority::Critical]));

    expect($result->payload)->toHaveKey('items');
});

it('refuses artifacts outside the role input contract', function () {
    $client = client();
    routeRole('copywriter');

    app(LlmGateway::class)->run(copyRequest($client->id, ['artifacts' => ['brand_brief' => brandBrief(), 'kpi_snapshot' => []]]));
})->throws(InvalidArgumentException::class, 'kpi_snapshot');

it('injects meta before validating artifact outputs', function () {
    $client = client();
    routeRole('brand_strategist');

    $result = app(LlmGateway::class)->run(new RoleRequest(
        clientId: $client->id,
        roleKey: 'brand_strategist',
        taskRef: 'onboarding',
        facts: [['id' => 1, 'text' => 'Workshop akhir pekan.'], ['id' => 2, 'text' => 'Kelas maksimal 10 peserta.']],
    ));

    expect($result->payload)->not->toHaveKey('meta')->and($result->payload['business']['name'])->toBe('Studio Contoh');
});
