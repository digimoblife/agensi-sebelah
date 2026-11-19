<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Enums\ArtifactStatus;
use App\Enums\ArtifactType;
use App\Jobs\GenerateArtifact;
use App\Models\ArtifactVersion;
use App\Models\Client;
use App\Models\User;
use App\Services\Artifacts\ArtifactGeneration;
use App\Services\Artifacts\ArtifactService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/** Brand Brief, Visual Rules, Research Pack: generate (queued), review, edit as a new version, approve. */
class ArtifactVersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'artifactVersions';

    protected static ?string $title = 'Artefak';

    public function table(Table $table): Table
    {
        $generate = collect(ArtifactGeneration::supported())->map(fn (ArtifactType $type) => Action::make("generate_{$type->value}")
            ->label(match ($type) {
                ArtifactType::BrandBrief => 'Buat Brand Brief',
                ArtifactType::VisualRules => 'Buat Visual Rules',
                ArtifactType::ResearchPack => 'Buat Research Pack',
                ArtifactType::ContentPlan => 'Buat Content Plan',
                default => $type->value,
            })
            ->modalDescription('Dijalankan di antrian dan memakai budget AI klien. Hasilnya berupa draf untuk ditinjau.')
            ->schema($type === ArtifactType::ContentPlan ? [
                Select::make('month')->label('Bulan')->required()->default(now('Asia/Jakarta')->addMonth()->format('Y-m'))
                    ->options(collect(range(0, 3))->mapWithKeys(fn (int $i) => [
                        now('Asia/Jakarta')->startOfMonth()->addMonths($i)->format('Y-m') => now('Asia/Jakarta')->startOfMonth()->addMonths($i)->format('m/Y'),
                    ])),
                TextInput::make('posts_per_week')->label('Konten per minggu')->integer()->minValue(1)->maxValue(14)->default(3)->required(),
                CheckboxList::make('platforms')->label('Platform')->required()->default(['instagram'])
                    ->options(['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'google_business_profile' => 'Google Business Profile']),
                Textarea::make('notes')->label('Catatan untuk Content Strategist')->rows(3)->maxLength(1000),
            ] : [])
            ->requiresConfirmation($type !== ArtifactType::ContentPlan)
            ->action(function (array $data) use ($type) {
                /** @var Client $client */
                $client = $this->getOwnerRecord();
                /** @var User $user */
                $user = Auth::user();
                $options = $type === ArtifactType::ContentPlan ? [
                    'month' => (string) $data['month'],
                    'posts_per_week' => (int) $data['posts_per_week'],
                    'platforms' => array_values((array) $data['platforms']),
                    'notes' => isset($data['notes']) ? (string) $data['notes'] : null,
                ] : [];
                $queued = GenerateArtifact::request($client, $type, $user, $options);

                Notification::make()
                    ->title($queued ? 'Masuk antrian. Notifikasi muncul saat selesai.' : 'Pembuatan yang sama masih berjalan.')
                    ->{$queued ? 'success' : 'warning'}()->send();
            }))->all();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('artifact'))
            ->columns([
                TextColumn::make('artifact.type')->label('Artefak')->formatStateUsing(fn (ArtifactType $state) => str_replace('_', ' ', ucfirst($state->value))),
                TextColumn::make('artifact.scope_ref')->label('Periode')->placeholder('—'),
                TextColumn::make('version')->label('Versi')->formatStateUsing(fn ($state) => "v{$state}"),
                TextColumn::make('status')->badge()->color(fn (ArtifactStatus $state) => match ($state) {
                    ArtifactStatus::Approved => 'success', ArtifactStatus::Draft, ArtifactStatus::InReview => 'info', default => 'gray',
                }),
                TextColumn::make('token_estimate')->label('± token')->numeric(),
                TextColumn::make('change_note')->label('Catatan')->limit(40)->placeholder('—'),
                TextColumn::make('created_at')->label('Dibuat')->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')->label('Artefak')
                    ->options(collect(ArtifactType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->value]))
                    ->query(fn ($query, array $data) => $data['value'] ? $query->whereHas('artifact', fn ($q) => $q->where('type', $data['value'])) : $query),
            ])
            ->headerActions([ActionGroup::make($generate)->label('Buat dengan AI')->button()->icon('heroicon-o-sparkles')])
            ->recordActions([
                Action::make('view')->label('Lihat')->icon('heroicon-o-eye')->modalSubmitAction(false)
                    ->modalHeading(fn (ArtifactVersion $r) => str_replace('_', ' ', ucfirst($r->artifact->type->value))." v{$r->version}")
                    ->schema([Textarea::make('json')->hiddenLabel()->rows(28)->disabled()])
                    ->fillForm(fn (ArtifactVersion $r) => ['json' => $this->pretty($r->payload)]),
                Action::make('edit_json')->label('Ubah')->icon('heroicon-o-pencil-square')
                    ->modalDescription('Perubahan disimpan sebagai versi baru dan divalidasi terhadap schema.')
                    ->schema([
                        Textarea::make('json')->hiddenLabel()->rows(28)->required(),
                        Textarea::make('change_note')->label('Catatan perubahan')->rows(2)->maxLength(300),
                    ])
                    ->fillForm(fn (ArtifactVersion $r) => ['json' => $this->pretty($r->payload)])
                    ->action(fn (ArtifactVersion $r, array $data) => $this->saveEdit($r, $data)),
                Action::make('approve')->label('Setujui')->icon('heroicon-o-check-badge')->color('success')->requiresConfirmation()
                    ->modalDescription('Versi ini akan dipakai semua peran. Versi yang disetujui sebelumnya menjadi superseded.')
                    ->visible(fn (ArtifactVersion $r) => in_array($r->status, [ArtifactStatus::Draft, ArtifactStatus::InReview], true))
                    ->action(function (ArtifactVersion $r) {
                        /** @var User $user */
                        $user = Auth::user();
                        app(ArtifactService::class)->approve($r, $user);
                        Notification::make()->title("v{$r->version} disetujui.")
                            ->body($r->artifact->type === ArtifactType::ContentPlan ? 'Item konten dibuat di menu Konten.' : null)->success()->send();
                    }),
            ]);
    }

    /** @param  array<string, mixed>  $data */
    private function saveEdit(ArtifactVersion $version, array $data): void
    {
        $payload = json_decode((string) ($data['json'] ?? ''), true);
        if (! is_array($payload)) {
            Notification::make()->title('JSON tidak valid.')->danger()->send();

            return;
        }

        /** @var Client $client */
        $client = $this->getOwnerRecord();
        try {
            $new = app(ArtifactService::class)->createVersion(
                $client, $version->artifact->type, $payload, $version->artifact->scope_ref,
                changeNote: filled($data['change_note'] ?? null) ? (string) $data['change_note'] : 'Diubah admin dari v'.$version->version,
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Tidak sesuai schema')->body(mb_strimwidth($e->getMessage(), 0, 500, '…'))->danger()->persistent()->send();

            return;
        }

        Notification::make()->title("Disimpan sebagai v{$new->version}.")->success()->send();
    }

    /** @param  array<string, mixed>  $payload */
    private function pretty(array $payload): string
    {
        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
