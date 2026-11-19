<?php

namespace App\Services\Llm;

use App\Enums\CredentialProvider;
use App\Enums\LlmCallStatus;
use App\Enums\TaskPriority;
use App\Models\Client;
use App\Models\LlmCall;
use App\Models\ModelCatalogEntry;
use App\Models\RoleConfig;
use App\Roles\RoleDefinition;
use App\Roles\RoleRegistry;
use App\Services\Budget\BudgetService;
use App\Services\Credentials\CredentialStore;
use App\Services\Llm\Exceptions\AllProvidersFailed;
use App\Services\Llm\Exceptions\BudgetBlocked;
use App\Services\Llm\Exceptions\NoRoutableModel;
use App\Services\Llm\Exceptions\OutputInvalid;
use App\Services\Llm\Exceptions\ProviderException;
use App\Services\Schemas\SchemaRepository;
use Illuminate\Support\Collection;

/**
 * The only way to reach an AI model (CLAUDE.md rule 1). Steps follow docs/LLM-GATEWAY.md: resolve role → budget
 * guard → deterministic prompt → capability-filtered routing → call → schema + semantic validation (one retry)
 * → fallback model on provider errors → log every attempt and record spend.
 */
class LlmGateway
{
    public function __construct(
        private readonly RoleRegistry $roles,
        private readonly ProviderRegistry $providers,
        private readonly CredentialStore $credentials,
        private readonly SchemaRepository $schemas,
        private readonly PromptRepository $prompts,
        private readonly PromptBuilder $builder,
        private readonly CostCalculator $costs,
        private readonly BudgetService $budgets,
    ) {}

