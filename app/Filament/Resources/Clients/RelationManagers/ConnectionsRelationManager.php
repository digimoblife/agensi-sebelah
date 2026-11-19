<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Models\Client;
use App\Models\OAuthConnection;
use App\Services\Analytics\MetricsPuller;
use App\Services\Connections\GoogleConnection;
use App\Services\Connections\MetaConnection;
use App\Services\Connections\OAuthException;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** F1.2: connect the client's Instagram (publish + insights) and GA4 (analytics). */
class ConnectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'connections';

    protected static ?string $title = 'Koneksi akun';

    public function table(Table $table): Table
    {
        /** @var Client $client */
        $client = $this->getOwnerRecord();

        return $table
            ->columns([
                TextColumn::make('platform')->formatStateUsing(fn (string $state) => $state === 'ga4' ? 'Google Analytics 4' : 'Instagram'),
                TextColumn::make('account_name')->label('Akun')->placeholder('Belum dipilih'),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'active' => 'success', 'needs_selection', 'pending' => 'warning', default => 'danger',
                }),
                TextColumn::make('last_error_code')->label('Error terakhir')->placeholder('—'),
                TextColumn::make('updated_at')->label('Diubah')->since(),
            ])
            ->headerActions([
                Action::make('connect_instagram')->label('Hubungkan Instagram')->icon('heroicon-o-camera')
                    ->url(route('oauth.connect', ['platform' => 'meta', 'client' => $client->ulid])),
                Action::make('connect_ga4')->label('Hubungkan Google Analytics')->icon('heroicon-o-chart-bar')->color('gray')
                    ->url(route('oauth.connect', ['platform' => 'google', 'client' => $client->ulid])),
                Action::make('pull')->label('Tarik data sekarang')->icon('heroicon-o-arrow-path')->color('gray')
                    ->action(function () use ($client) {
                        $outcomes = app(MetricsPuller::class)->pull($client);
                        Notification::make()->title('Tarik data selesai')->body(collect($outcomes)->map(fn ($o, $s) => "{$s}: {$o}")->implode(' · '))->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('choose_ig')->label('Pilih akun Instagram')
                    ->visible(fn (OAuthConnection $r) => $r->platform === 'instagram' && $r->status === 'needs_selection')
                    ->schema(fn (OAuthConnection $r) => [Select::make('ig_id')->label('Akun')->required()
                        ->options(collect((array) ($r->meta['candidates'] ?? []))->mapWithKeys(fn (array $c) => [$c['ig_id'] => '@'.$c['ig_username'].' ('.$c['page_name'].')']))])
                    ->action(function (OAuthConnection $r, array $data) {
                        try {
                            app(MetaConnection::class)->select($r, (string) $data['ig_id']);
                            Notification::make()->title('Akun Instagram dipilih.')->success()->send();
                        } catch (OAuthException $e) {
                            Notification::make()->title('Gagal memilih akun ('.$e->getMessage().').')->danger()->send();
                        }
                    }),
                Action::make('ga4_property')->label('Atur Property ID')
                    ->visible(fn (OAuthConnection $r) => $r->platform === 'ga4')
                    ->schema([TextInput::make('property')->label('GA4 Property ID (angka)')->required()->regex('/^\d{6,12}$/')
                        ->helperText('Admin GA4 → Property settings → Property ID.')])
                    ->fillForm(fn (OAuthConnection $r) => ['property' => $r->account_ref])
                    ->action(fn (OAuthConnection $r, array $data) => app(GoogleConnection::class)->setProperty($r, (string) $data['property'])),
                DeleteAction::make()->label('Putuskan'),
            ]);
    }
}
