# ADR 0002 — Workflow engine

Status: Diterima · 19 Nov 2026 · Menjawab pertanyaan terbuka PRD "Workflow engine"

## Opsi

| Opsi | Kelebihan | Kekurangan |
| --- | --- | --- |
| n8n | Visual, banyak konektor | Logic tersebar di luar repo, sulit di-test & di-versi, validasi schema dan budget guard harus dibuat ulang di node, menambah satu layanan |
| Temporal | Durable execution kuat | Berat untuk satu MacBook (server + DB sendiri), SDK PHP kurang matang, berlebihan untuk volume 20–50 klien |
| **Buat sendiri** | Semua di Postgres + kode Laravel, mudah di-test, cocok dengan prinsip "orkestrator deterministik" | Harus membuat sendiri retry, catch-up, dan konsol job |

## Keputusan

State machine buatan sendiri:

- Setiap entitas yang punya alur (`content_items`, `campaigns`, `media_briefs`, `onboardings`, `publish_jobs`)
  memiliki kolom `status` dan kelas transisi di `app/Services/Workflow/<Entity>Workflow.php`.
- Transisi hanya lewat `Workflow::transition($entity, $to, $actor, $reason)`. Transisi tidak sah melempar exception.
  Setiap transisi dicatat di `workflow_events`.
- Efek samping transisi (memanggil peran, render, kirim approval) dijalankan sebagai job ke antrian, bukan sinkron.
- Penjadwalan: tabel `scheduled_tasks` (jenis, `client_id`, `due_at`, `payload_ref`, `status`) dipindai scheduler
  Laravel tiap menit. Ini juga yang menangani catch-up (ADR 0003).
- Konsol job (F9.6) membaca `job_runs` + Horizon.

## Konsekuensi

- Tidak ada "CEO agent". Peran tidak memanggil peran lain; hanya workflow yang memicu peran.
- Matriks transisi didokumentasikan di `docs/WORKFLOWS.md` dan dikunci dengan test.
