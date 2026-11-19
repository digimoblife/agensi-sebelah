# Runbook — Publish Instagram & paket siap upload

Status: Minggu 5 · 19 Nov 2026 · Rujukan: F4.2, F9.5, ADR 0003, DECISIONS Minggu 5

## Dua cara terbit

| Cara | Kapan dipakai | Yang terjadi saat jadwal tiba |
| --- | --- | --- |
| **Otomatis (API)** | Instagram, format feed/carousel/story, akun IG terhubung, dan `PUBLIC_MEDIA_BASE_URL` diisi | Diposting lewat Instagram Content Publishing API |
| **Paket siap upload** | Semua kasus lain (FB, TikTok, GBP, reel, belum ada tunnel, atau koneksi bermasalah) | Paket ZIP dibuat dan pengingat dikirim ke Telegram admin berisi gambar, caption, dan tombol "Sudah diupload" |

Jadwal yang terlewat lebih dari 30 menit (Mac mati/sleep) **tidak diposting otomatis**. Sistem membuat paket dan mengirim pengingat "Jadwal terlewat" (ADR 0003). Publish yang gagal tidak diulang otomatis; admin memilih *Coba terbit lagi* atau *Jadikan paket manual* di menu Jadwal terbit.

## Syarat publish otomatis Instagram

1. Akun Instagram klien berjenis **Business/Creator** dan tertaut ke sebuah **Facebook Page**.
2. Meta App (Kredensial → Meta) dengan izin `instagram_content_publish`, `instagram_basic`, `instagram_manage_insights`, `pages_show_list`, `pages_read_engagement`, `business_management`. Sebelum App Review, akun klien harus ditambahkan sebagai *tester/role* di app (mode development).
3. Tambahkan **Valid OAuth Redirect URI** di Facebook Login: `http://localhost:8088/oauth/meta/callback` (tertulis juga di halaman Kredensial).
4. Klien → tab *Koneksi akun* → **Hubungkan Instagram**. Bila ada beberapa akun, pilih satu.
5. **Tunnel media publik**. Instagram mengambil gambar lewat URL publik, sedangkan sistem berjalan lokal. Arahkan tunnel (mis. Cloudflare Tunnel) ke **`http://127.0.0.1:8089`**, yaitu server nginx yang hanya melayani `/media/<ulid>` dengan tanda tangan berumur 2 jam. **Jangan** arahkan tunnel ke 8088 (dashboard). Lalu isi `.env`:

   ```
   PUBLIC_MEDIA_BASE_URL=https://media.domain-anda.com
   ```

   dan jalankan `docker compose up -d` agar nilai terbaca.

## GA4

1. Kredensial → Google OAuth (Client ID/Secret). Redirect URI: `http://localhost:8088/oauth/google/callback`.
2. Klien → *Koneksi akun* → **Hubungkan Google Analytics** → **Atur Property ID** (angka dari Admin GA4).
3. Event konversi yang ditarik = event dari profil jenis penawaran + `metric_key` di Goal & KPI klien. Pastikan nama event di GA4 sama (mis. `ticket_booking`, `sign_up`, `lead`, `purchase`).

## Jadwal otomatis (scheduler)

| Perintah | Kapan | Catatan |
| --- | --- | --- |
| `publish:due` | tiap menit | publish / paket + pengingat; catch-up jadwal terlewat |
| `metrics:pull` | 03:00 WIB | menarik data sampai kemarin; mengejar hari yang terlewat (maks. 30 hari) |
| `reports:weekly` | tiap jam 08:00–23:00 WIB | membuat laporan minggu lalu untuk klien aktif yang punya sumber data terhubung, sekali per minggu |
