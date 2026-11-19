<?php

namespace App\Filament\Pages;

use App\Enums\CredentialProvider;
use App\Enums\CredentialStatus;
use App\Models\User;
use App\Services\Credentials\ConnectionResult;
use App\Services\Credentials\ConnectionTesters\GoogleOauthTester;
use App\Services\Credentials\ConnectionTesters\MetaTester;
use App\Services\Credentials\ConnectionTesters\TelegramTester;
use App\Services\Credentials\CredentialStore;
use App\Services\Credentials\CredentialValidator;
use App\Services\Llm\ProviderRegistry;
use App\Services\Telegram\Invites;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Pengaturan > Kredensial (docs/specs/credentials-settings.md). Secrets are typed in, saved encrypted, and only
 * ever shown masked. Form state is cleared after every save so no value stays in the Livewire payload.
 */
class Credentials extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Kredensial';

    protected static ?string $title = 'Kredensial';

    protected static ?int $navigationSort = 1;

    /** @var array<string, array<string, string|null>> */
    public array $data = [];

    public function content(Schema $schema): Schema
    {
        $store = app(CredentialStore::class);

        $sections = [];
        foreach (CredentialProvider::cases() as $provider) {
            $definition = (array) config("credentials.providers.{$provider->value}");
            if (($definition['hidden'] ?? false) === true) {
                continue;
            }

            $fields = [];
            foreach ($store->fields($provider) as $key => $field) {
                $current = $store->display($provider, $key);
                $input = TextInput::make("data.{$provider->value}.{$key}")
                    ->label($field['label'])
                    ->autocomplete('off')
                    ->placeholder($current ?? ($field['default'] ?? 'Belum diisi'))
                    ->helperText($current !== null && $field['secret'] ? 'Tersimpan. Kosongkan untuk mempertahankan nilai lama.' : null);
                $fields[] = $field['secret'] ? $input->password()->revealable(false) : $input;
            }

            if ($provider === CredentialProvider::Telegram) {
                $linked = Auth::user()?->getAttribute('telegram_chat_id') !== null;
                $fields[] = Text::make($linked ? 'Akun Telegram Anda sudah terhubung untuk notifikasi admin.' : 'Hubungkan akun Telegram Anda untuk menerima notifikasi keputusan klien dan alert budget.');
            }

            if ($provider === CredentialProvider::GoogleOauth) {
                $fields[] = Text::make('Redirect URI: '.url('/oauth/google/callback'))->copyable();
            }

            $sections[] = Section::make((string) $definition['label'])
                ->description($this->statusLine($provider))
                ->schema([
                    ...$fields,
                    Actions::make([
                        Action::make("save_{$provider->value}")->label('Simpan')->action(fn () => $this->saveProvider($provider)),
                        Action::make("test_{$provider->value}")->label('Tes koneksi')->color('gray')
                            ->disabled(! $store->isComplete($provider))
                            ->action(fn () => $this->testProvider($provider)),
                        Action::make("link_{$provider->value}")->label('Hubungkan Telegram saya')->color('gray')
                            ->visible($provider === CredentialProvider::Telegram && $store->isComplete($provider))
                            ->action(fn () => $this->linkAdminTelegram()),
                        Action::make("delete_{$provider->value}")->label('Hapus')->color('danger')->requiresConfirmation()
                            ->modalDescription('Peran AI yang memakai penyedia ini akan beralih ke model cadangan.')
                            ->hidden($store->status($provider) === null && ! $store->isComplete($provider))
                            ->action(fn () => $this->deleteProvider($provider)),
                    ]),
                ])
                ->collapsible();
        }

        return $schema->components($sections);
    }

    public function saveProvider(CredentialProvider $provider): void
    {
        $submitted = $this->data[$provider->value] ?? [];
        $errors = app(CredentialValidator::class)->errors($provider, $submitted);
        if ($errors !== []) {
            throw ValidationException::withMessages(
                collect($errors)->mapWithKeys(fn ($message, $key) => ["data.{$provider->value}.{$key}" => $message])->all()
            );
        }

        $changed = app(CredentialStore::class)->save($provider, $submitted);
        $this->data[$provider->value] = [];

        Notification::make()
            ->title($changed === [] ? 'Tidak ada perubahan.' : 'Kredensial disimpan. Jalankan tes koneksi.')
            ->success()->send();
    }

    public function testProvider(CredentialProvider $provider): void
    {
        $store = app(CredentialStore::class);
        $result = $this->tester($provider, $store->values($provider));
        $store->recordTest($provider, $result);

        Notification::make()->title($result->message)->{$result->ok ? 'success' : 'danger'}()->send();
    }

    public function linkAdminTelegram(): void
    {
        /** @var User $user */
        $user = Auth::user();
        try {
            $link = app(Invites::class)->forAdmin($user);
        } catch (\Throwable) {
            Notification::make()->title('Bot belum bisa dihubungi. Jalankan tes koneksi dulu.')->danger()->send();

            return;
        }
        Notification::make()->title('Buka tautan ini di Telegram')->body($link)->persistent()->success()->send();
    }

    public function deleteProvider(CredentialProvider $provider): void
    {
        app(CredentialStore::class)->forget($provider);
        Notification::make()->title('Kredensial dihapus.')->success()->send();
    }

    /** @param  array<string, string>  $values */
    private function tester(CredentialProvider $provider, array $values): ConnectionResult
    {
        if ($llm = $provider->llmProvider()) {
            return app(ProviderRegistry::class)->adapter($llm)->testConnection($values);
        }

        return match ($provider) {
            CredentialProvider::Telegram => app(TelegramTester::class)->test($values),
            CredentialProvider::Meta => app(MetaTester::class)->test($values),
            CredentialProvider::GoogleOauth => app(GoogleOauthTester::class)->test($values),
            default => ConnectionResult::failed('not_supported', 'Tes koneksi belum tersedia untuk penyedia ini.'),
        };
    }

    private function statusLine(CredentialProvider $provider): string
    {
        $store = app(CredentialStore::class);
        if (! $store->isComplete($provider)) {
            return 'Belum diisi';
        }

        $status = $store->status($provider);

        return match ($status?->status) {
            CredentialStatus::Ok => 'OK · '.$status->message.' · dites '.$status->last_tested_at?->timezone('Asia/Jakarta')->format('d M Y H:i'),
            CredentialStatus::Failed => 'Gagal · '.$status->message.' ('.$status->last_error_code.')',
            default => 'Belum dites',
        };
    }
}
