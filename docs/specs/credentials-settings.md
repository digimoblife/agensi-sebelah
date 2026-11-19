# Spesifikasi — Menu Konfigurasi Kredensial

Status: disetujui untuk dibangun · 19 Nov 2026 · Rujukan: PRD §Fitur admin untuk model, F1.2, F9.9 · Target: Minggu 1 MVP (bersama LLM Gateway)

Admin memasukkan semua kredensial lewat dashboard (Filament), **bukan lewat `.env`**. `.env` hanya berisi
konfigurasi infrastruktur (DB, Redis, `APP_KEY`). Dengan begitu kunci bisa dirotasi tanpa restart dan tanpa menyentuh file.

## Lokasi

Filament → grup navigasi **Pengaturan** → halaman **Kredensial**. Hanya admin yang sudah login dengan 2FA.

## Kelompok kredensial

| Kelompok | Field | Tes koneksi | Dipakai oleh |
| --- | --- | --- | --- |
| Anthropic | API key | `GET /v1/models` | LLM Gateway |
| OpenAI | API key, Organization ID (opsional) | `GET /v1/models` | LLM Gateway, GPT Image |
| Google Gemini | API key | list models | LLM Gateway, gambar, Veo |
| DeepSeek | API key, base URL (default resmi) | list models | LLM Gateway |
| Telegram | Bot token | `getMe` (tampilkan username bot) | Poller, approval, notifikasi |
| Meta (Instagram/Facebook) | App ID, App Secret, Graph API version | app access token valid | OAuth koneksi akun klien, publish, Insights |
| Google OAuth | Client ID, Client Secret, redirect URI (read-only, ditampilkan untuk disalin) | validasi format; tes nyata saat connect akun | GA4, Search Console (Fase 2), GBP |
| TikTok (Fase 2) | Client key, Client secret | — | disiapkan, disembunyikan sampai Fase 2 |

Token OAuth **per akun klien** (IG, GA4) tidak dimasukkan di sini. Itu didapat lewat tombol "Hubungkan akun" di halaman
klien (F1.2) dan disimpan di `oauth_connections`. Menu ini hanya menyimpan kredensial aplikasi.

## Perilaku

- **Disimpan terenkripsi** di tabel `provider_credentials` (cast `encrypted` dengan `APP_KEY`). Satu baris per
  (workspace, provider, field). Kolom: `provider`, `key`, `value` (enc), `last4`, `status` (`untested/ok/failed`),
  `last_tested_at`, `last_error_code`, `updated_by`.
- **Tidak pernah ditampilkan ulang.** Setelah disimpan, field tampil sebagai `••••••••abcd` (4 karakter terakhir).
  Mengganti nilai = mengetik ulang. Field kosong saat submit = nilai lama dipertahankan.
- **Tombol "Tes koneksi"** per kelompok, dijalankan sinkron dengan timeout 10 detik. Hasil: status + pesan singkat
  (mis. "OK, bot @nama_bot") atau kode error (`401_unauthorized`, `timeout`). Respons mentah tidak ditampilkan atau di-log.
- **Tombol "Hapus"** dengan konfirmasi. Peran yang bergantung pada provider itu otomatis memakai cadangan; jika tidak ada,
  dashboard menampilkan peringatan.
- **Status di dashboard:** badge per provider (Belum diisi / Belum dites / OK / Gagal). Gateway melewati provider
  berstatus `failed` atau kosong saat routing.
- **Log aktivitas** (`activity_log`): siapa mengubah/menghapus/mengetes kredensial apa dan kapan, **tanpa nilai**.
- **Cache:** nilai didekripsi saat dipakai, tidak disimpan di cache/Redis dalam bentuk polos. Payload job tidak membawa kredensial.

## Keamanan

- Input `type=password`, `autocomplete=off`. Tidak ada nilai kredensial di HTML setelah disimpan.
- Validasi format ringan per provider (panjang minimum, prefix bila diketahui) agar salah tempel terdeteksi.
- `#[SensitiveParameter]` di semua method yang menerima nilai kredensial; tidak ada nilai di exception/log.
- Rotasi `APP_KEY` butuh perintah re-enkripsi (`php artisan credentials:reencrypt`), didokumentasikan di runbook.
- Halaman membutuhkan konfirmasi password ulang jika sesi lebih dari 15 menit (password confirmation Laravel).

## Test

- Simpan → nilai di DB terenkripsi, tidak sama dengan plaintext; `last4` benar.
- Submit kosong mempertahankan nilai lama.
- Tes koneksi memakai `Http::fake()`; sukses, 401, timeout.
- Tidak ada nilai kredensial di log, `activity_log`, atau respons HTML (assert string tidak muncul).
- Gateway melewati provider dengan status `failed`.
