# Arsitektur — Agensi Sebelah v1

Status: draf fondasi · 19 Nov 2026 · Rujukan: PRD §Arsitektur teknis, ADR 0001–0004

## Komponen

```
                         ┌──────────────── MacBook (Docker Compose) ────────────────┐
 Admin ── browser ─────► │ app (Laravel + Filament)                                 │
                         │   │                                                      │
 Klien ── Telegram ◄───► │ telegram-poller ──► ApprovalService                      │
                         │   │                                                      │
                         │ scheduler (tiap menit) ──► scheduled_tasks ──► queue     │
                         │                                              │           │
                         │ worker (Horizon): default · llm · render · publish ·     │
                         │                   analytics                              │
                         │   │          │            │            │                 │
                         │ LlmGateway  renderer     Publishers   Analytics pullers  │
                         │   │        (Node+PW)      │            │                 │
                         │ postgres · redis · gotenberg                             │
                         └───┼──────────────────────┼────────────┼─────────────────┘
                             ▼                      ▼            ▼
                Anthropic / OpenAI /         Meta Graph (IG)   GA4, IG Insights
                Gemini / DeepSeek            Fase 2: TikTok,   Fase 2: GSC, Clarity,
                                             FB, GBP, WP       TikTok, CAPI
```

## Alur inti (alur campaign standar PRD, versi MVP)

1. **Onboarding** (F1.1, F9.1, F9.2): admin mengisi form → `clients`, `offerings`, `knowledge_items`, `kpi_goals`.
2. **Brand Brief + Visual Rules** (F1.3): job `RunRole(brand_strategist)` → gateway → artefak `brand_brief` v1
   status `draft` → approval klien → `approved` (hanya versi `approved` yang dipakai peran lain).
3. **Research Pack** (F2.1): T0 mengumpulkan data (input manual + sumber publik) → T2 merangkum ke artefak.
4. **Content Plan** (F2.3, F9.7): Content Strategist menerima Brand Brief + Research Pack + kalender momen +
   offering aktif → satu baris per item.
5. **Batch copy** (F3.1): Copywriter dipanggil per batch (mis. 10–30 item per panggilan) → `content_item` per item.
6. **Brand Guardian** (F3.3): T0 rule (panjang per platform, hashtag, link, kata terlarang) dulu, lalu T1 untuk
   tone & klaim risiko. Gagal → kembali ke Copywriter dengan alasan (revisi parsial, item itu saja).
7. **Render** (F3.2): `renderer` mengisi template brand → file di pustaka aset.
8. **Approval** (F4.1): Telegram ke klien. Revisi → F3.4. Alasan revisi dikumpulkan untuk F9.4.
9. **Publish** (F4.2, F4.3, F9.5): QA Publisher (T0) → jadwal → publish IG via API, atau paket siap upload.
10. **KPI & laporan** (F7, F8.1): Data Analyst (T0, SQL) → `kpi_snapshot` → Report Writer (T2) → PDF Gotenberg → Telegram.

## Batas modul

| Modul | Folder | Tanggung jawab |
| --- | --- | --- |
| Llm | `app/Services/Llm` | Satu-satunya jalan ke penyedia AI. Routing, fallback, budget guard, cache, validasi, log. Lihat `LLM-GATEWAY.md`. |
| Roles | `app/Roles`, `config/roles.php` | Definisi peran: input artefak, prompt, output schema, tier. Peran tidak tahu penyedia mana yang dipakai. |
| Artifacts | `app/Services/Artifacts` | Simpan/versi/validasi artefak, ambil versi `approved` terbaru. |
| Workflow | `app/Services/Workflow` | State machine + `workflow_events`. Memicu job. |
| Knowledge | `app/Services/Knowledge` | Knowledge base & pustaka aset klien; menyusun konteks fakta untuk peran. |
| Render | `app/Services/Render` | Klien HTTP ke `renderer`, template per brand. |
| Publish | `app/Services/Publish` | Adapter per platform + paket siap upload. |
| Telegram | `app/Services/Telegram` | Poller, client, template pesan `lang/id`. |
| Analytics | `app/Services/Analytics` | Puller per sumber, warehouse tabel `metrics_daily`, KPI snapshot. |
| Budget | `app/Services/Budget` | Kuota biaya per klien (LLM & media), alert, keputusan fallback tier. |

## Keamanan (ringkas; detail di `SECURITY.md` nanti)

- Dashboard hanya di `localhost`, login admin + 2FA (TOTP Filament).
- API key penyedia & token OAuth dienkripsi (`encrypted` cast Laravel, `APP_KEY` disimpan di Keychain/1Password, bukan di repo).
- Backup harian `pg_dump` terenkripsi ke disk eksternal / cloud; uji restore bulanan.
- `activity_log` untuk aksi admin; `llm_calls` untuk semua panggilan AI.

## Yang sengaja belum ada di v1

Relay VPS, webhook masuk, tracker first-party, tautan web untuk klien, multi-user. Struktur data disiapkan
(`workspace_id`, kolom `role` di users) tanpa fitur.
