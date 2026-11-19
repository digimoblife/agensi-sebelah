<?php

namespace App\Services\Ops;

use App\Enums\ArtifactType;
use App\Enums\AssetKind;
use App\Enums\ClientStatus;
use App\Enums\CredentialProvider;
use App\Enums\CredentialStatus;
use App\Models\Client;
use App\Models\ModelCatalogEntry;
use App\Models\RoleConfig;
use App\Models\User;
use App\Models\Workspace;
use App\Roles\RoleRegistry;
use App\Services\Artifacts\ArtifactService;
use App\Services\Credentials\CredentialStore;
use App\Services\Publish\PublicMediaUrl;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Week 6: everything the pilot needs, checked in one place (`php artisan pilot:check`, Gerbang MVP page).
 * "fail" blocks the pilot, "warn" degrades it (e.g. packages instead of automatic posting).
 */
class PilotReadiness
{
    public function __construct(
        private readonly CredentialStore $credentials,
        private readonly RoleRegistry $roles,
        private readonly Heartbeat $heartbeat,
        private readonly ArtifactService $artifacts,
        private readonly PublicMediaUrl $media,
    ) {}

    /** @return list<Check> */
    public function system(): array
    {
        $checks = [];
        $admin = User::query()->where('role', 'owner')->first();
        $checks[] = $admin === null
            ? new Check('Sistem', 'Akun admin', 'fail', 'Belum ada', 'docker compose exec app php artisan make:filament-user')
            : new Check('Sistem', 'Akun admin', $admin->app_authentication_secret !== null ? 'ok' : 'warn', $admin->email,
                $admin->app_authentication_secret !== null ? '' : 'Login sekali untuk memasang MFA (wajib).');
        $checks[] = new Check('Sistem', 'Telegram admin terhubung', $admin?->getAttribute('telegram_chat_id') !== null ? 'ok' : 'warn', '',
            'Kredensial → Telegram → Hubungkan Telegram saya (untuk pengingat upload & alert).');

        $llmReady = collect([CredentialProvider::Anthropic, CredentialProvider::OpenAi, CredentialProvider::Gemini, CredentialProvider::DeepSeek])
            ->filter(fn (CredentialProvider $p) => $this->credentials->status($p)?->status === CredentialStatus::Ok);
        $checks[] = new Check('Sistem', 'API key AI tervalidasi', $llmReady->isNotEmpty() ? 'ok' : 'fail',
            $llmReady->map(fn ($p) => $p->value)->implode(', '), 'Kredensial → isi API key → Tes koneksi.');
        $checks[] = $this->credentialCheck(CredentialProvider::Telegram, 'Bot Telegram', 'fail');
        $checks[] = $this->credentialCheck(CredentialProvider::Meta, 'Meta App (Instagram)', 'warn');
        $checks[] = $this->credentialCheck(CredentialProvider::GoogleOauth, 'Google OAuth (GA4)', 'warn');

        $workspaceId = Workspace::query()->value('id');
        foreach ($this->roles->options() as $key => $label) {
            $config = RoleConfig::query()->where(['workspace_id' => $workspaceId, 'role_key' => $key])->with('primaryModel')->first();
            $model = $config?->primaryModel;
            $ok = $model instanceof ModelCatalogEntry && $model->isRoutable() && $this->credentials->isUsable(CredentialProvider::from($model->provider->value));
            $checks[] = new Check('Model AI', $label, $ok ? 'ok' : 'fail', $model !== null ? $model->label : 'Belum diatur',
                $ok ? '' : 'Pengaturan → Model per peran (model harus punya harga dan API key yang valid).');
        }

        $checks[] = $this->beat('scheduler', 'Scheduler berjalan', 5, 'fail', 'docker compose up -d scheduler');
        $checks[] = $this->beat('telegram_poller', 'Poller Telegram berjalan', 3, 'fail', 'docker compose up -d telegram-poller');
        $checks[] = $this->beat('backup', 'Backup harian', 26 * 60, 'warn', 'docker compose exec app php artisan backup:run --verify');
        $checks[] = $this->gotenberg();
        $checks[] = new Check('Publish', 'Tunnel media publik', $this->media->available() ? 'ok' : 'warn',
            $this->media->available() ? (string) config('services.publish.public_media_base') : 'Tidak ada',
            'Tanpa tunnel semua posting menjadi paket siap upload (docs/RUNBOOK-PUBLISH.md).');

        return $checks;
    }

