# Data Model — Agensi Sebelah v1

Status: draf fondasi · 19 Nov 2026 · Konvensi: PostgreSQL, `timestamptz` UTC, id `bigint` + `ulid` publik bila
terekspos (callback Telegram, nama file), enum = `varchar` + `CHECK`.

**Aturan scoping:** setiap tabel data klien punya `workspace_id` dan `client_id` (NOT NULL, FK, ber-index).
v1 punya satu workspace yang di-seed. Tabel global (katalog model, kalender momen) tidak punya `client_id`.

## Ringkasan relasi

```
workspaces ─┬─ users (v1: 1 admin)
            ├─ provider_credentials ── model_catalog ── role_configs
            └─ clients ─┬─ client_contacts (telegram_chat_id)
                        ├─ offerings ── offering_types (profil F9.1, global)
                        ├─ knowledge_items
                        ├─ kpi_goals
                        ├─ assets ── asset_usages
                        ├─ artifacts ── artifact_versions
                        ├─ content_items ── approvals, publish_jobs
                        ├─ budgets
                        ├─ llm_calls, media_generations
                        └─ metrics_daily, oauth_connections
global: moment_calendar, offering_types, model_catalog
ops: scheduled_tasks, job_runs, workflow_events, activity_log
```

## Tabel (MVP)

### Tenant & klien

| Tabel | Kolom penting | Catatan |
| --- | --- | --- |
| `workspaces` | id, name | Seed 1 baris. Siap SaaS. |
| `users` | id, workspace_id, email, password, two_factor_secret (enc), role | `role` = `owner` di v1. |
| `clients` | id, workspace_id, ulid, name, slug, status (`onboarding/active/paused/archived`), timezone, primary_offering_type | Jeda per klien (F9.6) = `paused`. |
| `client_contacts` | client_id, name, telegram_chat_id, invite_code_hash, can_approve | ADR 0004. |
| `offering_types` | key (`event_ticket/digital_app/service/physical_product/content_community`), conversion_events json, default_kpis json, content_pillars json, ctas json, required_assets json | Profil F9.1, global, di-seed dari tabel PRD. |
| `offerings` | client_id, offering_type_key, name, price nullable, schedule json nullable, url, status | Entitas generik "Offering". |
| `knowledge_items` | client_id, offering_id nullable, kind (`faq/policy/testimonial/fact/price/schedule/do_dont`), title, body, source, valid_until, approved_at | Sumber fakta F9.2. Hanya yang `approved_at` not null dipakai peran. |
| `kpi_goals` | client_id, metric_key, target_value, period, priority | F9.8. |

### Artefak

| Tabel | Kolom penting | Catatan |
| --- | --- | --- |
| `artifacts` | id, client_id, type (`brand_brief/visual_rules/research_pack/campaign_brief/content_plan/kpi_snapshot/...`), scope_ref nullable (mis. campaign_id, period), current_version_id | Satu baris per artefak logis. |
| `artifact_versions` | id, artifact_id, client_id, version int, schema_version, status (`draft/in_review/approved/superseded/rejected`), payload jsonb, token_estimate int, produced_by_llm_call_id nullable, approved_by/at, change_note | Payload divalidasi schema sebelum insert. Versi tidak diubah setelah `approved`; perubahan = versi baru. |
| `content_items` | id, ulid, client_id, content_plan_version_id, offering_id nullable, platform, format, publish_at, status (lihat WORKFLOWS), payload jsonb (schema `content_item`), revision_count, major_revision bool, asset_ids | Satu baris per item; revisi parsial mengubah item ini saja. |
| `approvals` | id, ulid, client_id, subject_type/id, channel (`telegram/dashboard`), decision (`approved/revise/rejected`), comment, reason_tags json, decided_by, decided_at | `reason_tags` diringkas ke aturan do/don't (F9.4). |

### Aset

| Tabel | Kolom penting | Catatan |
| --- | --- | --- |
| `assets` | id, ulid, client_id, offering_id nullable, kind (`photo/video/screenshot/render/ai_image/ai_video/stock`), source (`client/ugc/auto/ai/stock`), path, mime, width, height, duration, tags json (mood, has_people, has_children), consent_status, license, ai_generated bool | F9.3. `has_children` + izin wajib dicek Brand Guardian. |
| `asset_usages` | asset_id, content_item_id, used_at | Riwayat pemakaian. |
| `shot_lists` | client_id, month, items json, status | F9.3 (boleh digeser ke Minggu 7+). |

