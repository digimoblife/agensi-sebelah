# Keputusan Desain (Decision Log)

Keputusan kecil yang diambil saat implementasi beserta alasannya. Keputusan besar ditulis sebagai ADR di `docs/adr/`.
Setiap entri menyebut ID fitur PRD atau kode celah (K1–K10) terkait.

---

## Fondasi (19 Nov 2026)

- **Budget dalam USD, bukan token** (ANALISIS §1 #4). Token tetap dicatat di `llm_calls`; kuota dan alert memakai `cost_usd`.
- **Definisi "revisi besar"** (K5): item ditolak, atau diregenerasi lebih dari satu kali, atau komentar revisi ditandai `major`. Disimpan di `content_items.major_revision`.
- **`meta` artefak diisi backend** dan ditimpa gateway sebelum validasi; LLM tidak dipercaya untuk `client_id`/versi.
- **Pola hashtag** di `content_item` memakai `\p{L}` (Unicode). Validator harus mendukung regex Unicode (Ajv default, PCRE `u` di opis).
