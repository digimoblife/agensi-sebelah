# Analisis Kebutuhan Pengembangan — Agensi Sebelah

19 Nov 2026 · Sumber: `docs/PRD.md` (PRD v1 + tambahan v1.1)

Kondisi repo saat ini: hanya ada `docs/PRD.md`. Belum ada kode, belum ada git, belum ada `CLAUDE.md`, skill, atau dokumen teknis.

---

## 1. Ringkasan cepat

PRD sudah kuat di sisi **produk** (masalah, prinsip hemat token, 27 peran, artefak, modul F1–F9, roadmap). Yang belum ada adalah lapisan **teknis yang bisa langsung dikerjakan**: stack, skema data, skema JSON artefak, state machine, kontrak per peran, dan detail integrasi platform. Tanpa itu, Minggu 1 MVP ("skema artefak + LLM Gateway + log token") belum bisa dimulai.

**Blocker sebelum menulis kode:**

| # | Keputusan | Rekomendasi |
| --- | --- | --- |
| 1 | Stack backend (pertanyaan terbuka) | Pakai stack yang sama dengan Gawean/ReportFlow supaya pola gateway, bot Telegram, dan skill eval bisa dipakai ulang. |
| 2 | Workflow engine: buat sendiri / n8n / Temporal | **Buat sendiri**: state machine di Postgres + antrian job. n8n tidak cocok untuk artefak berversi dan validasi schema; Temporal terlalu berat untuk satu MacBook. Pertimbangkan antrian berbasis Postgres (pg-boss / Graphile Worker / Oban / Laravel queue sesuai stack) supaya Redis tidak wajib. |
| 3 | Cara klien membuka "tautan approval/laporan" | Lihat kontradiksi K1 di bawah. MVP: approval klien **hanya via Telegram** + laporan dikirim sebagai PDF di Telegram. |
| 4 | Satuan budget guard | Pakai **mata uang (USD/IDR)**, bukan token, karena harga berbeda per penyedia dan ada biaya gambar/video. Token tetap dicatat. |
| 5 | Pemetaan model default & batas biaya media | Isi angka awal (misal media $X/klien/bulan) agar budget guard bisa diuji dengan klien pilot pertama. |

---

## 2. Kontradiksi dan celah di PRD

| ID | Temuan | Dampak | Saran |
| --- | --- | --- | --- |
| K1 | Klien approve lewat "tautan tanpa login", tapi arsitektur local-first tanpa alamat publik (VPS baru fase 2). | Tautan approval tidak bisa dibuka klien. | MVP: approval via tombol inline Telegram saja. Opsional: Cloudflare Tunnel untuk tautan laporan. |
| K2 | Bagian keamanan menyebut "akses dashboard per peran (PM, klien, viewer)", padahal v1 hanya satu admin dan multi-user ditunda. | Scope creep. | Ubah ke: v1 satu admin + 2FA; kolom peran disiapkan di skema. |
| K3 | Auto-publish IG ada di MVP, padahal Meta butuh App Review / akun Business yang tertaut ke Facebook Page. | Bisa memakan waktu berminggu-minggu. | Mulai pendaftaran Meta App **di Minggu 1**. Dalam mode development, akun klien bisa ditambahkan sebagai role/tester. Paket siap upload (F9.5) jadi jalur utama sampai disetujui. |
| K4 | Data Analyst + KPI Snapshot di MVP butuh data Insights IG/GA4, tapi koneksi OAuth (F1.2) tidak dirinci untuk MVP. | KPI Snapshot kosong. | MVP: IG Insights + GA4 saja. Kanal lain fase 2. |
| K5 | Gerbang MVP "≥ 80% konten disetujui tanpa revisi besar", tapi "revisi besar" tidak didefinisikan. | KPI tidak bisa diukur. | Definisikan: revisi besar = item ditolak, atau diregenerasi > 1 kali, atau komentar bertag `major`. Catat di tabel approval. |
| K6 | OAuth redirect butuh HTTPS untuk sebagian penyedia (Meta, TikTok). | Login OAuth gagal di `http://localhost`. | Pakai `https://localhost` (mkcert) atau tunnel, dan catat di dokumen integrasi. |
| K7 | Researcher "scraping kompetitor", sementara NFR melarang otomasi yang melanggar ToS. | Risiko kepatuhan. | Batasi ke sumber publik/terbuka: website kompetitor, Meta Ad Library, Google Trends, SERP API. Tulis daftar sumber yang boleh dipakai. |
| K8 | Checkbox pertanyaan terbuka "Nama produk" dan "Mode pemakaian" belum dicentang padahal sudah diputuskan. | Kecil. | Centang di PRD. |
| K9 | DeepSeek vision "cek saat implementasi". | Brand Guardian / Auditor bisa gagal jika model cadangan tidak mendukung vision. | Gateway wajib menolak routing ke model tanpa kemampuan yang dibutuhkan (cek dari katalog). |
| K10 | MacBook sleep: "catch-up otomatis" belum dirinci. | Job terlewat diam-diam. | launchd agent + `caffeinate` saat ada jadwal dekat; saat bangun, scheduler memindai job `due_at < now` dan memutuskan: jalankan, atau ubah ke paket siap upload + pengingat. |

