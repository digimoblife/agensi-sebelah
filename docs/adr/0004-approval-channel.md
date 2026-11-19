# ADR 0004 — Kanal approval klien dan bot Telegram

Status: Diterima · 19 Nov 2026 · Terkait F4.1, K1

## Konteks

PRD menyebut klien menyetujui konten lewat "tautan approval/laporan atau Telegram, tanpa login". Sistem berjalan
lokal tanpa alamat publik, jadi tautan web tidak bisa dibuka klien di MVP.

## Keputusan

- **MVP: approval klien hanya lewat Telegram.** Bot mengirim preview (gambar + caption) dengan tombol inline
  `Setujui / Revisi / Tolak`. Revisi meminta komentar teks yang menjadi input revisi per item (F3.4).
- Setiap klien terhubung ke bot lewat kode undangan sekali pakai (`/start <kode>`); `telegram_chat_id` disimpan
  di `client_contacts`. Hanya chat terdaftar yang bisa approve item milik kliennya.
- **Long polling** (`php artisan telegram:poll` di container `telegram-poller`), bukan webhook, karena tidak ada
  alamat publik. Update diproses idempotent berdasarkan `update_id`.
- Callback tombol membawa token acak berumur pendek yang dipetakan ke `approvals.id`, bukan id mentah.
- Laporan mingguan dikirim sebagai PDF + ringkasan teks di Telegram.
- Admin juga bisa approve dari dashboard (Filament). Kedua jalur memakai service yang sama (`ApprovalService`).
- Tautan web approval/laporan ditunda sampai ada relay VPS atau tunnel (Fase 2).

## Konsekuensi

- Pengingat jadwal upload (F9.5) juga lewat Telegram ke admin.
- Perintah kerja admin via Telegram tetap ditahan sesuai PRD.
