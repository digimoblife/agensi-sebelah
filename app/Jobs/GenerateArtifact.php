<?php

namespace App\Jobs;

use App\Enums\ArtifactType;
use App\Models\Client;
use App\Models\JobRun;
use App\Models\User;
use App\Services\Artifacts\ArtifactGeneration;
use App\Services\Artifacts\PrerequisiteMissing;
use App\Services\Jobs\JobRunRecorder;
use App\Services\Llm\Exceptions\BudgetBlocked;
use App\Services\Llm\Exceptions\LlmGatewayException;
use App\Services\Llm\Exceptions\NoRoutableModel;
use App\Services\Llm\Exceptions\OutputInvalid;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Generates one artifact draft on the `llm` queue. Carries only ids. Not retried by the queue: the gateway already
 * retries and falls back, and every outcome lands in job_runs plus a dashboard notification.
 */
class GenerateArtifact implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $jobRunId, public readonly int $clientId, public readonly ArtifactType $type)
    {
        $this->onQueue('llm');
    }

    /** Queue a generation unless the same one is already waiting or running. */
    public static function request(Client $client, ArtifactType $type, ?User $user): bool
    {
        $run = app(JobRunRecorder::class)->queue(
            "generate_artifact:{$client->id}:{$type->value}",
            'generate_artifact:'.$type->value,
            $client->workspace_id,
            $client->id,
            $user,
        );
        if ($run === null) {
            return false;
        }

        dispatch(new self($run->id, $client->id, $type));

        return true;
    }

    public function handle(ArtifactGeneration $generation, JobRunRecorder $recorder): void
    {
        $run = JobRun::query()->find($this->jobRunId);
        $client = Client::query()->find($this->clientId);
        if ($run === null || $client === null) {
            return;
        }

        $recorder->start($run);
        $label = str_replace('_', ' ', ucfirst($this->type->value));

        try {
            $version = $generation->generate($client, $this->type);
        } catch (PrerequisiteMissing $e) {
            $recorder->fail($run, 'prerequisite_missing');
            $this->notify($run, "{$label} belum bisa dibuat", $e->getMessage(), 'warning');

            return;
        } catch (LlmGatewayException $e) {
            $recorder->fail($run, class_basename($e), needsManual: $e instanceof OutputInvalid);
            $this->notify($run, "{$label} gagal dibuat", match (true) {
                $e instanceof BudgetBlocked => 'Budget AI klien bulan ini tidak mencukupi.',
                $e instanceof NoRoutableModel => 'Belum ada model yang siap dipakai untuk peran ini. Periksa Kredensial, Katalog model, dan Model per peran.',
                $e instanceof OutputInvalid => 'Output AI tidak valid dua kali. Tugas masuk antrian manual.',
                default => 'Semua model gagal merespons. Lihat Log panggilan AI.',
            }, 'danger');

            return;
        }

        $recorder->succeed($run, 'artifact_version:'.$version->id);
        $this->notify($run, "{$label} v{$version->version} siap ditinjau", "Klien: {$client->name}", 'success');
    }

    private function notify(JobRun $run, string $title, string $body, string $status): void
    {
        $user = $run->requested_by !== null ? User::query()->find($run->requested_by) : null;
        if ($user === null) {
            return;
        }

        Notification::make()->title($title)->body($body)->status($status)
            ->actions([Action::make('open')->label('Buka klien')->url(route('filament.admin.resources.clients.edit', ['record' => $this->clientId]))])
            ->sendToDatabase($user);
    }
}
