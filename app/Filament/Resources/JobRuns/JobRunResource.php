<?php

namespace App\Filament\Resources\JobRuns;

use App\Enums\ArtifactType;
use App\Enums\JobRunStatus;
use App\Jobs\GenerateArtifact;
use App\Models\Client;
use App\Models\JobRun;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** F9.6 job console: queue history, failures, re-run. Pause per client = client status "paused". */
class JobRunResource extends Resource
{
    protected static ?string $model = JobRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Biaya & log AI';

    protected static ?string $navigationLabel = 'Konsol job';

    protected static ?string $modelLabel = 'job';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Diminta')->dateTime('d M H:i', 'Asia/Jakarta')->sortable(),
                TextColumn::make('job')->label('Job'),
                TextColumn::make('client.name')->label('Klien')->placeholder('—'),
                TextColumn::make('status')->badge()->color(fn (JobRunStatus $state) => match ($state) {
                    JobRunStatus::Succeeded => 'success', JobRunStatus::Queued, JobRunStatus::Running => 'info',
                    JobRunStatus::NeedsManual => 'warning', JobRunStatus::Failed => 'danger',
                }),
                TextColumn::make('error_code')->label('Kode error')->placeholder('—'),
                TextColumn::make('attempts')->label('Percobaan'),
                TextColumn::make('finished_at')->label('Selesai')->since()->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('10s')
            ->filters([
                SelectFilter::make('status')->options(collect(JobRunStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->value])),
                SelectFilter::make('client_id')->label('Klien')->relationship('client', 'name'),
            ])
            ->recordActions([
                Action::make('retry')->label('Jalankan ulang')->icon('heroicon-o-arrow-path')->requiresConfirmation()
                    ->visible(fn (JobRun $r) => in_array($r->status, [JobRunStatus::Failed, JobRunStatus::NeedsManual], true)
                        && str_starts_with($r->job, 'generate_artifact:'))
                    ->action(function (JobRun $r) {
                        $client = Client::query()->findOrFail($r->client_id);
                        /** @var User $user */
                        $user = Auth::user();
                        GenerateArtifact::request($client, ArtifactType::from(substr($r->job, strlen('generate_artifact:'))), $user);
                        Notification::make()->title('Masuk antrian lagi.')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListJobRuns::route('/')];
    }
}
