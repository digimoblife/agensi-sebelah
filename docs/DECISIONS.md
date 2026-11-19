# Keputusan Desain (Decision Log)

Keputusan kecil yang diambil saat implementasi beserta alasannya. Keputusan besar ditulis sebagai ADR di `docs/adr/`.
Setiap entri menyebut ID fitur PRD atau kode celah (K1–K10) terkait.

---

## Fondasi (19 Nov 2026)

- **Budget dalam USD, bukan token** (ANALISIS §1 #4). Token tetap dicatat di `llm_calls`; kuota dan alert memakai `cost_usd`.
- **Definisi "revisi besar"** (K5): item ditolak, atau diregenerasi lebih dari satu kali, atau komentar revisi ditandai `major`. Disimpan di `content_items.major_revision`.
- **`meta` artefak diisi backend** dan ditimpa gateway sebelum validasi; LLM tidak dipercaya untuk `client_id`/versi.
- **Pola hashtag** di `content_item` memakai `\p{L}` (Unicode). Validator harus mendukung regex Unicode (Ajv default, PCRE `u` di opis).

## Minggu 1 — LLM Gateway & Kredensial (19 Nov 2026)

- **Claude lewat SDK resmi `anthropic-ai/sdk`**, penyedia lain (OpenAI, Gemini, DeepSeek) lewat HTTP client Laravel. Retry SDK dimatikan (`maxRetries: 0`); retry dan fallback dikerjakan gateway agar setiap percobaan tercatat di `llm_calls` dan dihitung budget.
- **Fallback server Claude** (`server-side-fallback-2026-07-01`, `fallbacks: "default"`) aktif untuk model yang di katalog punya kemampuan `server_fallback` (Opus 5.5, Sonnet 5.5). Penolakan yang tetap terjadi → `refused` → gateway mencoba model cadangan.
- **Schema versi penyedia** (`SchemaRepository::providerSchema`): `$ref` di-inline, `meta` dibuang, kata kunci yang tidak didukung structured output (`maxLength`, `pattern`, `minimum`, `minItems`, dll.) dihapus. Schema lengkap tetap divalidasi lokal dengan opis. OpenAI memakai `strict: false` karena mode strict mewajibkan semua properti `required`. DeepSeek memakai JSON mode dan schema ditaruh di prompt.
- **Arti kolom token di `llm_calls`**: `input_tokens` = input yang tidak ter-cache, `cached_input_tokens` = baca cache, `cache_write_tokens` = tulis cache (Anthropic), `output_tokens` termasuk token thinking (Gemini).
- **Validasi semantik umum**: setiap string `kb:<id>` di output harus ada di daftar fakta yang dikirim. Gagal = dianggap output tidak valid (ikut aturan ulang sekali → antrian manual).
- **Error `bad_request` tidak memicu fallback** (permintaan kita yang salah, bukan penyedianya). Error lain (auth, rate limit, kuota, server, timeout, refusal) pindah ke model cadangan.
- **Budget**: tugas `normal` ditunda saat ≥ 80 % atau saat estimasi melebihi sisa; `priority` jalan dan turun ke model termurah bila peran mengizinkan; `critical` selalu jalan di model termurah. Event `BudgetThresholdReached` (80/100) dikirim sekali per bulan; listener Telegram menyusul di Minggu 4.
- **MFA** untuk admin tersedia (Filament app authentication + recovery codes). ~~Wajib saat login pertama~~ → **opsional** sejak 19 Nov 2026 atas keputusan owner; aktifkan dari halaman Profil. Dashboard tetap hanya bisa diakses dari `localhost`.
- **Katalog model**: hanya model Claude yang di-seed dengan harga (sumber: daftar harga Anthropic, Nov 2026). Model OpenAI, Gemini, dan DeepSeek ditambah admin di dashboard beserta harganya; model tanpa harga tidak pernah dipakai gateway.
- **Workspace**: helper/seed memakai "workspace pertama", bukan `firstOrCreate(['id' => 1])` (kolom `id` tidak fillable sehingga cara itu membuat workspace baru setiap kali).

## Minggu 2 — Onboarding, knowledge base, Brand Brief, Research Pack (19 Nov 2026)

- **Jawaban onboarding** (F1.1) disimpan di `clients.onboarding` (jsonb), termasuk checklist akses akun per kanal. Checklist akses tidak pernah dikirim ke AI. Fakta yang boleh dipakai AI tetap hanya dari knowledge base.
- **Profil jenis penawaran** (F9.1) di tabel global `offering_types` (5 profil dari PRD) berisi event konversi, KPI, pilar, CTA, aset, dan catatan kepatuhan. Profil dikirim ke Brand Strategist sebagai masukan, tidak memaksa isi brief.
- **Knowledge base**: mengedit item mencabut persetujuannya, jadi fakta yang berubah harus disetujui ulang. Item kedaluwarsa (`valid_until` lewat) otomatis tidak dikirim.
- **Prasyarat generator**: Brand Brief butuh deskripsi bisnis + minimal satu fakta disetujui; Visual Rules butuh Brand Brief disetujui; Research Pack butuh minimal satu bahan riset. Pesan prasyarat tampil ke admin sebagai notifikasi.
- **Research Pack**: bahan riset dikumpulkan manual dari sumber publik (K7). Backend menimpa `sources`, `period`, dan `keywords[].volume` (hanya dari input SERP admin); model hanya merangkum. Validator peran menolak `source_ids` yang tidak diberikan.
- **Visual Rules**: `logo.asset_id` selalu `pending` sampai pustaka aset ada (Minggu 4); model tidak memilih aset.
- **Validator per peran** (`App\Roles\OutputValidator`, didaftarkan di `config/roles.php`) berjalan setelah validasi schema: Research Pack (sumber), Content Batch (tepat satu item per `plan_item_key`).
- **Job**: `GenerateArtifact` di antrian `llm`, `tries = 1` (retry/fallback sudah di gateway), dicatat di `job_runs` dengan kunci `generate_artifact:<client>:<type>` sehingga klik ganda tidak menggandakan biaya. Hasil dikirim sebagai notifikasi dashboard. Konsol job (F9.6) bisa menjalankan ulang job gagal.
- **Edit artefak oleh admin** = versi baru yang divalidasi schema; versi lama tidak diubah.
- **Budget per klien** bisa diisi di profil klien; kosong = default `config/llm.php`.
- Koneksi OAuth akun klien (F1.2) belum dibuat; checklist akses akun dipakai dulu. OAuth dikerjakan bersama publish IG (Minggu 5) dan analitik.

## Minggu 3 — Content Plan, batch copy, Brand Guardian (19 Nov 2026)

- **Kalender momen** (F9.7) global dan bisa diedit admin. Seeder mengisi tahun berjalan + 2 tahun. Ramadan, Idul Fitri, Idul Adha, dan libur sekolah ditandai *perkiraan* (hisab) dan perlu disesuaikan dengan SKB pemerintah. Tanggal gajian = tanggal 25 sampai akhir bulan.
- **Content Plan** adalah artefak per bulan (`scope_ref = YYYY-MM`). Admin memilih bulan, jumlah konten per minggu, dan platform. Format per platform dibatasi ke yang bisa dibuat MVP (`ContentPlanGenerator::FORMATS`). Validator menolak tanggal di luar bulan, kunci ganda, pilar yang tidak ada di Brand Brief, momen/penawaran yang tidak diberikan, dan kombinasi platform/format yang tidak diminta.
- **Materialisasi**: menyetujui Content Plan membuat/menyelaraskan `content_items` lewat event `ArtifactApproved`. Item yang sudah ditulis tidak diubah oleh plan baru; item yang hilang dari plan dibatalkan bila belum disetujui. Aturan lengkap di `docs/WORKFLOWS.md`.
- **Status konten** hanya lewat `ContentWorkflow` (matriks dikunci test). Nilai status Minggu 4–5 (render, jadwal, publish, paket) sudah ada di CHECK constraint agar tidak perlu migrasi ulang.
- **Copywriter** menulis per batch 10 item (`ContentDrafting::BATCH_SIZE`) dalam satu panggilan. Revisi hanya mengirim item yang direvisi + komentar + draf sebelumnya. Panggilan gagal mengembalikan item ke status sebelumnya.
- **Brand Guardian**: aturan kode (T0) dulu: panjang caption/hashtag per platform, kata terlarang Brand Brief, angka/harga/jadwal tanpa `fact_refs`, headline melebihi batas template, carousel < 2 slide, emoji untuk brand tanpa emoji, link non-https. Draf yang sudah gagal aturan tidak dikirim ke review LLM (hemat token). Review LLM (T1, batch) menilai tone, klaim, fakta, kepatuhan, typo. Isu *major* = gagal.
- **Revisi otomatis maksimal 1 kali** per item atas temuan Guardian (tidak dihitung sebagai revisi manusia). Gagal lagi → `guardian_failed`, admin bisa *Loloskan* dengan alasan tercatat.
- **Revisi besar (K5)** terisi otomatis: ditolak, direvisi manusia lebih dari sekali, atau ditandai *besar* saat minta revisi.
- **Approval dashboard** memakai `ContentReview`; Telegram (Minggu 4) memakai service yang sama. Setiap keputusan tercatat di `approvals` (komentar, tag alasan, major) untuk F9.4.
- **job_runs.options** menyimpan opsi job (mis. bulan Content Plan) agar Konsol job bisa menjalankan ulang persis sama.
- **Kalender konten** (F2.3) berupa halaman bulanan sederhana dengan item dan momen. Interaksi (drag-and-drop jadwal) belum ada; perubahan tanggal lewat revisi Content Plan.

## Minggu 4 — Render template, pustaka aset, approval Telegram (19 Nov 2026)

- **Render lewat Gotenberg** (amandemen ADR 0001). Tiga template bawaan (`resources/views/render`: cover, slide, story) diwarnai dari Visual Rules (warna, font, posisi logo). Carousel = sampul (headline visual) + satu halaman per slide; CTA di halaman terakhir. Ukuran: feed/carousel 1080×1350, story/reel 1080×1920, GBP 1200×900. Reel/short video baru dirender sampulnya (`render.needs_video`), klipnya Fase 2.
- **Kapan render**: setelah Brand Guardian lolos (sebelum approval), supaya klien menyetujui visual + caption sekaligus. Visual yang disetujui menjadi final: `approved → rendered` otomatis bila render masih sesuai draf (hash kanonik draf, karena jsonb Postgres tidak menjaga urutan key).
- **Template per brand** (`visual_rules.templates[].key`) untuk sementara hanya informatif; renderer memilih template bawaan berdasarkan format. Template khusus per klien bisa ditambah nanti tanpa mengubah alur.
- **Pustaka aset** (F9.3): file di disk privat `assets`, preview dashboard lewat signed URL 30 menit. Pemilih foto: kecocokan kata di judul/tag + penawaran, lalu yang paling jarang dipakai. Foto berisi anak hanya dipakai bila izin `granted`, atau sumber stok/ilustrasi. Izin `pending`/`refused` tidak pernah dipakai. Logo = aset jenis logo terbaru (atau `visual_rules.logo.asset_id` bila diisi ULID aset). Shot list bulanan belum dibuat.
- **Telegram** (ADR 0004): long polling di service `telegram-poller` (menunggu sampai token bot diisi). Update diproses sekali (`telegram_updates`, `INSERT ... ON CONFLICT DO NOTHING`). Kontak klien dan admin terhubung lewat tautan `/start` sekali pakai (hash disimpan, berlaku 7 hari).
- **Approval Telegram**: bot mengirim gambar (album untuk carousel) lalu pesan berisi caption + tombol Setujui / Minta revisi / Tolak. Tombol membawa token acak 24 karakter (bukan id), berlaku 7 hari, hanya untuk chat kontak itu, hanya untuk item kliennya, dan hanya saat item masih menunggu persetujuan. Keputusan satu kontak menutup tombol kontak lain untuk item yang sama. "Minta revisi" menunggu satu pesan teks berikutnya (maks. 24 jam) sebagai komentar, lalu draf ulang item itu saja.
- **Notifikasi admin via Telegram**: keputusan klien (revisi/tolak/setuju) dan alert budget 80/100 %. Admin menghubungkan akunnya dari halaman Kredensial.
- Pesan bot memakai template tetap `lang/id/bot.php` dengan nada netral-ramah. Persona khusus (seperti Pak Carik di ReportFlow) belum ditetapkan.

## Minggu 5 — Publish, paket siap upload, KPI, laporan mingguan (19 Nov 2026)

- **Koneksi akun (F1.2)**: Instagram lewat Facebook Login (token Page dari token user long-lived; tidak kedaluwarsa), GA4 lewat Google OAuth offline (refresh token). Token terenkripsi. `state` OAuth terikat sesi admin dan berlaku 15 menit. Property ID GA4 diisi admin (tidak di-list otomatis).
- **Instagram butuh URL publik** untuk gambar (celah baru, melengkapi K3/K6). Solusinya opsional: tunnel ke server nginx khusus `/media` (port 8089) dengan tanda tangan relatif 2 jam. Tanpa tunnel, semua posting memakai paket siap upload, sesuai prinsip local-first PRD. Detail: `docs/RUNBOOK-PUBLISH.md`.
- **Publish otomatis MVP**: Instagram feed, carousel (≤10), story. Reel/TikTok/FB/GBP selalu paket manual sampai Fase 2.
- **QA Publisher (F4.3, T0)**: visual harus sesuai draf terakhir, waktu tidak di masa lalu, caption + hashtag dalam batas platform, carousel ≤ 10, link dicek (HEAD; rusak = blokir, tidak bisa dicek = peringatan), jadwal berdekatan di platform yang sama (< 60 menit = blokir, < 3 jam = peringatan).
- **Jadwal terlewat > 30 menit** = paket + pengingat "Jadwal terlewat", tidak pernah posting terlambat diam-diam. **Publish gagal** = status `failed`, alert Telegram ke admin, tanpa retry otomatis.
- **Paket siap upload (F9.5)**: ZIP berisi gambar berurutan, `caption.txt`, `info.txt`. Pengingat Telegram ke admin: gambar, header jadwal, caption terpisah (mudah disalin), tombol "Sudah diupload" (token acak, hanya chat admin).
- **Waktu**: semua waktu dinormalisasi ke UTC di batas service sebelum dibandingkan dengan kolom `timestamptz`. Bug ini sempat muncul di pengecekan jadwal bentrok (selisih 7 jam) dan tertangkap test.
- **Metrik (F7 MVP)**: Instagram (reach & follower_count harian per metrik terpisah; insight per post yang terbit lewat API) dan GA4 (sessions, users, jumlah event konversi). Tabel `metrics_daily` (upsert idempoten), `metric_pulls` menyimpan hari lengkap terakhir sehingga hari yang terlewat dikejar (maks. 30 hari). Nama metrik Instagram bisa berubah dari Meta; setiap metrik diminta terpisah agar satu perubahan tidak menghentikan yang lain.
- **KPI Snapshot (T0)**: dihitung SQL; jumlah per periode (follower memakai nilai terakhir), perbandingan periode sebelumnya, target goal dihitung pro rata (bulanan 120 → mingguan 28), metrik goal utama di atas, catatan kualitas data (sumber belum terhubung, hari kosong). Disimpan sebagai artefak `kpi_snapshot` (scope `YYYY-Www`) berstatus approved.
- **Laporan mingguan (F8.1)**: Report Writer (T2) hanya menulis komentar; tabel angka di PDF selalu dari snapshot. Bila AI gagal, laporan tetap dikirim tanpa komentar. PDF formal lewat Gotenberg, dikirim ke kontak Telegram klien (ringkasan + PDF) dan admin. Satu laporan per klien per minggu; scheduler tiap jam mengejar laporan yang terlewat. Klien tanpa sumber data terhubung dilewati (hemat biaya).

## Minggu 6 — Kesiapan pilot (19 Nov 2026)

- **Backup harian (F9.9)**: `pg_dump` format custom, dienkripsi AES-256-GCM dengan kunci HMAC dari `APP_KEY`, disimpan di folder host `BACKUP_HOST_DIR` (bisa disk eksternal/folder sinkron), retensi 14 hari, diverifikasi `pg_restore --list` setiap kali. Image app kini memuat `postgresql17-client`. Restore manual mengikuti `docs/RUNBOOK-OPS.md`.
- **Heartbeat** (cache): scheduler (setiap `publish:due` sukses), poller Telegram (setiap putaran, juga saat menunggu token), backup. Dipakai `pilot:check`.
- **`pilot:check`** dan halaman **Kesiapan & gerbang MVP**: satu sumber cek (`PilotReadiness`). ✗ menghalangi pilot (akun admin, API key AI, bot Telegram, model per peran, scheduler, poller, renderer, Brand Brief/Visual Rules, approver Telegram, knowledge base); ! menurunkan kemampuan (Meta/Google, tunnel, logo, foto, IG/GA4, goal).
- **Gerbang MVP** (`MvpGate`): minggu berjalan dihitung dari konten pertama yang terbit/diupload; % tanpa revisi besar = item yang sudah diputuskan, bukan ditolak, dan tidak `major_revision`; biaya bulan ini = `llm_calls` + biaya media; perbandingan dengan baseline Paperclip yang diisi admin (`workspaces.settings.paperclip_baseline_usd`). Waktu PM per klien dicatat manual (`docs/PILOT-CHECKLIST.md` tabel E).
- **Klien demo** (`demo:seed`, `--with-artifacts`, `--purge`): data fiktif yang ditandai `[DEMO]` untuk uji kering; gambar placeholder berupa blok warna. Bukan acuan desain produk.
- **Test end-to-end** (`tests/Feature/EndToEnd/PilotFlowTest.php`) menjalankan satu putaran penuh dengan semua layanan luar dipalsukan.

## Tambahan — gambar AI manual lewat aplikasi chat (19 Nov 2026)

- **Keputusan owner**: untuk hemat biaya, gambar AI dibuat manual. Dashboard menyusun prompt siap tempel (`ImagePrompt`: prompt Copywriter + gaya, palet warna, dan `image_prompt_suffix` dari Visual Rules + rasio sesuai format + larangan: teks/tulisan, logo, wajah orang nyata, dan daftar `ai_forbidden_for` brand). Admin membuat gambarnya di ChatGPT/Gemini lalu mengunggahnya ke item konten (**Konten → Unggah gambar AI**).
- Ini tidak bertentangan dengan PRD "API key berbayar, bukan login langganan chat": aturan itu untuk panggilan otomatis dari sistem; di sini manusia yang memakai aplikasi chat.
- Gambar disimpan sebagai aset `ai_image` (`ai_generated = true`) dan hanya dipakai item itu sebagai latar; template tetap menambahkan headline, logo, dan CTA. Pemilih foto otomatis tidak memakai aset `ai_image`.
- Item yang draf-nya meminta gambar AI (`visual.mode = ai_image`) **tidak dirender dan tidak dikirim ke klien** sampai gambar diunggah. Mengganti gambar membuat render lama usang dan visual dikirim ulang. Setelah konten disetujui, gambar tidak bisa diganti lagi lewat jalur ini.
- **Label konten AI tidak ditambahkan otomatis** ke paket siap upload maupun pengingat Telegram (keputusan owner). Penandaan label di Meta/TikTok diputuskan admin saat upload. Pengiriman prompt dan penerimaan gambar lewat Telegram belum dibuat.
