# PRD v1 — Agensi Digital Marketing Berbasis AI (Hemat Token)

Nov 18, 2026 · @Simple Logic

## Ringkasan produk

Kita membangun Agensi Sebelah, agensi digital marketing berbasis AI yang mengelola brand, campaign, website, SEO, media sosial, iklan, audit konversi, dan analitik untuk banyak klien sekaligus, dengan biaya token jauh di bawah sistem multi-agent seperti Paperclip.

**Problem.** UMKM dan brand lokal butuh pemasaran digital yang konsisten, tetapi agensi manusia mahal, freelancer tidak konsisten, dan sistem agent otonom boros token karena koordinasi antar-agent (heartbeat, konteks panjang, pesan bolak-balik).

**Solusi.** Workflow deterministik yang memanggil "peran" AI hanya saat dibutuhkan. Peran berkomunikasi lewat artefak JSON terstruktur, bukan chat. Manusia (PM/klien) menjadi gerbang persetujuan.

**Visi.** Satu PM bisa mengelola 20 sampai 50 klien dengan kualitas setara agensi, dengan biaya AI per klien yang dapat diprediksi dan dibatasi.

## Pengguna dan mode pemakaian

Versi 1 dipakai pribadi: satu admin (owner) mengelola klien-kliennya sendiri; tidak ada plan, billing, atau fitur SaaS.

| Pengguna | Peran di sistem | Akses |
| --- | --- | --- |
| Admin (owner) | Mengelola semua klien, brief, peran AI, model & API key, approval, publish, audit, analitik | Penuh, lewat dashboard + Telegram |
| Klien | Menyetujui konten dan membaca laporan | Tautan approval/laporan atau Telegram, tanpa login dashboard |

**Tetap siap jadi SaaS.** Data dipisah per klien (client\_id di semua tabel), biaya dicatat per klien, dan API key disimpan per workspace. Jika nanti dijadikan SaaS, cukup menambah lapisan workspace, user, plan, dan billing tanpa membongkar inti.

**Diferensiasi.** (1) Biaya AI per klien terukur dan dibatasi. (2) Satu sistem mencakup strategi sampai analitik. (3) Bebas memilih model dari empat penyedia per peran. (4) Data first-party dan server-side tracking sebagai standar.

## Prinsip desain hemat token

Token hanya dibelanjakan untuk kreativitas dan penilaian; koordinasi, hitungan, dan rendering dikerjakan kode.

1. **Orkestrator deterministik.** Alur kerja ditulis sebagai state machine; tidak ada "CEO agent".
2. **Event-driven, tanpa heartbeat.** Peran berjalan hanya karena jadwal, event (komentar masuk, metrik anjlok), atau aksi manusia.
3. **Routing model per tier.** T0 tanpa LLM, T1 model murah, T2 DeepSeek, T3 model premium (strategi, jarang).
4. **Brand Brief terkompresi + prefix caching.** Profil klien sekitar 1.500 token, selalu di awal prompt agar ter-cache.
5. **Batching.** Satu panggilan menghasilkan banyak item (misalnya 30 caption) dalam JSON.
6. **Kontrak input sempit.** Setiap peran hanya menerima artefak yang dibutuhkannya, tanpa riwayat chat.
7. **Angka dihitung kode.** Analitik dan audit memakai SQL/rule; LLM hanya menerima ringkasan kecil untuk menulis insight.
8. **Template visual.** Desain dirender dari template HTML/SVG; generate gambar hanya untuk kebutuhan khusus.
9. **Revisi parsial.** Revisi mengubah satu item, bukan generate ulang batch.
10. **Budget guard.** Kuota token bulanan per klien; saat mendekati batas, turun tier atau tunda tugas non-prioritas.

## Struktur tim AI

Tim terdiri dari 27 peran dalam 8 divisi; setiap peran adalah prompt + job, bukan agent yang hidup terus, sehingga menambah peran tidak menambah biaya.

