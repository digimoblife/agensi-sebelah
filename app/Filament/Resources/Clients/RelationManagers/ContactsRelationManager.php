<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Models\ClientContact;
use App\Services\Telegram\Invites;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

/** ADR 0004: client people who approve content through the Telegram bot. */
class ContactsRelationManager extends RelationManager
{
    use ScopedToClient;

    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Kontak Telegram';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(120),
            Toggle::make('can_approve')->label('Boleh menyetujui konten')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama'),
                IconColumn::make('can_approve')->label('Approver')->boolean(),
                IconColumn::make('linked')->label('Terhubung')->boolean()->state(fn (ClientContact $r) => $r->telegram_chat_id !== null),
                TextColumn::make('linked_at')->label('Sejak')->since()->placeholder('—'),
            ])
            ->headerActions([$this->scopedCreateAction()->label('Tambah kontak')])
            ->recordActions([
                Action::make('invite')->label('Buat tautan undangan')->icon('heroicon-o-link')
                    ->action(function (ClientContact $r) {
                        try {
                            $link = app(Invites::class)->forContact($r);
                        } catch (Throwable) {
                            Notification::make()->title('Bot Telegram belum siap. Isi dan tes token bot di Kredensial.')->danger()->send();

                            return;
                        }
                        Notification::make()->title('Kirim tautan ini ke '.$r->name)
                            ->body("{$link}\n\nBerlaku ".config('services.telegram.invite_ttl_days').' hari, sekali pakai.')
                            ->persistent()->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
