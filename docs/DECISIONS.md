# Keputusan Desain (Decision Log)

Keputusan kecil yang diambil saat implementasi beserta alasannya. Keputusan besar ditulis sebagai ADR di `docs/adr/`.
Setiap entri menyebut ID fitur PRD atau kode celah (K1–K10) terkait.

---

## Fondasi (19 Nov 2026)

- **Budget dalam USD, bukan token** (ANALISIS §1 #4). Token tetap dicatat di `llm_calls`; kuota dan alert memakai `cost_usd`.
- **Definisi "revisi besar"** (K5): item ditolak, atau diregenerasi lebih dari satu kali, atau komentar revisi ditandai `major`. Disimpan di `content_items.major_revision`.
- **`meta` artefak diisi backend** dan ditimpa gateway sebelum validasi; LLM tidak dipercaya untuk `client_id`/versi.
- **Pola hashtag** di `content_item` memakai `\p{L}` (Unicode). Validator harus mendukung regex Unicode (Ajv default, PCRE `u` di opis).

## Minggu 1 — LLM Gateway & Kredensial (19 Nov 2026)

- **Claude lewat SDK resmi `anthropic-ai/sdk`**, penyedia lain (OpenAI, Gemini, DeepSeek) lewat HTTP client Laravel. Retry SDK dimatikan (`maxRetries: 0`); retry dan fallback dikerjakan gateway agar setiap percobaan tercatat di `llm_calls` dan dihitung budget.
- **Fallback server Claude** (`server-side-fallback-2026-07-01`, `fallbacks: "default"`) aktif untuk model yang di katalog punya kemampuan `server_fallback` (Opus 5.5, Sonnet 5.5). Penolakan yang tetap terjadi → `refused` → gateway mencoba model cadangan.
- **Schema versi penyedia** (`SchemaRepository::providerSchema`): `$ref` di-inline, `meta` dibuang, kata kunci yang tidak didukung structured output (`maxLength`, `pattern`, `minimum`, `minItems`, dll.) dihapus. Schema lengkap tetap divalidasi lokal dengan opis. OpenAI memakai `strict: false` karena mode strict mewajibkan semua properti `required`. DeepSeek memakai JSON mode dan schema ditaruh di prompt.
- **Arti kolom token di `llm_calls`**: `input_tokens` = input yang tidak ter-cache, `cached_input_tokens` = baca cache, `cache_write_tokens` = tulis cache (Anthropic), `output_tokens` termasuk token thinking (Gemini).
- **Validasi semantik umum**: setiap string `kb:<id>` di output harus ada di daftar fakta yang dikirim. Gagal = dianggap output tidak valid (ikut aturan ulang sekali → antrian manual).
- **Error `bad_request` tidak memicu fallback** (permintaan kita yang salah, bukan penyedianya). Error lain (auth, rate limit, kuota, server, timeout, refusal) pindah ke model cadangan.
- **Budget**: tugas `normal` ditunda saat ≥ 80 % atau saat estimasi melebihi sisa; `priority` jalan dan turun ke model termurah bila peran mengizinkan; `critical` selalu jalan di model termurah. Event `BudgetThresholdReached` (80/100) dikirim sekali per bulan; listener Telegram menyusul di Minggu 4.
- **MFA wajib** untuk admin (Filament app authentication + recovery codes). Setelah login pertama, admin diminta memasang aplikasi authenticator.
- **Katalog model**: hanya model Claude yang di-seed dengan harga (sumber: daftar harga Anthropic, Nov 2026). Model OpenAI, Gemini, dan DeepSeek ditambah admin di dashboard beserta harganya; model tanpa harga tidak pernah dipakai gateway.
- **Workspace**: helper/seed memakai "workspace pertama", bukan `firstOrCreate(['id' => 1])` (kolom `id` tidak fillable sehingga cara itu membuat workspace baru setiap kali).
