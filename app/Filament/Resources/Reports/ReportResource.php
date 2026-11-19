<?php

namespace App\Filament\Resources\Reports;

use App\Jobs\GenerateWeeklyReport;
use App\Models\Client;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\WeeklyReports;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** F8.1 weekly reports (PDF + Telegram summary). */
class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $modelLabel = 'laporan';

    protected static ?int $navigationSort = 5;

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
            ->modifyQueryUsing(fn ($query) => $query->with(['client', 'pdf']))
            ->columns([
                TextColumn::make('period_start')->label('Minggu mulai')->date('d M Y')->sortable(),
                TextColumn::make('client.name')->label('Klien'),
                TextColumn::make('narrative.headline')->label('Judul')->limit(60)->placeholder('Tanpa komentar AI'),
                TextColumn::make('status')->badge(),
                TextColumn::make('sent_at')->label('Dikirim')->since()->placeholder('—'),
            ])
            ->defaultSort('period_start', 'desc')
            ->filters([SelectFilter::make('client_id')->label('Klien')->relationship('client', 'name')])
            ->headerActions([
                Action::make('generate')->label('Buat laporan minggu lalu')->icon('heroicon-o-document-chart-bar')
                    ->schema([Select::make('client_id')->label('Klien')->required()->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id'))])
                    ->action(function (array $data) {
                        /** @var Client $client */
                        $client = Client::query()->findOrFail((int) $data['client_id']);
                        /** @var User $user */
                        $user = Auth::user();
                        $queued = GenerateWeeklyReport::request($client, app(WeeklyReports::class)->lastWeekStart($client), $user);
                        Notification::make()->title($queued ? 'Masuk antrian.' : 'Laporan yang sama masih diproses.')->{$queued ? 'success' : 'warning'}()->send();
                    }),
            ])
            ->recordActions([
                Action::make('pdf')->label('Unduh PDF')->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Report $r) => $r->pdf !== null)->url(fn (Report $r) => $r->pdf?->previewUrl(), true),
                Action::make('send')->label('Kirim ulang')->icon('heroicon-o-paper-airplane')->color('gray')->requiresConfirmation()
                    ->visible(fn (Report $r) => $r->pdf !== null)
                    ->action(fn (Report $r) => Notification::make()->title('Terkirim ke '.app(WeeklyReports::class)->send($r).' kontak.')->success()->send()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListReports::route('/')];
    }
}
