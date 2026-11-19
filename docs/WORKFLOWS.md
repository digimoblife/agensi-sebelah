# Workflow

Status: Minggu 3 · 19 Nov 2026 · Rujukan: ADR 0002, PRD §Alur campaign standar

Status hanya berubah lewat kelas workflow (`app/Services/Workflow`). Transisi yang tidak ada di matriks melempar
`InvalidTransition`. Setiap transisi dicatat di `workflow_events` (dari, ke, aktor, alasan). Matriks dikunci test
(`tests/Unit/Workflow/ContentWorkflowTest.php`).

## Content item

```
planned ──draft──► drafting ──► drafted ──Guardian lolos──► awaiting_approval ──setuju──► approved ──► rendered ──► scheduled ──► published
   ▲                  │  ▲         │                              │  │                      │            │             │
   │             gagal│  │         └─Guardian gagal─► guardian_failed  ├─revisi─► revision_requested ─► drafting       └─► publish_failed / packaged ─► manually_uploaded
 cancelled ◄──────────┘  └──────────── (revisi otomatis 1x) ◄──────┘  └─tolak──► rejected ─► drafting
```

| Dari | Boleh ke | Pemicu |
| --- | --- | --- |
| planned | drafting, cancelled | Admin memilih "Buat draft", atau plan baru menghapus item |
| drafting | drafted, planned, revision_requested | Copywriter selesai; gagal → kembali ke status sebelumnya |
| drafted | awaiting_approval, guardian_failed, drafting, cancelled | Hasil Brand Guardian |
| guardian_failed | drafting, awaiting_approval, cancelled | Revisi otomatis 1x, atau admin meloloskan manual |
| awaiting_approval | approved, revision_requested, rejected, cancelled | Keputusan admin/klien (Telegram mulai Minggu 4) |
| revision_requested | drafting, cancelled | Revisi parsial item ini saja (F3.4) |
| approved | rendered, revision_requested, cancelled | Render template (Minggu 4) |
| rejected | drafting, cancelled | Tulis ulang dari nol |
| rendered → scheduled → published | | Minggu 4–5 |
| scheduled | publish_failed, packaged | Gagal publish = alert, tidak diulang diam-diam; terlambat > 30 menit = paket siap upload (ADR 0003) |

## Aturan revisi

- Revisi hanya mengirim item itu + komentar + draf sebelumnya + Brand Brief (prinsip 9).
- Brand Guardian gagal → **satu** revisi otomatis memakai temuan Guardian sebagai komentar. Gagal lagi → `guardian_failed`, menunggu admin.
- **Revisi besar** (K5, gerbang MVP 80 %) = item ditolak, atau diregenerasi lebih dari satu kali atas permintaan manusia, atau komentar revisi ditandai *besar*. Disimpan di `content_items.major_revision`.

## Content Plan

- Content Plan adalah artefak berversi per bulan (`scope_ref = YYYY-MM`).
- Saat sebuah versi disetujui, barisnya dimaterialisasi menjadi `content_items` (kunci: klien + bulan + `plan_item_key`):
  baris baru → `planned`; baris yang berubah dan belum ditulis → diperbarui; baris yang hilang dari plan dan belum
  disetujui → `cancelled`. Item yang sudah disetujui atau lebih lanjut tidak diubah.
