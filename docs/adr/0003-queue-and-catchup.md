# ADR 0003 — Antrian, penjadwalan, dan catch-up saat Mac mati

Status: Diterima · 19 Nov 2026 · Terkait PRD §Arsitektur, risiko "MacBook mati atau sleep", K10

## Keputusan

- **Redis + Laravel Queue + Horizon**, sama dengan ReportFlow. Antrian: `default`, `llm`, `render`, `publish`,
  `analytics`, `video` (native, Fase 2). Antrian `llm` dibatasi konkurensinya agar rate limit penyedia aman.
- **Redis bukan sumber kebenaran.** Semua job penting punya baris di `job_runs` (Postgres) dengan
  `idempotency_key` unik. Jika Redis hilang, scheduler bisa menjadwal ulang dari Postgres.
- **Catch-up.** Scheduler tiap menit memilih `scheduled_tasks` dengan `due_at <= now()` dan `status = pending`.
  Untuk setiap tugas yang telat lebih dari ambang per jenis:
  - `publish`: jika telat > 30 menit, **tidak** dipublikasikan otomatis. Sistem membuat paket siap upload (F9.5),
    mengirim pengingat Telegram, dan menandai `missed`. Admin memilih "publish sekarang" atau "sudah diupload".
  - `analytics_pull`, `report`, `audit`: dijalankan saat itu juga, sekali saja walau beberapa periode terlewat
    (tarik data untuk rentang yang terlewat).
  - `research`, `content_batch`: dijalankan, lalu admin diberi tahu bahwa tugas berjalan terlambat.
- **Mencegah sleep.** Agent `launchd` menjalankan `caffeinate -i` selama ada `publish` dalam 2 jam ke depan
  (opsional, bisa dimatikan). Docker Desktop diatur start saat login.

## Konsekuensi

- Perilaku "telat → paket manual" harus diuji dengan jam palsu (`Carbon::setTestNow`).
- Relay VPS (Fase 2) menangani bagian yang harus selalu online; ADR terpisah saat itu.