**Risiko scope.** MVP 6 minggu memuat sekitar 16 kemampuan besar untuk developer tunggal. Kandidat yang bisa digeser ke Minggu 7+ tanpa merusak gerbang MVP: winning library (bagian F9.4), shot list bulanan (F9.3), mode bandingkan model, dan Research Pack otomatis penuh (cukup input manual + ringkasan LLM).

---

## 3. Dokumen yang perlu dibuat

Urut prioritas. **P0** = dibutuhkan sebelum/di Minggu 1, **P1** = selama MVP, **P2** = menjelang fase 2.

| Prioritas | File | Isi | Sumber di PRD |
| --- | --- | --- | --- |
| P0 | `CLAUDE.md` (root) | Stack, perintah dev/test, struktur folder, aturan wajib: semua LLM lewat gateway, `client_id` di semua tabel, output LLM divalidasi schema, tidak ada publish tanpa approval. Bahasa dokumen & kode. | Prinsip, Arsitektur, NFR |
| P0 | `docs/ARCHITECTURE.md` | Diagram komponen (web, worker, scheduler, gateway, renderer, bot), alur data, keputusan local-first, cara catch-up. | Arsitektur teknis |
| P0 | `docs/adr/0001-stack.md`, `0002-workflow-engine.md`, `0003-queue.md`, `0004-approval-channel.md` | Satu keputusan per file: konteks, opsi, keputusan, konsekuensi. | Pertanyaan terbuka |
| P0 | `docs/DATA-MODEL.md` | ERD & tabel: workspace, client, offering, offering_type_profile, knowledge_item, asset (tag, izin, lisensi), artifact + artifact_version, content_item, approval, publish_job, job/job_run, llm_call (prompt_version, model, token in/out/cached, biaya), budget, provider_key (terenkripsi), model_catalog, role_config, moment_calendar, kpi_goal, activity_log. | Artefak, F9.x, F8.4 |
| P0 | `schemas/*.schema.json` + `docs/ARTIFACTS.md` | JSON Schema untuk tiap artefak (Brand Brief, Visual Rules, Research Pack, Content Plan, Content Item, KPI Snapshot dulu), contoh valid, batas token, aturan versi. | Tabel artefak |
| P0 | `docs/LLM-GATEWAY.md` | Interface `call(role, task, inputs)`, routing tier, susunan prompt (Brand Brief di awal untuk prefix cache), fallback, retry + validasi schema (ulang sekali lalu antrian manual), perhitungan biaya per penyedia, budget guard 80%/100%. | Penyedia model, NFR |
| P1 | `docs/ROLES.md` (atau `roles/<role>.yaml`) | Registry 27 peran: trigger, tier, input artefak, output schema, model utama/cadangan, batas biaya per panggilan, file prompt. Isi lengkap dulu untuk peran MVP. | Struktur tim AI |
| P1 | `docs/WORKFLOWS.md` | State machine: onboarding, content item (`planned → drafted → guardian_failed/passed → awaiting_approval → revision → approved → rendered → scheduled → published / failed / packaged`), job (`queued → running → succeeded / failed / dead`), media brief (storyboard → draft → final). Idempotency key per job. | Alur campaign, alur brief video |
| P1 | `docs/INTEGRATIONS.md` | Per platform (Meta/IG, Telegram, GA4, Search Console, nanti TikTok, GBP, WordPress): scope OAuth, status App Review, rate limit, refresh token, endpoint yang dipakai, fallback manual. | F1.2, F4.2, F7.3 |
| P1 | `docs/SECURITY.md` | 2FA admin, enkripsi API key/token (kunci dari Keychain macOS / env), backup harian + uji restore, log aktivitas, retensi data & hashing untuk UU PDP. | F9.9, NFR |
| P1 | `docs/BACKLOG-MVP.md` | Pecahan per minggu → story dengan ID F, acceptance criteria, definisi selesai. | Roadmap |
| P1 | `prompts/<role>/v1.md` + `docs/PROMPTING.md` | Konvensi prompt berversi, struktur (system, brand brief, KB, task, schema), aturan anti-mengarang (fakta hanya dari KB). | Prinsip 4–6, F9.2 |
| P1 | `templates/visual/<brand>/` + `docs/VISUAL-TEMPLATES.md` | Ukuran per format (feed 1080×1350, story 1080×1920, carousel), slot teks, aturan overflow, cara render Playwright. | F3.2 |
| P1 | `lang/id/*` (pesan bot) | Template pesan Telegram: approval, pengingat upload, alert budget, job gagal, laporan mingguan. | F4.1, F9.5 |
| P1 | Seeder data demo fiktif | Klien demo per jenis penawaran (event, aplikasi, jasa, produk fisik, komunitas) agar fitur diuji secara generik. Data klien nyata dimasukkan lewat dashboard, tidak masuk repo. | Jenis bisnis |
| P2 | `evals/<role>/` + `docs/EVALS.md` | Dataset contoh per peran, metrik, cara menjalankan. Mulai kumpulkan contoh sejak MVP dari hasil approval/revisi. | F9.13 |
| P2 | `docs/COMPLIANCE.md` | Aturan per kategori (klaim tumbuh kembang anak, refund tiket), label konten AI Meta/TikTok, larangan wajah orang nyata, foto anak. | Pustaka aset, F9.14 |
| P2 | `docs/RUNBOOK.md` | Menyalakan/mematikan stack, restore backup, rotasi API key, menangani publish gagal. | Keandalan |

