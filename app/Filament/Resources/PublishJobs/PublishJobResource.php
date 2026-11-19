<?php

namespace App\Filament\Resources\PublishJobs;

use App\Enums\PublishStatus;
use App\Models\PublishJob;
use App\Services\Publish\Publishing;
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

/** F4.2 + F9.5: what goes out when, and what still needs a manual upload. */
class PublishJobResource extends Resource
{
    protected static ?string $model = PublishJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $navigationLabel = 'Jadwal terbit';

    protected static ?string $modelLabel = 'jadwal terbit';

    protected static ?int $navigationSort = 4;

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
        $actor = fn () => 'admin:'.Auth::id();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['contentItem', 'client', 'package']))
            ->columns([
                TextColumn::make('scheduled_at')->label('Jadwal')->dateTime('D d M H:i', 'Asia/Jakarta')->sortable(),
                TextColumn::make('client.name')->label('Klien'),
                TextColumn::make('platform')->badge()->color('gray'),
                TextColumn::make('contentItem.angle')->label('Konten')->limit(50),
                TextColumn::make('mode')->label('Cara')->formatStateUsing(fn (string $state) => $state === 'api' ? 'Otomatis' : 'Paket manual'),
                TextColumn::make('status')->badge()->color(fn (PublishStatus $state) => match ($state) {
                    PublishStatus::Published, PublishStatus::ManuallyUploaded => 'success',
                    PublishStatus::Scheduled, PublishStatus::Publishing => 'info',
                    PublishStatus::Packaged, PublishStatus::Missed => 'warning',
                    PublishStatus::Failed => 'danger', PublishStatus::Cancelled => 'gray',
                }),
                TextColumn::make('error_code')->label('Error')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('permalink')->label('Tautan')->url(fn (PublishJob $r) => $r->permalink, true)->limit(30)->placeholder('—'),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->filters([
                SelectFilter::make('status')->multiple()->options(collect(PublishStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->value])),
                SelectFilter::make('client_id')->label('Klien')->relationship('client', 'name'),
            ])
            ->recordActions([
                Action::make('download')->label('Unduh paket')->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (PublishJob $r) => $r->package !== null)
                    ->url(fn (PublishJob $r) => $r->package?->previewUrl(), true),
                Action::make('uploaded')->label('Sudah diupload')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (PublishJob $r) => in_array($r->status, [PublishStatus::Packaged, PublishStatus::Missed], true))
                    ->action(fn (PublishJob $r) => app(Publishing::class)->markUploaded($r, $actor())),
                Action::make('package')->label('Jadikan paket manual')->icon('heroicon-o-archive-box')->color('gray')
                    ->visible(fn (PublishJob $r) => in_array($r->status, [PublishStatus::Scheduled, PublishStatus::Failed], true))
                    ->requiresConfirmation()
                    ->action(fn (PublishJob $r) => app(Publishing::class)->package($r, missed: false, actor: $actor())),
                Action::make('retry')->label('Coba terbit lagi')->icon('heroicon-o-arrow-path')->color('warning')
                    ->visible(fn (PublishJob $r) => $r->status === PublishStatus::Failed)->requiresConfirmation()
                    ->action(function (PublishJob $r) use ($actor) {
                        app(Publishing::class)->retry($r, $actor());
                        Notification::make()->title('Dicoba terbit sekarang.')->success()->send();
                    }),
                Action::make('cancel')->label('Batalkan')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (PublishJob $r) => $r->status === PublishStatus::Scheduled)->requiresConfirmation()
                    ->action(fn (PublishJob $r) => app(Publishing::class)->cancel($r, $actor())),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPublishJobs::route('/')];
    }
}
