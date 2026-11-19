<?php

namespace App\Providers;

use App\Services\Artifacts\ArtifactApproved;
use App\Services\Content\ContentPlanMaterializer;
use App\Services\Llm\ProviderRegistry;
use App\Services\Schemas\SchemaRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One registry per process so tests (and the fake provider's queue) see the same instances.
        $this->app->singleton(ProviderRegistry::class);
        $this->app->singleton(SchemaRepository::class);
    }

    public function boot(): void
    {
        Event::listen(ArtifactApproved::class, [ContentPlanMaterializer::class, 'handle']);

        // Fakes must never reach production (CLAUDE.md, test isolation).
        if ($this->app->environment('production')) {
            foreach (['llm.provider' => 'LLM_PROVIDER'] as $config => $env) {
                if (config($config) === 'fake') {
                    throw new RuntimeException("{$env}=fake is not allowed in production.");
                }
            }
        }
    }
}
