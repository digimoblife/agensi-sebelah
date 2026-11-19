# Checklist Pilot (Minggu 6)

Status: 19 Nov 2026 · Rujukan: PRD §Roadmap (gerbang MVP), `docs/RUNBOOK-PUBLISH.md`, `docs/RUNBOOK-OPS.md`

Gerbang MVP: **klien pilot berjalan 4 minggu**, **biaya token per bulan terukur**, dan **≥ 80 % konten disetujui tanpa revisi besar**.
Semua dipantau di dashboard **Kesiapan & gerbang MVP** dan lewat perintah:

```
docker compose exec app php artisan pilot:check
```

## A. Satu kali, sebelum pilot (oleh admin)

| # | Langkah | Di mana | Cek |
| --- | --- | --- | --- |
| 1 | Buat akun admin, login (MFA opsional, disarankan: Profil → aktifkan) | `php artisan make:filament-user` | pilot:check "Akun admin" ✓ |
| 2 | API key minimal satu penyedia AI + Tes koneksi | Pengaturan → Kredensial | "API key AI tervalidasi" ✓ |
| 3 | Tambah model + harga untuk penyedia selain Claude | Pengaturan → Katalog model | kolom "Siap dipakai" ✓ |
| 4 | Atur model utama + cadangan untuk 7 peran | Pengaturan → Model per peran | baris "Model AI" ✓ |
| 5 | Bot Telegram (@BotFather) → token → Tes koneksi → *Hubungkan Telegram saya* | Kredensial | "Bot Telegram", "Telegram admin terhubung" ✓ |
| 6 | Meta App + Redirect URI; Google OAuth + Redirect URI | Kredensial (URI tertulis di halaman) | ! boleh menyusul |
| 7 | Arahkan backup ke disk eksternal/folder sinkron (`BACKUP_HOST_DIR`) | `.env` | "Backup harian" ✓ |
| 8 | (Opsional) tunnel media → `PUBLIC_MEDIA_BASE_URL` | `.env`, RUNBOOK-PUBLISH | "Tunnel media publik" ✓ |
| 9 | Isi baseline biaya Paperclip per klien per bulan | Kesiapan & gerbang MVP → *Baseline biaya Paperclip* | kolom "vs Paperclip" terisi |

## B. Uji kering dengan klien demo (±30 menit, sebelum data asli)

```
docker compose exec app php artisan demo:seed --with-artifacts
```

1. Klien `[DEMO] Studio Contoh` → Artefak → *Buat Content Plan* (bulan depan, 1 konten/minggu). Tinjau → Setujui.
2. Konten → pilih item → *Buat / ulang draf*. Tunggu notifikasi. Periksa Brand Guardian dan visual.
3. Kontak Telegram → tambah diri Anda sebagai kontak demo → *Buat tautan undangan* → buka di Telegram → setujui konten dari bot.
4. Konten → *Jadwalkan* (beberapa menit ke depan). Tunggu paket + pengingat di Telegram → *Sudah diupload*.
5. Log panggilan AI: catat biaya satu siklus. Konsol job: pastikan tidak ada job gagal.
6. Hapus demo: `php artisan demo:seed --purge`.

## C. Onboarding klien pilot

1. Klien baru: profil, jenis penawaran, budget AI bulanan, jawaban onboarding, akses akun.
2. Penawaran, **knowledge base** (minimal harga, jadwal, kebijakan, 3–5 FAQ) → *Setujui*.
3. Goal & KPI (prioritas 1 = metrik utama, mis. booking/lead/pembelian).
4. Pustaka aset: logo + ≥ 5 foto asli (isi izin untuk foto berisi orang/anak).
5. Bahan riset (catatan kompetitor, keyword) → *Buat Research Pack* → Setujui.
6. *Buat Brand Brief* → tinjau bersama klien → Setujui. *Buat Visual Rules* → Setujui.
7. Kontak Telegram klien (approver) → kirim tautan undangan.
8. Koneksi akun: Instagram dan GA4 (bila sudah siap). Tanpa itu konten tetap jalan lewat paket manual, tetapi laporan mingguan tidak dibuat.
9. `pilot:check <slug-klien>`: tidak ada ✗.

## D. Rutinitas mingguan selama 4 minggu

| Kapan | Apa | Target |
| --- | --- | --- |
| Awal bulan | Content Plan bulan berikutnya → tinjau → setujui | ≤ 20 menit |
| Mingguan | *Buat / ulang draf* untuk minggu depan; tangani item *Gagal cek Guardian* | ≤ 20 menit |
| Harian (otomatis) | Klien menyetujui di Telegram; konten terjadwal terbit/paket + pengingat | — |
| Senin (otomatis) | Laporan mingguan ke klien; baca di menu Laporan | — |
| Jumat | Buka *Kesiapan & gerbang MVP*: % tanpa revisi besar, biaya bulan ini, job gagal | catat di tabel E |

**Waktu PM per klien** (KPI ≤ 1 jam/minggu) belum diukur otomatis; catat manual di tabel E.

## E. Catatan mingguan (isi selama pilot)

| Minggu | Konten diputuskan | % tanpa revisi besar | Biaya AI (USD) | Waktu PM (menit) | Masalah / perbaikan |
| --- | --- | --- | --- | --- | --- |
| 1 | | | | | |
| 2 | | | | | |
| 3 | | | | | |
| 4 | | | | | |

## F. Perbandingan dengan Paperclip

1. Baseline = biaya AI per klien per bulan dengan setup Paperclip untuk volume konten yang setara (isi di dashboard).
2. Pembanding = biaya bulan berjalan di dashboard (LLM + media), dan biaya per konten terbit.
3. Bandingkan juga kualitas: % tanpa revisi besar dan waktu PM. Penghematan biaya tanpa kualitas setara tidak dihitung lulus.