| Divisi | Peran | Kapan jalan | Tier |
| --- | --- | --- | --- |
| Strategy & Brand | Brand Strategist | Onboarding, per kuartal | T3 |
| Strategy & Brand | Researcher (pasar, kompetitor, tren, persona) | Onboarding, bulanan, pra-campaign | T0 + T2 |
| Strategy & Brand | Content Strategist | Bulanan | T2/T3 |
| Strategy & Brand | Campaign Planner | Per campaign | T3 |
| Creative | Art Director | Onboarding, per campaign | T3 sekali, lalu aturan T0 |
| Creative | Copywriter | Batch mingguan | T2 |
| Creative | Scriptwriter (Reels/TikTok) | Batch mingguan | T2 |
| Creative | Graphic Designer (render template) | Setelah copy disetujui | T0 |
| Creative | Editor / Brand Guardian | Sebelum approval | T1 |
| Website & SEO | SEO Specialist | Bulanan audit, mingguan monitor | T0 + T2 |
| Website & SEO | SEO Content Writer | Per brief | T2 |
| Website & SEO | Web Maintainer | Terjadwal | T0 |
| Website & SEO | CRO Specialist | Bulanan, per temuan audit | T0 + T2 |
| Audit & Conversion | Website Auditor | Onboarding, kuartalan, saat konversi anjlok | T0 + T2 (+T3 vision opsional) |
| Social Media | Social Media Specialist | Mingguan | T1 + T0 |
| Social Media | Community Manager | Event-driven | T1 + RAG |
| Social Media | KOL/Influencer Specialist | Per campaign | T0 + T2 |
| Performance | Ads Specialist | Per campaign, review mingguan | T2 |
| Data & Analytics | Analytics Engineer (tracking, pipeline, data quality) | Onboarding, monitor harian | T0 |
| Data & Analytics | Analytics Manager (atribusi, funnel, insight) | Mingguan | T0 + T2 |
| Data & Analytics | Data Analyst | Harian/mingguan | T0 |
| Data & Analytics | Report Writer | Mingguan/bulanan | T2 |
| Operations | Project Manager / Traffic (orkestrator) | Selalu | T0 |
| Operations | Account Manager | Event-driven | T1 |
| Operations | Budget Guard (kuota token & biaya media) | Harian | T0 |
| Operations | Onboarding Assistant | Saat klien baru | T1/T2 |
| Operations | QA Publisher (cek link, format, jadwal) | Sebelum publish | T0 |

Tier: T0 tanpa LLM · T1 model murah · T2 DeepSeek · T3 model premium.

## Artefak dan alur kerja

Semua peran membaca dan menulis artefak JSON berversi; artefak adalah satu-satunya "bahasa" antar-peran.

| Artefak | Dibuat oleh | Dipakai oleh | Ukuran target |
| --- | --- | --- | --- |
| Brand Brief | Brand Strategist | Semua peran kreatif | ≤ 1.500 token |
| Visual Rules | Art Director | Graphic Designer, Brand Guardian | ≤ 800 token |
| Research Pack | Researcher | Strategist, Campaign Planner, SEO | ≤ 2.000 token (ringkasan) |
| Campaign Brief | Campaign Planner | Content Strategist, Ads, KOL | ≤ 1.000 token |
| Content Plan | Content Strategist | Copywriter, Scriptwriter, SEO Writer | 1 baris per item |
| Content Item | Copywriter / Scriptwriter | Designer, Guardian, Social Specialist | Per item |
| Audit Report | Website Auditor | CRO, Web Maintainer, Copywriter | Daftar temuan ber-skor ICE |
| Tracking Plan | Analytics Engineer | Web Maintainer, Ads | Taksonomi event |
| KPI Snapshot | Data Analyst | Analytics Manager, Report Writer | ≤ 30 baris angka |
| Experiment | CRO Specialist | Web Maintainer, Analytics Manager | Hipotesis, varian, metrik |

**Alur campaign standar:**

1. Researcher menyusun Research Pack.
2. Campaign Planner menulis Campaign Brief (big idea, KPI, timeline, kanal).
3. Content Strategist memecahnya menjadi Content Plan.
4. Copywriter, Scriptwriter, dan SEO Writer membuat draft secara batch.
5. Graphic Designer merender visual dari Visual Rules.
6. Brand Guardian dan QA Publisher mengecek; item gagal kembali ke langkah 4.
7. Klien/PM menyetujui via dashboard atau Telegram.
8. Social Media Specialist dan Ads Specialist menjadwalkan dan mempublikasikan.
9. Data Analyst membuat KPI Snapshot; Report Writer menulis laporan.

