# Artefak

Status: draf fondasi · 19 Nov 2026 · Rujukan: PRD §Artefak dan alur kerja

Artefak adalah satu-satunya bahasa antar-peran. Skema di `resources/schemas/artifacts/`, contoh fiktif di
`resources/schemas/examples/` (dipakai juga sebagai fixture `FakeLlmProvider`).

## Skema MVP

| Artefak | File | Dibuat oleh | Target ukuran | Disimpan sebagai |
| --- | --- | --- | --- | --- |
| Brand Brief | `brand_brief.v1.schema.json` | Brand Strategist (T3) | ≤ 1.500 token | `artifact_versions` |
| Visual Rules | `visual_rules.v1.schema.json` | Art Director (T3 sekali) | ≤ 800 token | `artifact_versions` |
| Research Pack | `research_pack.v1.schema.json` | Researcher (T0 + T2) | ≤ 2.000 token | `artifact_versions` |
| Content Plan | `content_plan.v1.schema.json` | Content Strategist (T2) | 1 baris per item | `artifact_versions` |
| Content Item | `content_item.v1.schema.json` | Copywriter (T2) | per item | `content_items.payload` |
| Content Batch | `content_batch.v1.schema.json` | Copywriter (output LLM) | ≤ 30 item | tidak disimpan; dipecah ke `content_items` |
| KPI Snapshot | `kpi_snapshot.v1.schema.json` | Data Analyst (T0) | ≤ 30 baris | `artifact_versions` |

Fase 2/3: Campaign Brief, Audit Report, Tracking Plan, Experiment.

## Aturan

1. **`meta` diisi backend.** LLM tidak dipercaya untuk `client_id`, versi, atau `source_refs`; gateway menimpanya sebelum validasi.
2. **Fakta wajib berasal dari knowledge base.** Field `kb_ref` / `fact_refs` harus menunjuk knowledge item yang dikirim ke panggilan itu (validator semantik).
3. **Angka tidak dari LLM.** `kpi_snapshot` dan `research_pack.keywords[].volume` diisi kode; LLM yang menulis angka di luar itu dianggap tidak valid.
4. **`additionalProperties: false`** di semua objek supaya output tidak melebar dan boros token.
5. **Versi artefak** (`artifact_versions.version`) naik setiap perubahan isi. Versi `approved` tidak pernah diubah.
6. **Versi skema** (`v1` di nama file) naik hanya untuk perubahan yang tidak kompatibel (menghapus/mengganti field wajib, mengetatkan enum). Menambah field opsional boleh di versi yang sama. Schema lama tetap disimpan selama masih ada data yang memakainya.
7. **Revisi parsial**: revisi Content Item mengirim hanya item itu + komentar + Brand Brief, bukan seluruh batch.
8. **Belajar dari revisi (F9.4)**: `rules.do/avoid` di Brand Brief diperbarui dari ringkasan `approvals.reason_tags`, membentuk versi Brand Brief baru yang perlu disetujui admin.

## Validasi lokal

```
npx --yes ajv-cli@5 validate --spec=draft2020 -c ajv-formats \
  -r resources/schemas/artifacts/_common.v1.schema.json \
  -r resources/schemas/artifacts/content_item.v1.schema.json \
  -s resources/schemas/artifacts/brand_brief.v1.schema.json \
  -d resources/schemas/examples/brand_brief.v1.json
```

Di aplikasi: `opis/json-schema` dengan resolver yang memetakan `$id` `https://agensi-sebelah.local/schemas/` ke folder ini.
