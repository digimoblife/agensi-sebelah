<?php

namespace App\Console\Commands;

use App\Enums\ArtifactType;
use App\Enums\AssetKind;
use App\Enums\ClientStatus;
use App\Enums\KnowledgeKind;
use App\Enums\ResearchInputKind;
use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Artifacts\ArtifactService;
use App\Services\Assets\AssetLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Week 6 dry run: a clearly fictional client to try the whole flow before the pilot. Never use it as a design
 * reference (the product stays client-agnostic). Remove with --purge.
 */
class DemoSeed extends Command
{
    public const SLUG = 'demo-studio-contoh';

    protected $signature = 'demo:seed {--with-artifacts : Also approve the example Brand Brief and Visual Rules (skips the premium-model step)} {--purge : Delete the demo client}';

    protected $description = 'Create (or delete) a fictional demo client for a dry run';

    public function handle(ArtifactService $artifacts, AssetLibrary $assets): int
    {
        $existing = Client::query()->where('slug', self::SLUG)->first();

        if ($this->option('purge')) {
            if ($existing !== null) {
                Storage::disk('assets')->deleteDirectory($existing->ulid);
                $existing->delete();
            }
            $this->components->info('Klien demo dihapus.');

            return self::SUCCESS;
        }

        if ($existing !== null) {
            $this->components->warn('Klien demo sudah ada (id '.$existing->id.'). Pakai --purge untuk mengulang.');

            return self::SUCCESS;
        }

        $client = DB::transaction(function () {
            $client = Client::query()->create([
                'workspace_id' => Workspace::current()->id, 'name' => '[DEMO] Studio Contoh', 'slug' => self::SLUG,
                'status' => ClientStatus::Active, 'primary_offering_type' => 'event_ticket', 'timezone' => 'Asia/Jakarta',
                'llm_budget_usd' => 5, 'media_budget_usd' => 2,
                'onboarding' => [
                    'business_description' => 'Studio fiktif untuk uji coba: workshop kerajinan tanah liat akhir pekan untuk pemula, kelas kecil, bahan disediakan.',
                    'location' => 'Kota Contoh', 'audience' => 'Pekerja muda 22–35 tahun yang ingin aktivitas offline akhir pekan.',
                    'goals_note' => 'Mengisi kursi workshop setiap akhir pekan.',
                    'competitors' => [['name' => 'Kompetitor Fiktif A', 'url' => 'https://example.com']],
                    'visual_preferences' => 'Hangat, natural, warna tanah.',
                    'account_access' => ['instagram' => 'requested'],
                ],
            ]);
            $offering = $client->offerings()->create(['workspace_id' => $client->workspace_id, 'offering_type' => 'event_ticket',
                'name' => 'Workshop Cangkir Pertama', 'description' => 'Sesi 3 jam membuat cangkir dari tanah liat.', 'price' => 250000, 'status' => 'active',
                'schedule' => ['Sabtu 10.00–13.00' => 'Maks. 10 peserta', 'Minggu 14.00–17.00' => 'Maks. 10 peserta']]);

            foreach ([
                [KnowledgeKind::Price, 'Harga workshop', 'Rp250.000 per orang, sudah termasuk bahan dan pembakaran.'],
                [KnowledgeKind::Schedule, 'Jadwal', 'Sabtu 10.00–13.00 dan Minggu 14.00–17.00.'],
                [KnowledgeKind::Fact, 'Ukuran kelas', 'Kelas maksimal 10 peserta.'],
                [KnowledgeKind::Policy, 'Refund', 'Pembatalan H-3 dapat dijadwalkan ulang; tidak ada pengembalian dana setelah H-3.'],
                [KnowledgeKind::Faq, 'Perlu pengalaman?', 'Tidak perlu. Instruktur mendampingi dari awal.'],
            ] as [$kind, $title, $body]) {
                $client->knowledgeItems()->create(['workspace_id' => $client->workspace_id, 'offering_id' => $offering->id, 'kind' => $kind,
                    'title' => $title, 'body' => $body, 'source' => 'Data demo fiktif', 'approved_at' => now()]);
            }
            $client->kpiGoals()->create(['workspace_id' => $client->workspace_id, 'metric_key' => 'ticket_booking', 'label' => 'Booking tiket',
                'target_value' => 80, 'period' => 'month', 'priority' => 1]);
            $client->researchInputs()->create(['workspace_id' => $client->workspace_id, 'kind' => ResearchInputKind::ManualNote,
                'ref' => 'Catatan demo', 'note' => 'Kompetitor fiktif fokus paket korporat dan jarang menampilkan proses. Peminat menanyakan apakah pemula bisa ikut.']);

            return $client;
        });

        // Placeholder images (plain colour blocks), clearly titled as demo, so render and packages can be tried.
        foreach ([[AssetKind::Logo, 'Logo demo', [138, 75, 42]], [AssetKind::Photo, 'Demo: foto tangan membentuk tanah liat', [196, 164, 132]],
            [AssetKind::Photo, 'Demo: suasana kelas', [120, 140, 110]]] as [$kind, $title, $rgb]) {
            $image = imagecreatetruecolor(800, 800);
            imagefill($image, 0, 0, (int) imagecolorallocate($image, ...$rgb));
            ob_start();
            imagepng($image);
            $assets->storeContents($client, (string) ob_get_clean(), 'png', ['kind' => $kind, 'source' => 'client', 'title' => $title, 'tags' => ['demo']]);
        }

        if ($this->option('with-artifacts')) {
            /** @var User $admin */
            $admin = User::query()->where('role', 'owner')->first() ?? throw new \RuntimeException('Buat akun admin dulu (make:filament-user).');
            foreach ([[ArtifactType::BrandBrief, 'brand_brief'], [ArtifactType::VisualRules, 'visual_rules']] as [$type, $file]) {
                $payload = json_decode((string) file_get_contents(resource_path("schemas/examples/{$file}.v1.json")), true);
                unset($payload['meta']);
                if ($file === 'brand_brief') {
                    $payload['business']['name'] = 'Studio Contoh';
                    $facts = $client->knowledgeItems()->orderBy('id')->pluck('id')->all();
                    $payload['positioning']['proof_points'] = [['text' => 'Kelas maksimal 10 peserta', 'kb_ref' => 'kb:'.$facts[2]]];
                }
                $artifacts->approve($artifacts->createVersion($client, $type, $payload, changeNote: 'Contoh demo'), $admin);
            }
        }

        $this->components->info("Klien demo dibuat (id {$client->id}). Lanjutkan dari menu Klien → [DEMO] Studio Contoh.");

        return self::SUCCESS;
    }
}