**Loop optimasi:** Analytics Engineer menjaga data bersih → Analytics Manager menemukan masalah → Website Auditor mendiagnosis → CRO Specialist membuat eksperimen → Web Maintainer/Copywriter menerapkan → hasil diukur lagi.

## Kebutuhan fungsional

Setiap modul di bawah adalah satu kumpulan job + layar dashboard; ID dipakai di backlog.

**M1 — Onboarding klien**

- F1.1 Form onboarding (bisnis, produk, audiens, kompetitor, akses akun).
- F1.2 Koneksi akun via OAuth: Meta, TikTok, Google (GA4, Search Console, Ads, Business Profile), CMS.
- F1.3 Generate Brand Brief dan Visual Rules; klien menyetujui sebelum dipakai.

**M2 — Strategi & campaign**

- F2.1 Research Pack otomatis (scraping kompetitor, tren, keyword).
- F2.2 Campaign Brief dengan KPI dan timeline.
- F2.3 Content Plan bulanan dalam tampilan kalender.

**M3 — Produksi kreatif**

- F3.1 Batch generate caption, script, artikel, copy iklan.
- F3.2 Render visual dari template per brand (feed, carousel, story).
- F3.3 Brand Guardian: cek tone, typo, klaim berisiko, panjang per platform.
- F3.4 Revisi per item dari komentar klien.

**M4 — Approval & publish**

- F4.1 Approval via dashboard dan bot Telegram (setujui / revisi / tolak).
- F4.2 Penjadwalan dan auto-publish ke IG, FB, TikTok, Google Business Profile, WordPress.
- F4.3 QA sebelum publish (link, format, jadwal bentrok).

**M5 — Website, SEO & audit**

- F5.1 Crawler + Lighthouse/PageSpeed terjadwal.
- F5.2 Audit konversi 6 lapis (teknis, SEO, tracking, perilaku, pesan & UX, visual opsional).
- F5.3 Pohon diagnosis: tracking → kualitas trafik → kecocokan pesan → friksi → trust → penawaran.
- F5.4 Temuan diprioritaskan skor ICE dan otomatis menjadi tugas.
- F5.5 Eksperimen A/B dan pencatatan hasil.

**M6 — Community & ads**

- F6.1 Inbox komentar/DM terpadu; balasan otomatis dari FAQ, eskalasi ke manusia.
- F6.2 Variasi creative iklan dan saran alokasi budget (eksekusi budget tetap oleh manusia).

**M7 — Analitik first-party & third-party**

- F7.1 Tracking plan per jenis bisnis + konfigurasi GTM.
- F7.2 Tracker first-party self-host + endpoint event server (booking, pembayaran).
- F7.3 Integrasi GA4, Search Console, Clarity, Meta Pixel + Conversions API, TikTok Events API, Google Ads Enhanced Conversions.
- F7.4 Warehouse terpadu, UTM + identity stitching.
- F7.5 Atribusi first-touch, last-touch, position-based; analisis funnel dan cohort.
- F7.6 Monitor kualitas data (event hilang, selisih sumber > ambang).
- F7.7 Consent banner dan Consent Mode.

**M8 — Laporan & operasi**

- F8.1 Laporan mingguan/bulanan otomatis (PDF/web + ringkasan Telegram).
- F8.2 Dashboard biaya token per klien, per peran, per tugas.
- F8.3 Budget guard dan fallback tier.
- F8.4 Log audit setiap panggilan LLM (prompt version, model, token, biaya).

## Jenis bisnis dan pustaka aset

Sistem tidak berasumsi klien menjual produk fisik: setiap klien diberi profil jenis penawaran yang mengatur event konversi, KPI, pilar konten, CTA, dan aset yang dibutuhkan.

| Jenis penawaran | Contoh | Konversi utama | Aset visual utama |
| --- | --- | --- | --- |
| Tiket / event / workshop | Workshop kerajinan, kelas, konser | Booking tiket | Foto & video sesi, proses tangan berkarya, hasil karya peserta, tempat, instruktur, testimoni |
| Aplikasi / produk digital | Aplikasi parenting, SaaS | Install / daftar | Screenshot & rekaman layar, mockup perangkat, ilustrasi/karakter brand, cerita pengguna |
| Jasa / layanan | Konsultan, studio, klinik | Lead / konsultasi | Foto tim & proses kerja, studi kasus, before–after, testimoni |
| Produk fisik | Toko online | Pembelian | Foto produk, foto lifestyle, ulasan pembeli |
| Konten / komunitas | Kelas online, media | Subscribe / join | Cuplikan materi, wajah kreator, kutipan, highlight komunitas |