    public function run(RoleRequest $request): RoleResult
    {
        $role = $this->roles->get($request->roleKey);
        $this->builder->assertInputs($role, $request);

        /** @var Client $client */
        $client = Client::query()->findOrFail($request->clientId);
        $config = RoleConfig::query()->where(['workspace_id' => $client->workspace_id, 'role_key' => $role->key])->first();

        $system = $this->prompts->get($role->promptKey, $role->promptVersion);
        $context = $this->builder->cachedContext($role, $request);
        $task = $this->builder->task($role, $request);
        $schema = $this->schemas->providerSchema($role->outputSchema);
        $hash = hash('sha256', implode("\x1F", [$role->key, $role->promptVersion, $context, $task]));

        $candidates = $this->candidates($role, $config, $request->forceModelId);
        $inputTokens = (int) ceil(mb_strlen($system.$context.$task) / (float) config('llm.chars_per_token'));
        /** @var ModelCatalogEntry $first */
        $first = $candidates->first();
        $estimate = $this->costs->estimate($first, $inputTokens, $role->expectedOutputTokens);

        $log = fn (array $attributes) => $this->log($client, $role, $request, $hash, $attributes);

        $maxCost = $config?->max_cost_per_call_usd;
        if ($maxCost !== null && $estimate > (float) $maxCost && $request->priority !== TaskPriority::Critical) {
            $log(['status' => LlmCallStatus::BudgetBlocked, 'error_code' => 'max_cost_per_call', 'attempt' => 0]);
            throw new BudgetBlocked('max_cost_per_call');
        }

        $budget = $this->budgets->current($client);
        $decision = $this->budgets->decide($budget, $request->priority, $estimate, $role->allowDowngrade);
        if (! $decision->allowed) {
            $log(['status' => LlmCallStatus::BudgetBlocked, 'error_code' => $decision->reason, 'attempt' => 0]);
            throw new BudgetBlocked($decision->reason);
        }
        if ($decision->downgrade) {
            $candidates = $candidates->sortBy(fn (ModelCatalogEntry $m) => (float) $m->price_output_per_mtok)->values();
        }

        $attempt = 0;
        $previousCallId = null;
        $lastError = null;

        foreach ($candidates as $index => $model) {
            $credentials = $this->credentials->values(CredentialProvider::from($model->provider->value));
            $feedback = null;

            foreach ([1, 2] as $try) {
                $attempt++;
                $completion = new CompletionRequest(
                    modelId: $model->model_id,
                    system: $system,
                    cachedContext: $context,
                    task: $feedback === null ? $task : $task."\n\n".$feedback,
                    jsonSchema: $schema,
                    schemaName: $role->outputSchema,
                    maxTokens: min($role->maxOutputTokens, $model->max_output_tokens),
                    effort: $model->provider->value === 'anthropic' ? $role->effort : null,
                    serverFallback: $model->hasCapability('server_fallback'),
                );

                $started = hrtime(true);
                try {
                    $response = $this->providers->adapter($model->provider)->complete($completion, $credentials);
                } catch (ProviderException $e) {
                    $lastError = $e->errorCode;
                    $previousCallId = $log([
                        'status' => match ($e->errorCode) {
                            ProviderErrorCode::RateLimited => LlmCallStatus::RateLimited,
                            ProviderErrorCode::Refused => LlmCallStatus::Refused,
                            default => LlmCallStatus::ProviderError,
                        },
                        'error_code' => $e->errorCode->value,
                        'attempt' => $attempt,
                        'model' => $model,
                        'fallback_from_call_id' => $index > 0 ? $previousCallId : null,
                        'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                    ]);

                    if (! $e->errorCode->allowsFallback()) {
                        throw new AllProvidersFailed($e->errorCode->value);
                    }

                    continue 2; // next candidate model
                }

                $cost = $this->costs->cost($model, $response->inputTokens, $response->cachedInputTokens, $response->cacheWriteTokens, $response->outputTokens);
                $this->budgets->recordLlmSpend($budget, $cost);

                [$payload, $errors] = $this->parse($response->text, $role, $request);

                $callId = $log([
                    'status' => $errors === [] ? LlmCallStatus::Ok : LlmCallStatus::SchemaInvalid,
                    'error_code' => $errors === [] ? null : 'schema_invalid',
                    'attempt' => $attempt,
                    'model' => $model,
                    'fallback_from_call_id' => $index > 0 ? $previousCallId : null,
                    'input_tokens' => $response->inputTokens,
                    'cached_input_tokens' => $response->cachedInputTokens,
                    'cache_write_tokens' => $response->cacheWriteTokens,
                    'output_tokens' => $response->outputTokens,
                    'cost_usd' => $cost,
                    'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                ]);

                if ($errors === []) {
                    return new RoleResult($payload, $callId, $cost, $model->model_id, $index > 0);
                }

                // Validation failure: retry once on the same model with short feedback, then hand over to a human.
                if ($try === 2) {
                    throw new OutputInvalid(implode('; ', array_slice($errors, 0, 3)));
                }
                $feedback = "Output sebelumnya tidak valid terhadap schema:\n- ".implode("\n- ", array_slice($errors, 0, 8))
                    ."\nPerbaiki dan kirim ulang JSON lengkap.";
            }
        }

        throw new AllProvidersFailed($lastError?->value);
    }

    /** @return Collection<int, ModelCatalogEntry> */
    private function candidates(RoleDefinition $role, ?RoleConfig $config, ?int $forceModelId): Collection
    {
        $ids = $forceModelId !== null
            ? [$forceModelId]
            : array_filter([$config?->primary_model_id, $config?->fallback_model_id]);

        $models = ModelCatalogEntry::query()->whereIn('id', $ids)->get()
            ->sortBy(fn (ModelCatalogEntry $m) => array_search($m->id, $ids, true))
            ->filter(fn (ModelCatalogEntry $m) => $m->isRoutable()
                && collect($role->capabilities)->every(fn (string $c) => $m->hasCapability($c))
                && $this->credentials->isUsable(CredentialProvider::from($m->provider->value)))
            ->values();

        if ($models->isEmpty()) {
            throw new NoRoutableModel($role->key);
        }

        return $models;
    }

    /** @return array{0: array<string, mixed>, 1: list<string>} */
    private function parse(string $text, RoleDefinition $role, RoleRequest $request): array
    {
        $json = trim($text);
        if (str_starts_with($json, '```')) {
            $json = (string) preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $json);
        }

        $payload = json_decode($json, true);
        if (! is_array($payload)) {
            return [[], ['/: output is not a JSON object']];
        }
        unset($payload['meta']);

        $candidate = $payload;
        if ($this->schemas->hasMeta($role->outputSchema)) {
            $candidate['meta'] = [
                'artifact_type' => explode('.', $role->outputSchema)[0],
                'schema_version' => 1,
                'client_id' => $request->clientId,
            ];
        }

        $errors = $this->schemas->errors($role->outputSchema, $candidate);

        // Semantic rule: every kb:<id> the model cites must be a fact it was given (no invented facts).
        $allowed = array_map(fn (array $f) => 'kb:'.$f['id'], $request->facts);
        array_walk_recursive($payload, function ($value) use ($allowed, &$errors) {
            if (is_string($value) && preg_match('/^kb:\d+$/', $value) === 1 && ! in_array($value, $allowed, true)) {
                $errors[] = "{$value}: rujukan fakta tidak ada di knowledge base yang diberikan";
            }
        });

        return [$payload, $errors];
    }

    /** @param  array<string, mixed>  $attributes */
    private function log(Client $client, RoleDefinition $role, RoleRequest $request, string $hash, array $attributes): int
    {
        $model = $attributes['model'] ?? null;
        unset($attributes['model']);

        return LlmCall::query()->create($attributes + [
            'workspace_id' => $client->workspace_id,
            'client_id' => $client->id,
            'role_key' => $role->key,
            'task_ref' => mb_substr($request->taskRef, 0, 120),
            'prompt_key' => $role->promptKey,
            'prompt_version' => $role->promptVersion,
            'provider' => $model?->provider,
            'model_catalog_id' => $model?->id,
            'model_id' => $model?->model_id,
            'request_hash' => $hash,
        ])->id;
    }
}