---

## 4. SKILL.md yang disarankan

Skill ditaruh di `.claude/skills/<nama>/SKILL.md` di repo ini. Skill berguna untuk pekerjaan yang **berulang dan punya aturan tetap**. Pola di project lain (`pak-carik-messages`, `prompt-eval` untuk ReportFlow) bisa diadaptasi.

| Prioritas | Skill | Kapan dipakai | Isi pokok |
| --- | --- | --- | --- |
| MVP | `new-ai-role` | Menambah atau mengubah salah satu dari 27 peran. | Checklist: entri registry, file prompt v1, output schema, kontrak input sempit, tier + model, batas biaya, test validasi, contoh eval. Larangan memanggil SDK penyedia langsung. |
| MVP | `artifact-schema` | Menambah atau mengubah skema artefak JSON. | Aturan versi (bump minor/major), batas token, migrasi data lama, update contoh dan validator, cek peran yang membacanya. |
| MVP | `llm-gateway` | Menyentuh kode gateway, routing, fallback, biaya. | Urutan prompt untuk prefix cache, cara menghitung biaya per penyedia, format log `llm_call`, aturan budget guard, cara menambah adapter/model lewat katalog. |
| MVP | `bot-messages` (adaptasi `pak-carik-messages`) | Menulis pesan Telegram dan teks UI. | Persona dan gaya bahasa bot Agensi Sebelah (perlu ditentukan), format tombol approval, panjang maksimal, contoh. |
| MVP | `visual-template` | Membuat atau mengubah template HTML/SVG brand. | Ukuran kanvas, slot, token warna/font dari Visual Rules, uji overflow teks Indonesia yang panjang, perintah render. |
| MVP | `job-workflow` | Menambah job, jadwal, atau transisi state. | Idempotency key, retry, perilaku catch-up setelah Mac menyala, aturan "publish gagal = alert, tidak diulang diam-diam". |
| Fase 2 | `prompt-eval` (adaptasi dari ReportFlow) | Setiap prompt/model per peran diganti. | Jalankan dataset per peran, bandingkan skor + biaya antar versi, ambang lulus. |
| Fase 2 | `platform-integration` | Menambah adapter publish/analitik. | Template adapter, scope OAuth, rate limit, fallback paket manual, cara uji tanpa memposting sungguhan. |
| Fase 2 | `content-compliance` | Mengubah aturan Brand Guardian per kategori. | Daftar klaim berisiko per kategori, label AI, aturan aset anak dan wajah. |

Pelengkap non-skill:

- **Lint rule** yang melarang import SDK Anthropic/OpenAI/Gemini/DeepSeek di luar folder gateway. Ini lebih andal daripada instruksi di skill.
- **Hook** opsional (`.claude/settings.json`) untuk menjalankan validasi schema/test setelah file di `schemas/` atau `prompts/` diubah.
- **Skill `run`** atau entri `.claude/launch.json` untuk menyalakan stack lokal setelah stack dipilih.

---

## 5. Urutan kerja yang disarankan

1. `git init`, putuskan stack + ADR 0001–0004, tulis `CLAUDE.md`.
2. Daftarkan Meta App + bot Telegram sekarang, karena App Review butuh waktu (K3).
3. `DATA-MODEL.md` + JSON Schema 6 artefak MVP + `LLM-GATEWAY.md`, lalu skill `artifact-schema`, `llm-gateway`, dan `new-ai-role`.
4. Mulai Minggu 1 MVP sesuai PRD.
5. Dokumen P1 ditulis bersamaan dengan modul yang memakainya; skill MVP lainnya dibuat saat pola pertama sudah ada di kode.