Data penawaran memakai entitas generik "Offering" (nama, jenis, harga opsional, jadwal, URL, aset), sehingga satu klien bisa punya beberapa penawaran berbeda jenis.

**Sumber aset, urut prioritas:**

1. Aset asli klien: dokumentasi kegiatan, screenshot, foto tim. AI membuat shot list bulanan (daftar foto/video yang perlu diambil) agar stok terus bertambah.
2. Konten pengguna (UGC) dengan izin: foto peserta workshop, ulasan, cerita pengguna.
3. Aset otomatis: screenshot dan rekaman layar aplikasi diambil Playwright; mockup perangkat dari template.
4. Ilustrasi dan latar AI untuk suasana dan konsep. AI tidak dipakai untuk memalsukan hal nyata, misalnya hasil karya peserta, tempat, atau fitur aplikasi yang tidak ada.
5. Foto stok berlisensi sebagai pelengkap.

Foto anak (relevan untuk klien kategori anak/parenting) hanya dari stok berlisensi, ilustrasi, atau dengan izin orang tua.

Pustaka aset menyimpan tag (offering, jenis, suasana, ada/tanpa orang), status izin dan lisensi, serta riwayat pemakaian. Aturan kepatuhan Brand Guardian mengikuti kategori klien, misalnya klaim tumbuh kembang anak untuk aplikasi parenting, atau kebijakan refund dan jadwal untuk tiket.

## Tambahan fungsional v1.1

Tambahan ini disetujui 19 Nov 2026; sembilan masuk MVP, lima di fase 2, satu ditahan.

**MVP**

- F9.1 Profil jenis penawaran per klien yang mengatur tracking plan, KPI, CTA, dan pilar konten.
- F9.2 Knowledge base klien: offering, harga, jadwal, FAQ, testimoni, kebijakan. Menjadi sumber fakta semua peran agar AI tidak mengarang.
- F9.3 Pustaka aset + shot list bulanan.
- F9.4 Belajar dari revisi: alasan revisi/penolakan diringkas menjadi aturan "lakukan/hindari" di Brand Brief; konten berperforma terbaik masuk winning library sebagai contoh.
- F9.5 Paket siap upload: file final, caption, hashtag, dan jadwal dalam satu unduhan, plus pengingat ke HP saat jadwal tiba; admin menandai "sudah diupload".
- F9.6 Konsol job: antrian, job gagal, jalankan ulang, jeda per klien, log.
- F9.7 Kalender momen Indonesia: Ramadan, Lebaran, Harbolnas 11.11 dan 12.12, 17 Agustus, tanggal gajian, libur sekolah, Natal dan tahun baru.
- F9.8 Goal dan KPI utama per klien; laporan dan prioritas audit mengacu ke sini.
- F9.9 Keamanan: login admin dengan 2FA, enkripsi API key dan token akun, backup database harian, log aktivitas.

**Fase 2**

- F9.10 WhatsApp: approval klien, broadcast, katalog WhatsApp Business.
- F9.11 Email marketing dan balasan ulasan Google Business Profile.
- F9.12 Repurposing: satu konten pilar dipecah menjadi banyak format.
- F9.13 Evaluasi prompt per peran: dataset contoh, diuji setiap kali prompt atau model diganti.
- F9.14 Kepatuhan konten per kategori, musik bebas royalti, label konten AI.

**Ditahan:** perintah kerja admin via Telegram (misalnya `/brief`, `/status`). Bot Telegram tetap dipakai untuk notifikasi dan approval klien.

## Penyedia model AI

Admin memasukkan API key dari empat penyedia; setiap peran diberi model utama + cadangan, dan LLM Gateway yang memanggil, mencatat biaya, serta berpindah ke cadangan saat error.

