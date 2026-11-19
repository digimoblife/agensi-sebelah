# Agensi Sebelah

Agensi digital marketing berbasis AI yang hemat token. Workflow deterministik memanggil "peran" AI
hanya saat dibutuhkan. Peran berkomunikasi lewat artefak JSON berversi, dan admin/klien menjadi gerbang persetujuan.
Versi 1 dipakai pribadi: satu admin, banyak klien, berjalan lokal di MacBook.

- **Sumber kebenaran produk:** `docs/PRD.md`. Baca section yang relevan SEBELUM mengerjakan fitur,
  lalu sebutkan ID fitur (F1.1, F9.5, dst.) di pesan commit. Contoh: `feat(gateway): fallback routing (F8.3)`.
- **Analisis & celah PRD:** `docs/ANALISIS-KEBUTUHAN.md` (kode K1–K10).
- **Keputusan teknis:** `docs/adr/`. Keputusan kecil saat implementasi dicatat di `docs/DECISIONS.md`.
- **Dokumen teknis:** `docs/ARCHITECTURE.md`, `docs/DATA-MODEL.md`, `docs/ARTIFACTS.md`, `docs/LLM-GATEWAY.md`.
- Jika PRD ambigu atau bertentangan, cek `ANALISIS-KEBUTUHAN.md` §2. Jika belum tercakup, **tanya user**, jangan menebak.

## Stack (ADR 0001)

Sama dengan ReportFlow: Laravel 13 (PHP 8.3), PostgreSQL, Redis (queue + Horizon), Filament 5 (dashboard admin),
Pest, Pint, Larastan. Ditambah: Gotenberg (PDF laporan dan render template visual lewat screenshot Chromium,
ADR 0001 amandemen), FFmpeg native macOS untuk video (Fase 2), SDK resmi Anthropic untuk Claude.
Docker Compose lokal: `app`, `worker` (Horizon), `scheduler`, `telegram-poller`, `postgres`, `redis`, `gotenberg`, `nginx`.

## Aturan yang tidak boleh dilanggar

1. **Semua panggilan model AI lewat `LlmGateway`** (`app/Services/Llm`). Tidak ada kode lain yang mengimpor
   SDK/HTTP penyedia (Anthropic, OpenAI, Gemini, DeepSeek). Dijaga arch test.
2. **Artefak adalah satu-satunya bahasa antar-peran.** Peran menerima artefak + knowledge base yang dibutuhkannya
   (kontrak input sempit), tanpa riwayat chat. Output peran = artefak baru/versi baru, divalidasi JSON Schema di
   `resources/schemas/artifacts/`. Gagal validasi diulang sekali, lalu masuk antrian manual.
3. **AI mengusulkan, backend memvalidasi, database memegang kebenaran.** AI tidak menulis ke DB langsung.
   Fakta (harga, jadwal, kebijakan, fitur) hanya boleh berasal dari knowledge base klien (F9.2).
4. **Angka dihitung kode.** KPI, audit, biaya, atribusi memakai SQL/rule. LLM hanya menerima ringkasan kecil.
5. **Tidak ada publish dan tidak ada perubahan budget iklan tanpa approval manusia.** Publish gagal = alert,
   tidak dicoba ulang diam-diam.
6. **Status hanya berubah lewat state machine** (`docs/WORKFLOWS.md`, mulai dibuat di Minggu 3). Setiap transisi dicatat.
7. **`client_id` (dan `workspace_id`) di semua tabel data klien** dan semua query di-scope per klien, walau v1 satu admin.
8. **Setiap panggilan LLM dicatat** di `llm_calls`: role, prompt_version, provider, model, token in/out/cached, biaya, status.
9. **Budget guard sebelum memanggil.** Gateway menolak/menurunkan tier saat kuota biaya klien habis (alert 80%, fallback 100%).
10. **Secret tidak pernah disimpan polos, di-log, dikirim ke AI, atau muncul di fixture.** API key & token OAuth
    dienkripsi. Jangan membaca atau mencetak isi `.env`.
11. **Pesan bot memakai template tetap** di `lang/id`, bukan buatan AI. Laporan klien formal dan netral.
12. **AI tidak memalsukan hal nyata** (hasil karya peserta, tempat, fitur aplikasi) dan tidak membuat wajah orang nyata.
    Foto anak hanya dari stok berlisensi, ilustrasi, atau dengan izin orang tua.

## Konvensi kode

- Kode, identifier, komentar, commit message: **bahasa Inggris**. Dokumen di `docs/` dan string untuk user: bahasa Indonesia.
- Waktu disimpan UTC (`timestamptz`), ditampilkan dalam `Asia/Jakarta`. Pola konversi mengikuti ReportFlow (`docs/DECISIONS.md` di sana).
- Enum PHP di `app/Enums`; di DB `varchar` + `CHECK` dengan nilai literal di migrasi.
- Struktur: `app/Services/{Llm,Artifacts,Roles,Workflow,Render,Publish,Telegram,Analytics,Budget}`, `app/Jobs`, `app/Filament`.
  Controller tipis; logic di service.
- Peran AI: `app/Roles/<RoleName>Role.php` + `config/roles.php` (registry) + prompt `resources/prompts/<role>/v<N>.md`.
  Jangan mengedit prompt versi lama; buat versi baru.
- Skema artefak: `resources/schemas/artifacts/<name>.v<N>.schema.json` + contoh di `resources/schemas/examples/`.
- Semua job idempotent (idempotency key) dan aman di-retry. Payload job hanya membawa id.
- Di test semua penyedia keluar dipaksa fake (`LLM_PROVIDER=fake`, `TELEGRAM_CLIENT=fake`, `PUBLISHER=fake`,
  `RENDERER=fake`; ambil instance-nya di test dengan `fakeLlm()`, `fakeTelegram()`, `fakeRenderer()`) dan `Http::preventStrayRequests()` aktif. Tidak ada test yang memanggil API sungguhan.

## Perintah

Tidak ada PHP/Composer di host; semua lewat container. Dashboard: http://localhost:8088/admin, Horizon: /horizon.

```
cp .env.example .env   # isi DB_PASSWORD, REDIS_PASSWORD, HOST_UID/GID (id -u / id -g), lalu:
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan make:filament-user
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint
docker compose exec app ./vendor/bin/phpstan analyse
```

Validasi schema artefak secara lokal: lihat `docs/ARTIFACTS.md` §Validasi lokal (Ajv strict, draft 2020-12).

Test memakai database `agensi_test` (dibuat `docker/postgres/init-test-db.sh` saat volume Postgres pertama kali dibuat);
`tests/TestCase.php` menolak boot jika database bukan `*_test`. Setting baru yang menunjuk layanan luar wajib dikunci di
`tests/bootstrap.php` dan ditambahkan ke `tests/Feature/TestIsolationTest.php`.

Definition of Done: test hijau, pint + phpstan bersih, `migrate:fresh --seed` jalan, contoh artefak lolos schema,
tidak ada secret di diff, PRD section terkait sudah dicek ulang.

## Cara bekerja

- Kerjakan per minggu MVP (`docs/PRD.md` §Roadmap). Mulai minggu baru dengan Plan Mode.
- Commit kecil dan sering di `master`. Push hanya bila user meminta.
- Jangan menambah dependency besar tanpa bertanya. Jangan mengerjakan fitur Fase 2/3 kecuali user meminta.
