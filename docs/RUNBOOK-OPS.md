# Runbook — Operasional harian

Status: Minggu 6 · 19 Nov 2026

## Menyalakan / mematikan

```
bin/agensi start            # cara singkat: nyalakan Docker bila perlu, semua service, buka dashboard
bin/agensi stop             # matikan (data tetap aman)
docker compose up -d        # semua service (app, worker, scheduler, telegram-poller, postgres, redis, gotenberg, nginx)
docker compose ps           # semua "Up"
docker compose exec app php artisan pilot:check
```

Mac boleh sleep. Saat bangun, scheduler mengejar: jadwal terbit yang terlewat menjadi paket + pengingat, data metrik
yang terlewat ditarik, laporan minggu lalu yang belum ada dibuat (ADR 0003). Jangan pernah `docker compose down -v`
(menghapus volume database).

## Backup (F9.9)

- Otomatis setiap 02:00 WIB: `pg_dump` terenkripsi AES-256-GCM (kunci diturunkan dari `APP_KEY`) ke `BACKUP_HOST_DIR`
  (default `./backups`), disimpan 14 hari, lalu diverifikasi bisa dibaca. Gagal = pesan Telegram ke admin.
- Manual: `docker compose exec app php artisan backup:run --verify`.
- **Simpan `APP_KEY` (isi `.env`) di password manager.** Tanpa itu backup tidak bisa dibuka, dan API key/token di database juga tidak bisa didekripsi.
- File upload (`storage/app/private/assets`) ikut backup Mac (Time Machine); tidak termasuk dump database.

### Restore (uji tiap bulan di database terpisah)

```
docker compose exec app php artisan backup:decrypt --out=/tmp/restore.dump          # file terbaru
docker compose exec postgres createdb -U agensi agensi_restore_check
docker compose cp app:/tmp/restore.dump /tmp/restore.dump && docker compose cp /tmp/restore.dump postgres:/tmp/restore.dump
docker compose exec postgres pg_restore -U agensi -d agensi_restore_check --no-owner /tmp/restore.dump
docker compose exec postgres dropdb -U agensi agensi_restore_check                    # setelah dicek
```

Restore ke database utama hanya saat insiden, setelah menghentikan `worker`, `scheduler`, dan `telegram-poller`.

## Bila ada masalah

| Gejala | Cek | Tindakan |
| --- | --- | --- |
| Draf tidak muncul | Konsol job, Log panggilan AI | Status `budget_blocked` → naikkan budget klien; `NoRoutableModel` → Model per peran/Kredensial |
| Klien tidak menerima pesan bot | pilot:check "Poller Telegram", Kontak Telegram "Terhubung" | `docker compose restart telegram-poller`; kirim ulang via *Render & kirim ke klien* |
| Posting gagal | Jadwal terbit (kode error), pesan Telegram | *Coba terbit lagi* atau *Jadikan paket manual* |
| Laporan tidak terkirim | Laporan, Konsol job, Koneksi akun GA4 | *Kirim ulang* / *Buat laporan minggu lalu* |
| Biaya naik tiba-tiba | Log panggilan AI (filter peran, status) | Turunkan tier peran di Model per peran; cek revisi otomatis berulang |