    /** @return list<Check> */
    public function client(Client $client): array
    {
        $group = $client->name;
        $c = fn (string $label, bool $ok, string $hint, bool $blocking = true, string $detail = '') => new Check($group, $label, $ok ? 'ok' : ($blocking ? 'fail' : 'warn'), $detail, $ok ? '' : $hint);

        $facts = $client->knowledgeItems()->usable()->count();
        $igConnection = $client->connection('instagram');
        $ga4 = $client->connection('ga4');

        return [
            $c('Status aktif', $client->status === ClientStatus::Active, 'Ubah status klien ke Active setelah onboarding.', false, $client->status->value),
            $c('Jenis penawaran', $client->primary_offering_type !== null, 'Isi jenis penawaran utama di profil.'),
            $c('Knowledge base disetujui', $facts >= 3, 'Tambahkan dan setujui fakta (harga, jadwal, kebijakan, FAQ).', $facts === 0, "{$facts} item"),
            $c('Goal & KPI', $client->kpiGoals()->exists(), 'Tambahkan minimal satu goal dengan prioritas 1.', false),
            $c('Brand Brief disetujui', $this->artifacts->approved($client, ArtifactType::BrandBrief) !== null, 'Artefak → Buat Brand Brief → tinjau → Setujui.'),
            $c('Visual Rules disetujui', $this->artifacts->approved($client, ArtifactType::VisualRules) !== null, 'Artefak → Buat Visual Rules → Setujui.'),
            $c('Logo di pustaka aset', $client->assets()->where('kind', AssetKind::Logo)->exists(), 'Pustaka aset → Unggah aset jenis Logo.', false),
            $c('Foto aset klien', $client->assets()->whereIn('kind', [AssetKind::Photo, AssetKind::Screenshot])->count() >= 5, 'Unggah minimal 5 foto/screenshot asli.', false),
            $c('Approver Telegram terhubung', $client->contacts()->where('can_approve', true)->whereNotNull('telegram_chat_id')->exists(), 'Kontak Telegram → Buat tautan undangan → kirim ke klien.'),
            $c('Instagram terhubung', $igConnection?->isActive() ?? false, 'Koneksi akun → Hubungkan Instagram.', false, (string) ($igConnection->account_name ?? '')),
            $c('GA4 terhubung', $ga4?->isActive() ?? false, 'Koneksi akun → Hubungkan Google Analytics → Atur Property ID.', false),
        ];
    }

    /** @param  list<Check>  $checks */
    public static function blocking(array $checks): bool
    {
        return collect($checks)->contains(fn (Check $c) => $c->status === 'fail');
    }

    /** @param  'warn'|'fail'  $severity */
    private function credentialCheck(CredentialProvider $provider, string $label, string $severity): Check
    {
        $status = $this->credentials->status($provider);

        return match (true) {
            $status?->status === CredentialStatus::Ok => new Check('Sistem', $label, 'ok', (string) $status->message),
            ! $this->credentials->isComplete($provider) => new Check('Sistem', $label, $severity, 'Belum diisi', 'Pengaturan → Kredensial.'),
            default => new Check('Sistem', $label, $severity, 'Belum dites / gagal', 'Kredensial → Tes koneksi.'),
        };
    }

    /** @param  'warn'|'fail'  $severity */
    private function beat(string $name, string $label, int $maxMinutes, string $severity, string $hint): Check
    {
        $last = $this->heartbeat->last($name);
        $ok = $last !== null && $last->gt(now()->subMinutes($maxMinutes));

        return new Check('Sistem', $label, $ok ? 'ok' : $severity, $last?->diffForHumans() ?? 'Belum pernah', $ok ? '' : $hint);
    }

    private function gotenberg(): Check
    {
        try {
            $ok = Http::timeout(5)->get(rtrim((string) config('services.renderer.gotenberg_url'), '/').'/health')->successful();
        } catch (Throwable) {
            $ok = false;
        }

        return new Check('Sistem', 'Renderer (Gotenberg)', $ok ? 'ok' : 'fail', '', $ok ? '' : 'docker compose up -d gotenberg');
    }
}