### AI, model, biaya

| Tabel | Kolom penting | Catatan |
| --- | --- | --- |
| `provider_credentials` | workspace_id, provider (`anthropic/openai/gemini/deepseek/telegram/meta/google_oauth/tiktok`), key, value (enc), last4, status, last_tested_at, last_error_code, updated_by | F9.9. Diisi lewat menu Kredensial (`docs/specs/credentials-settings.md`), bukan `.env`. |
| `model_catalog` | provider, model_id, label, capabilities json (`text/vision/image/video/json_mode/prompt_cache`), price_input_per_mtok, price_output_per_mtok, price_cached_input_per_mtok, price_per_image, price_per_video_second, currency, active | Harga diisi admin. Gateway menghitung biaya dari sini. |
| `role_configs` | workspace_id, role_key, primary_model_id, fallback_model_id, max_cost_per_call, tier | Pemetaan peran → model; default dari `config/roles.php`. |
| `llm_calls` | id, ulid, client_id, role_key, task_ref, prompt_key, prompt_version, provider, model_id, attempt, fallback_from_call_id, input_tokens, cached_input_tokens, output_tokens, cost_usd numeric(12,6), latency_ms, status (`ok/schema_invalid/provider_error/rate_limited/budget_blocked`), error_code, request_hash, created_at | F8.4. Tidak menyimpan prompt penuh; simpan `request_hash` + referensi artefak. |
| `media_generations` | client_id, kind (`image/video`), quality (`draft/final`), provider, model_id, units (jumlah/detik), cost_usd, asset_id, llm_call_id | Biaya media terpisah dari token. |
| `budgets` | client_id, month, llm_limit_usd, media_limit_usd, llm_spent_usd, media_spent_usd, alert_80_sent_at, state (`normal/degraded/blocked`) | Diperbarui dalam transaksi yang sama dengan `llm_calls`. |

### Publish & analitik

| Tabel | Kolom penting | Catatan |
| --- | --- | --- |
| `oauth_connections` | client_id, platform, account_ref, access_token (enc), refresh_token (enc), expires_at, scopes, status | F1.2. |
| `publish_jobs` | id, client_id, content_item_id, platform, scheduled_at, mode (`api/manual_package`), status (`scheduled/qa_failed/publishing/published/failed/missed/manually_uploaded`), external_post_id, package_path, error_code | F4.2, F9.5. |
| `metrics_daily` | client_id, source (`ig/ga4/...`), entity_type (`account/post/page`), entity_ref, date, metric_key, value numeric | Warehouse sederhana (EAV). Unik per (client, source, entity, date, metric). |

### Kalender & ops

| Tabel | Kolom penting | Catatan |
| --- | --- | --- |
| `moment_calendar` | date_start, date_end, key, name, kind (`religious/shopping/national/payday/school/season`), notes | F9.7. Tanggal Ramadan/Lebaran diisi per tahun. |
| `scheduled_tasks` | id, client_id nullable, kind, due_at, payload_ref, status (`pending/dispatched/done/missed/cancelled`), idempotency_key unik | ADR 0002/0003. |
| `job_runs` | id, idempotency_key unik, job_class, client_id nullable, status, attempts, started_at, finished_at, error_code | Konsol job F9.6. |
| `workflow_events` | subject_type/id, client_id, from_status, to_status, actor (`system/admin/client:<id>`), reason, created_at | Jejak semua transisi. |
| `activity_log` | user_id, action, subject, ip, created_at | F9.9. |

## Indeks & constraint penting

- `artifact_versions (artifact_id, version)` unik; partial index `status = 'approved'`.
- `llm_calls (client_id, created_at)` untuk dashboard biaya; `(role_key, prompt_version)` untuk perbandingan versi.
- `budgets (client_id, month)` unik.
- `publish_jobs (content_item_id, platform)` unik selama status aktif (mencegah publish ganda).
- `metrics_daily` unik seperti di atas; upsert idempotent.

## Retensi (UU PDP)

- Data pribadi pelanggan klien tidak disimpan di MVP (tidak ada inbox DM/CAPI). Saat Fase 2: hash sebelum kirim, retensi 13 bulan.
- `llm_calls` disimpan 18 bulan; payload prompt tidak disimpan.
