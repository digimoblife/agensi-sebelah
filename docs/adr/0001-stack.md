# ADR 0001 — Stack aplikasi

Status: Diterima · 19 Nov 2026 · Menjawab pertanyaan terbuka PRD "Stack backend"

## Konteks

PRD menanyakan apakah memakai stack yang sama dengan project lain. ReportFlow sudah memakai Laravel 13,
PostgreSQL, Redis, Filament 5, Pest, dan punya pola yang relevan: `AIService` + `ai_interactions`, prompt berversi
dengan checksum, eval dataset, bot Telegram dengan template `lang/`, job idempotent, dan isolasi test dari layanan nyata.
Agensi Sebelah dikembangkan oleh satu orang, jadi memakai ulang pola mengurangi biaya belajar dan bug.

## Keputusan

| Lapisan | Pilihan |
| --- | --- |
| Backend & dashboard | Laravel 13 (PHP 8.3), Filament 5 |
| Database | PostgreSQL 17 (lokal, Docker) |
| Antrian | Redis + Laravel Queue + Horizon (ADR 0003) |
| Workflow | State machine buatan sendiri di Postgres (ADR 0002) |
| Test & kualitas | Pest, Pint, Larastan level 8 |
| PDF laporan | Gotenberg |
| Render visual | Service `renderer` (Node 22 + Playwright): HTML/SVG template → PNG/JPG, screenshot aplikasi |
| Video | FFmpeg & Remotion dijalankan **native di macOS** (bukan di container) |
| Validasi JSON Schema | `opis/json-schema` (PHP, draft 2020-12) |
| Bot | Telegram Bot API, long polling (ADR 0004) |

### Render

Docker di macOS tidak bisa memakai akselerasi hardware Apple (VideoToolbox). Karena itu render video berjalan
sebagai worker native (`php artisan queue:work --queue=video` di host, atau skrip yang dipanggil lewat antrian `video`).
Render gambar statis tetap di container `renderer`. Video baru dibutuhkan di Fase 2, jadi MVP cukup `renderer`.

## Konsekuensi

- Pola dari ReportFlow (gateway, recorder, prompt repository, fake provider, test isolation) disalin lalu
  diadaptasi, bukan dijadikan paket bersama. Dibuat paket bersama nanti jika ada project ketiga yang memakainya.
- Dua runtime (PHP + Node). Node hanya untuk `renderer`, diakses lewat HTTP internal.
- Seluruh stack berjalan di satu Mac; lihat ADR 0003 untuk perilaku saat Mac mati/sleep.

## Amandemen (Minggu 4, 19 Nov 2026): render gambar lewat Gotenberg

Untuk MVP, render template visual memakai route screenshot Chromium milik Gotenberg
(`/forms/chromium/screenshot/html`), bukan service Node + Playwright terpisah. Gotenberg sudah ada di stack, sudah
memakai Chromium, dan bisa menerima HTML + file (logo, foto, font) dalam satu request. Service Node + Playwright
ditunda sampai benar-benar dibutuhkan (screenshot aplikasi otomatis F9.3, Remotion untuk video di Fase 2).
Font Google diunduh oleh container `app` (punya akses internet) dan di-cache, karena Gotenberg hanya ada di
jaringan internal.