| Penyedia | Teks & strategi | Baca gambar (vision) | Buat gambar | Buat video | Cocok untuk |
| --- | --- | --- | --- | --- | --- |
| Anthropic Claude | Ya | Ya | Tidak | Tidak | Strategi, campaign, audit pesan & UX, QA |
| OpenAI (GPT, GPT Image) | Ya | Ya | Ya | Tidak | Copy, gambar, cadangan strategi |
| Google Gemini (+ Veo) | Ya | Ya | Ya | Ya | Video, gambar, riset konteks panjang |
| DeepSeek | Ya | Cek saat implementasi | Tidak | Tidak | Produksi teks massal (T2) |

Catatan:

- Codex adalah agent coding dari OpenAI. Untuk sistem ini yang dipakai adalah API key OpenAI (model GPT dan GPT Image); Codex bisa dipakai terpisah untuk membantu membangun kode.
- Semua penyedia dipanggil lewat API key berbayar, bukan login langganan chat (Claude Pro, ChatGPT Plus, Gemini app).
- API video Sora milik OpenAI sudah dihentikan per 24 September 2026, jadi video dibuat lewat Gemini/Veo ([OpenAI docs](https://developers.openai.com/api/docs/guides/video-generation/index.html)).

**Fitur admin untuk model:**

- Simpan API key terenkripsi per penyedia, dengan tombol tes koneksi.
- Katalog model: nama, kemampuan (teks/vision/gambar/video), dan harga per 1 juta token, per gambar, atau per detik video. Harga diisi dan diperbarui admin.
- Per peran: model utama, model cadangan, batas biaya per panggilan.
- Fallback otomatis saat penyedia error, kena rate limit, atau kuota habis.
- Mode bandingkan: satu tugas dijalankan di dua model, hasil dan biaya dibandingkan berdampingan untuk memilih default.
- Adapter per penyedia, sehingga model baru cukup ditambah lewat katalog tanpa ubah kode.

**Contoh pemetaan awal (bisa diubah di dashboard):**

| Peran | Model utama | Cadangan |
| --- | --- | --- |
| Brand Strategist, Campaign Planner | Claude (model terkuat) | GPT |
| Copywriter, Content Strategist, Report Writer, SEO Writer | DeepSeek | Gemini Flash |
| Brand Guardian, Community Manager, Account Manager | Claude Haiku | Gemini Flash |
| Website Auditor (dengan screenshot) | Claude | Gemini |
| Generate gambar | GPT Image | Gemini |
| Generate video | Veo 3.1 (Lite untuk draft) | Gemini Omni Flash |

## Produksi visual dan video dari brief

Admin bisa memberi brief dan AI membuat gambar serta video; hasilnya tetap lewat Brand Guardian dan approval sebelum terbit.

| Mode | Cocok untuk | Cara kerja | Biaya |
| --- | --- | --- | --- |
| Render template | Feed, carousel, story, promo berulang | Teks dari AI + template HTML/SVG brand; video template (Remotion/FFmpeg) dari teks, foto produk, musik | Hampir nol, hanya server |
| Generate gambar AI | Key visual campaign, ilustrasi, foto produk lifestyle | GPT Image atau Gemini; prompt disusun dari brief + Visual Rules; foto asli dari pustaka aset (tempat, kegiatan, produk, screenshot aplikasi) bisa jadi referensi | Per gambar |
| Generate video AI | Klip Reels/TikTok, B-roll, iklan pendek | Veo 3.1 atau Gemini Omni Flash membuat klip pendek, lalu dirangkai | Per detik video |

Video AI adalah pengeluaran terbesar: Veo 3.1 di Gemini API berkisar $0,05–$0,60 per detik; satu klip 8 detik 1080p sekitar $0,64 (Lite), $0,96 (Fast), atau $3,20 (Standard) ([Layer3 Labs, Sep 2026](https://www.layer3labs.io/guides/veo-3-pricing)). Harga berubah cepat, jadi disimpan di katalog model.

**Alur dari brief:**

1. Admin mengisi brief: tujuan, pesan, format, durasi, referensi, dan aset dari pustaka.
2. Art Director dan Scriptwriter (LLM) mengubahnya menjadi storyboard per shot + prompt visual sesuai Visual Rules.
3. Admin menyetujui storyboard. Gerbang ini murah dan mencegah biaya media terbuang.
4. Generate draft murah: gambar kualitas rendah atau video Lite 720p, 2–4 varian.
5. Admin memilih varian, lalu render final kualitas tinggi.
6. Komposisi otomatis: logo, teks, subtitle, voice-over (TTS), dan musik dengan FFmpeg/Remotion.
7. Brand Guardian, approval klien, lalu publish.

**Kendali biaya dan risiko:**

- Batas biaya media per klien per bulan, terpisah dari kuota token.
- Draft selalu di kualitas termurah; kualitas tinggi hanya untuk varian terpilih.
- Pustaka aset: B-roll dan gambar yang sudah dibuat bisa dipakai ulang.
- Tidak membuat wajah orang nyata atau tokoh terkenal; mengikuti aturan label konten AI di Meta dan TikTok.

## Arsitektur teknis

Versi 1 berjalan lokal di MacBook (local-first); VPS hanya opsional di fase 2 untuk bagian ringan yang harus selalu online.

| Lapisan | Komponen | Catatan |
| --- | --- | --- |
| Aplikasi | Web app di localhost + worker | Docker Compose atau native di macOS |
| Workflow & antrian | Redis queue + state machine | Job yang terlewat saat Mac mati dijalankan begitu Mac menyala |
| Data | Postgres lokal | Backup harian ke disk eksternal atau cloud |
| LLM Gateway | Layanan internal | Routing 4 penyedia, cache, validasi, log biaya |
| Render | FFmpeg (akselerasi hardware Apple) + Playwright/Remotion | Render gambar dan video di Mac |
| Notifikasi & approval klien | Bot Telegram (long polling) | Tidak butuh alamat publik, bisa jalan dari lokal |
| Publish | API platform saat Mac menyala; selain itu paket siap upload | Lihat F9.5 |
| Analitik | GA4, Search Console, Clarity, Meta, TikTok via API | Ditarik dari lokal secara terjadwal |
| Relay VPS (fase 2, opsional) | Tracker first-party, penerima webhook komentar/DM, endpoint event server | Bagian ringan yang harus selalu online |

**Konsekuensi local-first.** Publish terjadwal dan balasan komentar otomatis hanya jalan saat Mac menyala. Jika jadwal terlewat, sistem langsung menyiapkan paket siap upload dan mengirim pengingat. Tracker first-party dan webhook butuh alamat publik, jadi baru aktif lewat relay VPS di fase 2.

**LLM Gateway adalah inti penghematan.** Semua panggilan LLM wajib lewat gateway; tidak ada peran yang memanggil API model langsung. Prompt disimpan berversi agar hasil dan biaya bisa dibandingkan antar versi.

## Non-fungsional, privasi, dan keamanan

Tidak ada konten yang terbit dan tidak ada uang iklan yang berpindah tanpa persetujuan manusia.

- **Human-in-the-loop:** publish, balasan sensitif, dan perubahan budget iklan selalu butuh approval.
- **Privasi:** consent banner, Consent Mode, minimalisasi data, dan retensi terbatas sesuai UU PDP (UU 27/2022); data pribadi di-hash sebelum dikirim ke Conversions API.
- **Keamanan:** token OAuth dan API key terenkripsi; akses dashboard per peran (PM, klien, viewer); isolasi data antar klien.
- **Keandalan:** job idempotent dengan retry; publish gagal memberi alert, tidak dicoba ulang diam-diam.
- **Kualitas output:** semua output LLM divalidasi JSON schema; gagal validasi diulang sekali, lalu masuk antrian manual.
- **Biaya:** kuota token per klien, alert di 80%, fallback tier di 100%.
- **Kepatuhan platform:** mengikuti batas rate dan kebijakan Meta, TikTok, dan Google; tidak ada otomasi yang melanggar ToS (misalnya auto-follow atau scraping akun pribadi).

## Ditunda sampai keputusan SaaS

Fitur berikut sengaja tidak dibangun di versi pribadi, tetapi struktur datanya disiapkan:

- Multi-user, tim, dan hak akses per peran (PM, staf, viewer).
- Paket/plan, kuota per plan, invoice, dan pembayaran.
- Onboarding mandiri untuk agensi lain dan white-label.
- Portal klien dengan login.

Syarat untuk mulai mengevaluasi SaaS: minimal 10 klien berjalan stabil dengan biaya AI per klien yang terprediksi.

## Roadmap

MVP sekitar 6 minggu dan diuji dengan satu klien pilot (dogfooding); produk tetap generik, klien pilot tidak menjadi acuan desain, lalu fase 2 dan 3 masing-masing sekitar 2 bulan; setiap fase punya gerbang sebelum lanjut.

| Fase | Durasi | Cakupan | Gerbang lanjut |
| --- | --- | --- | --- |
| MVP | \~6 minggu | Onboarding + Brand Brief, Researcher, Content Strategist, Copywriter, Graphic Designer (template), Brand Guardian, approval Telegram, auto-publish IG + paket siap upload, profil jenis bisnis, knowledge base & pustaka aset, konsol job, kalender momen, Data Analyst + Report Writer, LLM Gateway + dashboard biaya | Klien pilot berjalan 4 minggu; biaya token per bulan terukur; ≥ 80% konten disetujui tanpa revisi besar |
| Fase 2 | \~2 bulan | Website Auditor, SEO Specialist + SEO Writer, Analytics Engineer (tracking plan, first-party tracker, CAPI), Scriptwriter, TikTok + FB, Community Manager, Art Director penuh, WhatsApp, repurposing, evaluasi prompt, kepatuhan per kategori, relay VPS | 3–5 klien berjalan; audit menghasilkan perbaikan yang terukur |
| Fase 3 | \~2 bulan | Campaign Planner, Ads Specialist, CRO + A/B test, atribusi, KOL Specialist, perintah admin via Telegram (ditahan), portal klien sederhana, evaluasi kesiapan SaaS | 10+ klien; biaya AI per klien stabil dan terprediksi |

**Urutan kerja MVP:**

1. Minggu 1: skema artefak (Brand Brief, Content Plan, Content Item), LLM Gateway, log token.
2. Minggu 2: onboarding + profil jenis bisnis + knowledge base + Brand Brief + Research Pack.
3. Minggu 3: Content Plan + batch copy + Brand Guardian.
4. Minggu 4: render template + approval Telegram.
5. Minggu 5: auto-publish IG / paket siap upload + KPI Snapshot + laporan mingguan.
6. Minggu 6: uji penuh dengan klien pilot, perbandingan biaya dengan setup Paperclip.

## KPI, risiko, dan pertanyaan terbuka

Produk dinilai berhasil bila biaya per klien rendah dan stabil, sementara hasil marketing klien naik.

**KPI produk**

| KPI | Target awal |
| --- | --- |
| Biaya token per klien per bulan | Ditetapkan dari baseline pilot, lalu turun tiap fase |
| Konten disetujui tanpa revisi besar | ≥ 80% |
| Waktu PM per klien per minggu | ≤ 1 jam |
| Klien per PM | 20–50 |
| Retensi klien bulanan | ≥ 90% |
| Kenaikan konversi klien setelah audit | Diukur per eksperimen |

**Risiko dan mitigasi**

| Risiko | Mitigasi |
| --- | --- |
| Konten terasa generik | Brand Brief kuat, Brand Guardian, contoh konten terbaik klien sebagai few-shot |
| Perubahan API/kebijakan Meta & TikTok | Adapter per platform, fallback ke ekspor manual |
| Akses akun klien sulit didapat | Onboarding bertahap, mulai dari kanal yang paling mudah |
| Biaya token melonjak | Budget guard, log per tugas, review prompt version |
| Data analitik tidak akurat | Monitor kualitas data, event server-side sebagai sumber utama |
| MacBook mati atau sleep saat jadwal publish | Catch-up otomatis, paket siap upload + pengingat, relay VPS di fase 2 |

**Pertanyaan terbuka**

- [ ] Nama produk: Agensi Sebelah (diputuskan 19 Nov 2026)
- [ ] Stack backend: memakai stack yang sama dengan project lain (misalnya Gawean/ReportFlow) atau baru?
- [ ] Workflow engine: buat sendiri atau n8n/Temporal?
- [ ] Pemetaan model default per peran (Claude, OpenAI, Gemini, DeepSeek)?
- [ ] Batas biaya bulanan untuk generate gambar dan video?
- [ ] Mode pemakaian: pribadi dulu, SaaS dievaluasi setelah hasil terbukti (diputuskan 19 Nov 2026)
