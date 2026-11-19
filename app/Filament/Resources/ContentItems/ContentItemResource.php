<?php

namespace App\Filament\Resources\ContentItems;

use App\Enums\ContentStatus;
use App\Jobs\DraftContent;
use App\Jobs\RenderAndSend;
use App\Models\Asset;
use App\Models\Client;
use App\Models\ContentItem;
use App\Models\User;
use App\Services\Content\ContentReview;
use App\Services\Publish\Publishing;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/** F3.1–F3.4 + F4.1 (dashboard): draft, review, revise, approve content items. */
class ContentItemResource extends Resource
{
    protected static ?string $model = ContentItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Konten';

    protected static ?string $modelLabel = 'konten';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Rencana')->columns(3)->schema([
                TextEntry::make('client.name')->label('Klien'),
                TextEntry::make('publish_date')->label('Tanggal')->date('d M Y'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (ContentStatus $state) => $state->label())->color(fn (ContentStatus $state) => $state->color()),
                TextEntry::make('platform'),
                TextEntry::make('format'),
                TextEntry::make('pillar')->label('Pilar'),
                TextEntry::make('angle')->columnSpanFull(),
            ]),
            Section::make('Visual')->visible(fn (ContentItem $r) => ! empty($r->render['assets']))->schema([
                ImageEntry::make('render_preview')->hiddenLabel()->imageHeight(260)->checkFileExistence(false)
                    ->state(fn (ContentItem $r) => Asset::query()->whereIn('ulid', $r->render['assets'] ?? [])->get()
                        ->sortBy(fn (Asset $a) => array_search($a->ulid, $r->render['assets'] ?? [], true))->map(fn (Asset $a) => $a->previewUrl())->values()->all()),
                TextEntry::make('render.needs_video')->label('Catatan')->visible(fn (ContentItem $r) => (bool) ($r->render['needs_video'] ?? false))
                    ->formatStateUsing(fn () => 'Format video: gambar ini sampul. Klip video menyusul (fase 2).'),
            ]),
            Section::make('Draf')->visible(fn (ContentItem $r) => $r->draft !== null)->schema([
                TextEntry::make('draft.hook')->label('Hook')->placeholder('—'),
                TextEntry::make('draft.caption')->label('Caption')->prose(),
                TextEntry::make('draft.hashtags')->label('Hashtag')->badge()->placeholder('—'),
                TextEntry::make('draft.cta')->label('CTA')->placeholder('—'),
                RepeatableEntry::make('draft.slides')->label('Slide')->schema([
                    TextEntry::make('headline')->hiddenLabel()->weight('bold'),
                    TextEntry::make('body')->hiddenLabel()->placeholder(''),
                ])->visible(fn (ContentItem $r) => ! empty($r->draft['slides'])),
                TextEntry::make('draft.visual.headline')->label('Headline visual')->placeholder('—'),
                TextEntry::make('draft.fact_refs')->label('Rujukan fakta')->badge()->placeholder('—'),
            ]),
            Section::make('Brand Guardian')->visible(fn (ContentItem $r) => $r->guardian !== null)->schema([
                TextEntry::make('guardian.verdict')->label('Hasil')->badge()->color(fn (?string $state) => $state === 'pass' ? 'success' : 'danger'),
                RepeatableEntry::make('guardian.issues')->label('Temuan')->schema([
                    TextEntry::make('severity')->hiddenLabel()->badge()->color(fn (?string $state) => $state === 'major' ? 'danger' : 'gray'),
                    TextEntry::make('note')->hiddenLabel(),
                    TextEntry::make('suggestion')->hiddenLabel()->placeholder('')->color('gray'),
                ])->columns(3),
            ]),
            Section::make('Revisi')->visible(fn (ContentItem $r) => $r->revision_comment !== null)->schema([
                TextEntry::make('revision_comment')->label('Komentar terakhir'),
                TextEntry::make('revision_count')->label('Jumlah revisi'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('publish_date')->label('Tanggal')->date('D, d M')->sortable(),
                TextColumn::make('client.name')->label('Klien')->toggleable(),
                TextColumn::make('platform')->badge()->color('gray'),
                TextColumn::make('format'),
                TextColumn::make('angle')->limit(60)->tooltip(fn (ContentItem $r) => $r->angle)->searchable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (ContentStatus $state) => $state->label())->color(fn (ContentStatus $state) => $state->color()),
                TextColumn::make('revision_count')->label('Rev.')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('publish_date')
            ->groups([Group::make('month')->label('Bulan'), Group::make('client.name')->label('Klien')])
            ->filters([
                SelectFilter::make('client_id')->label('Klien')->relationship('client', 'name'),
                SelectFilter::make('status')->multiple()->options(collect(ContentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                SelectFilter::make('month')->label('Bulan')->options(fn () => ContentItem::query()->distinct()->orderByDesc('month')->pluck('month', 'month')),
            ])
            ->recordActions(static::recordActions())
            ->toolbarActions([
                BulkAction::make('draft')->label('Buat / ulang draf')->icon('heroicon-o-pencil')
                    ->requiresConfirmation()->modalDescription('Ditulis per batch di antrian, lalu dicek Brand Guardian. Memakai budget AI klien.')
                    ->action(fn (Collection $records) => static::queueDrafts($records)),
            ]);
    }

    /** @return list<Action> */
    public static function recordActions(): array
    {
        $actor = fn () => 'admin:'.Auth::id();

        return [
            Action::make('view')->label('Lihat')->icon('heroicon-o-eye')->modalSubmitAction(false)
                ->schema(fn (Schema $schema) => static::infolist($schema)),
            Action::make('send')->label('Render & kirim ke klien')->icon('heroicon-o-paper-airplane')->color('gray')
                ->visible(fn (ContentItem $r) => $r->status === ContentStatus::AwaitingApproval)
                ->requiresConfirmation()->modalDescription('Visual dirender ulang bila draf berubah, lalu dikirim ke kontak approver lewat Telegram.')
                ->action(function (ContentItem $r) {
                    dispatch(new RenderAndSend([$r->id]));
                    Notification::make()->title('Masuk antrian render & kirim.')->success()->send();
                }),
            Action::make('schedule')->label('Jadwalkan')->icon('heroicon-o-clock')->color('success')
                ->visible(fn (ContentItem $r) => $r->status === ContentStatus::Rendered)
                ->schema(fn (ContentItem $r) => [DateTimePicker::make('at')->label('Waktu terbit')->seconds(false)->timezone('Asia/Jakarta')->required()
                    ->default(app(Publishing::class)->defaultTime($r))])
                ->action(function (ContentItem $r, array $data) use ($actor) {
                    $result = app(Publishing::class)->schedule($r, CarbonImmutable::parse((string) $data['at']), $actor());
                    $notes = implode(' · ', array_column($result['issues'], 'note'));
                    $result['job'] === null
                        ? Notification::make()->title('Belum bisa dijadwalkan')->body($notes)->danger()->persistent()->send()
                        : Notification::make()->title($result['job']->mode === 'api' ? 'Dijadwalkan, terbit otomatis.' : 'Dijadwalkan. Paket + pengingat dikirim saat jadwal tiba.')->body($notes ?: null)->success()->send();
                }),
            Action::make('approve')->label('Setujui')->icon('heroicon-o-check')->color('success')
                ->visible(fn (ContentItem $r) => $r->status === ContentStatus::AwaitingApproval)
                ->action(fn (ContentItem $r) => app(ContentReview::class)->approve($r, $actor())),
            Action::make('revise')->label('Minta revisi')->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible(fn (ContentItem $r) => in_array($r->status, [ContentStatus::AwaitingApproval, ContentStatus::Approved], true))
                ->schema([
                    Textarea::make('comment')->label('Komentar untuk Copywriter')->required()->rows(4)->maxLength(2000),
                    Toggle::make('major')->label('Revisi besar (isi/arah salah, bukan sekadar redaksi)'),
                ])
                ->action(function (ContentItem $r, array $data) use ($actor) {
                    app(ContentReview::class)->requestRevision($r, (string) $data['comment'], (bool) ($data['major'] ?? false), $actor());
                    static::queueDrafts(collect([$r]));
                }),
            Action::make('reject')->label('Tolak')->icon('heroicon-o-x-mark')->color('danger')
                ->visible(fn (ContentItem $r) => $r->status === ContentStatus::AwaitingApproval)
                ->schema([Textarea::make('comment')->label('Alasan')->rows(3)->maxLength(2000)])
                ->action(fn (ContentItem $r, array $data) => app(ContentReview::class)->reject($r, $data['comment'] ?? null, $actor())),
            Action::make('override')->label('Loloskan')->icon('heroicon-o-shield-check')->color('gray')
                ->visible(fn (ContentItem $r) => $r->status === ContentStatus::GuardianFailed)
                ->schema([Textarea::make('reason')->label('Alasan meloloskan temuan Guardian')->required()->rows(2)->maxLength(300)])
                ->action(fn (ContentItem $r, array $data) => app(ContentReview::class)->overrideGuardian($r, (string) $data['reason'], $actor())),
        ];
    }

    /** @param  Collection<int, ContentItem>  $records */
    public static function queueDrafts(Collection $records): void
    {
        /** @var User $user */
        $user = Auth::user();
        $queued = 0;
        foreach ($records->groupBy('client_id') as $clientId => $items) {
            $client = Client::query()->findOrFail($clientId);
            $ids = $items->whereIn('status', [ContentStatus::Planned, ContentStatus::RevisionRequested, ContentStatus::Rejected, ContentStatus::GuardianFailed])
                ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $ids = array_values($ids);
            $queued += DraftContent::request($client, $ids, $user, 'admin:'.$user->id) ? count($ids) : 0;
        }

        Notification::make()->title($queued > 0 ? "{$queued} konten masuk antrian penulisan." : 'Tidak ada konten yang bisa ditulis (atau sudah di antrian).')
            ->{$queued > 0 ? 'success' : 'warning'}()->send();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListContentItems::route('/')];
    }
}
