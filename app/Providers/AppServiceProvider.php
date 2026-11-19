<?php

namespace App\Providers;

use App\Listeners\NotifyBudgetThreshold;
use App\Services\Artifacts\ArtifactApproved;
use App\Services\Budget\BudgetThresholdReached;
use App\Services\Content\ContentPlanMaterializer;
use App\Services\Llm\ProviderRegistry;
use App\Services\Publish\FakePublisher;
use App\Services\Publish\Platforms\InstagramPublisher;
use App\Services\Publish\Publisher;
use App\Services\Render\FakeRenderer;
use App\Services\Render\GotenbergRenderer;
use App\Services\Render\HtmlRenderer;
use App\Services\Schemas\SchemaRepository;
use App\Services\Telegram\FakeTelegramClient;
use App\Services\Telegram\HttpTelegramClient;
use App\Services\Telegram\TelegramClient;
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
        $this->app->singleton(TelegramClient::class, fn ($app) => config('services.telegram.client') === 'fake' ? new FakeTelegramClient : $app->make(HttpTelegramClient::class));
        $this->app->singleton(Publisher::class, fn ($app) => config('services.publish.driver') === 'fake' ? new FakePublisher : $app->make(InstagramPublisher::class));
        $this->app->singleton(HtmlRenderer::class, fn () => config('services.renderer.driver') === 'fake' ? new FakeRenderer : new GotenbergRenderer);
    }

    public function boot(): void
    {
        Event::listen(ArtifactApproved::class, [ContentPlanMaterializer::class, 'handle']);
        Event::listen(BudgetThresholdReached::class, NotifyBudgetThreshold::class);

        // Fakes must never reach production (CLAUDE.md, test isolation).
        if ($this->app->environment('production')) {
            foreach (['llm.provider' => 'LLM_PROVIDER', 'services.renderer.driver' => 'RENDERER', 'services.telegram.client' => 'TELEGRAM_CLIENT', 'services.publish.driver' => 'PUBLISHER'] as $config => $env) {
                if (config($config) === 'fake') {
                    throw new RuntimeException("{$env}=fake is not allowed in production.");
                }
            }
        }
    }
}
