# LLM Gateway

Status: draf fondasi · 19 Nov 2026 · Rujukan: PRD §Prinsip hemat token, §Penyedia model AI, F8.2–F8.4, NFR

Gateway adalah satu-satunya jalan ke penyedia AI dan inti penghematan. Peran tidak tahu penyedia mana yang dipakai.

## Interface

```php
// app/Services/Llm/LlmGateway.php
public function run(RoleRequest $request): RoleResult;

final class RoleRequest {
    public int $clientId;
    public string $roleKey;          // 'copywriter'
    public string $taskRef;          // 'content_plan@4:batch-1' (untuk log & idempotency)
    public array $artifactRefs;      // ['brand_brief' => versionId, 'content_plan' => versionId]
    public array $knowledgeItemIds;  // fakta yang boleh dipakai
    public array $taskInput;         // input kecil khusus tugas (mis. daftar plan item keys)
    public ?string $forceModel = null; // mode bandingkan
}

final class RoleResult {
    public array $payload;           // sudah lolos JSON Schema
    public int $llmCallId;
    public float $costUsd;
    public string $modelId;
    public bool $usedFallback;
}
```

Media (gambar/video) memakai `MediaGateway::generate(MediaRequest)` dengan aturan budget & log yang sama, tercatat di `media_generations`.

## Langkah satu panggilan

1. **Resolve peran** dari `config/roles.php` + `role_configs`: tier, prompt key, versi prompt aktif, output schema, model utama/cadangan, `max_cost_per_call`.
2. **Budget guard** (`BudgetService::check`): estimasi biaya = token input (hitung lokal) × harga + perkiraan output.
   - `state = normal` → lanjut.
   - `degraded` (≥ 80%, alert sudah dikirim) → tugas non-prioritas ditunda; tugas prioritas turun satu tier jika peran mengizinkan (`allow_downgrade`).
   - `blocked` (≥ 100%) → hanya tugas yang ditandai `critical` (mis. revisi yang diminta klien) yang jalan, di tier termurah. Lainnya → `llm_calls.status = budget_blocked`, job ditunda, admin diberi tahu.
   - Estimasi > `max_cost_per_call` → tolak sebelum memanggil.
3. **Susun prompt** (urutan tetap agar prefix cache efektif):
   1. System prompt peran (`resources/prompts/<role>/v<N>.md`), statis.
   2. Brand Brief versi `approved` (+ Visual Rules jika peran membutuhkannya), stabil per klien.
   3. Knowledge items yang dipilih, diurutkan berdasarkan id agar stabil.
   4. Artefak input tugas.
   5. Instruksi tugas + JSON Schema output.
   Bagian 1–3 diberi penanda cache sesuai penyedia (Anthropic `cache_control`; penyedia lain memakai cache otomatis berbasis prefix).
4. **Cek capability**: model harus punya kemampuan yang dibutuhkan peran (`vision`, `json_mode`) menurut `model_catalog`. Model yang tidak cocok dilewati (K9).
5. **Panggil adapter** (`Providers/{Anthropic,OpenAi,Gemini,DeepSeek}Adapter`) dengan structured output / JSON mode bila tersedia.
6. **Validasi** output dengan JSON Schema (`opis/json-schema`), lalu validator semantik peran (mis. setiap `fact_refs` harus termasuk knowledge item yang dikirim, `plan_item_key` harus ada di plan).
   - Gagal → **ulang sekali** dengan pesan error validasi yang ringkas (tanpa mengirim ulang output lengkap bila bisa).
   - Gagal lagi → `status = schema_invalid`, item masuk antrian manual (konsol job).
7. **Fallback**: error penyedia, rate limit (429), atau kuota habis → coba model cadangan sekali. `fallback_from_call_id` diisi. Error validasi tidak memicu fallback model.
8. **Catat** satu baris `llm_calls` per percobaan (token in/out/cached, biaya dari katalog, latency, status) dan update `budgets` dalam transaksi yang sama.
9. **Kembalikan** payload. Penyimpanan artefak dilakukan pemanggil lewat `ArtifactService`, bukan gateway.

## Perhitungan biaya

```
cost = (input_tokens - cached_input_tokens) × price_input / 1e6
     + cached_input_tokens × price_cached_input / 1e6
     + output_tokens × price_output / 1e6
```

Angka token diambil dari respons penyedia (`usage`), bukan estimasi. Harga dari `model_catalog` saat panggilan;
simpan `cost_usd` final di baris supaya perubahan harga tidak mengubah histori.

## Cache hasil (opsional, T0)

`request_hash = sha256(role, prompt_version, model, artefak refs, kb ids, task_input)`. Jika ada `llm_calls`
sukses dengan hash sama dalam 24 jam dan tugas ditandai `cacheable`, payload dipakai ulang tanpa memanggil model.

## Tier default

| Tier | Arti | Contoh peran MVP |
| --- | --- | --- |
| T0 | Tanpa LLM, kode/SQL/rule | Graphic Designer, QA Publisher, Data Analyst, Budget Guard, bagian rule Brand Guardian |
| T1 | Model murah | Brand Guardian (tone/klaim), Onboarding Assistant |
| T2 | DeepSeek | Copywriter, Content Strategist, Researcher (ringkasan), Report Writer |
| T3 | Model premium | Brand Strategist, Art Director (sekali) |

Pemetaan model awal mengikuti tabel PRD dan dapat diubah di dashboard.

## Mode bandingkan

`forceModel` menjalankan tugas yang sama di dua model. Hasil tidak disimpan sebagai artefak aktif; keduanya
ditampilkan berdampingan dengan biaya. Admin memilih default untuk `role_configs`.

## Test

- `FakeLlmProvider` mengembalikan fixture dari `resources/schemas/examples/` atau dari test; dipaksa di `tests/bootstrap.php`.
- Wajib diuji: urutan prompt (snapshot), budget guard di tiga state, fallback, retry validasi sekali, perhitungan biaya dengan cached token, capability filter.
- Arch test: tidak ada kelas di luar `App\Services\Llm\Providers` yang memanggil host penyedia atau memakai SDK-nya.

## Pertanyaan terbuka

- DeepSeek: dukungan vision & structured output perlu dicek saat implementasi adapter.
- Penghitung token lokal per penyedia untuk estimasi: cukup pendekatan `chars / 3.5` di MVP, dikoreksi dari data `usage`.
