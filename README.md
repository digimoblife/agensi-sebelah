# Agensi Sebelah

Agensi digital marketing berbasis AI yang hemat token: workflow deterministik, peran AI yang dipanggil saat dibutuhkan,
artefak JSON berversi, dan manusia sebagai gerbang persetujuan. Lihat [`docs/PRD.md`](docs/PRD.md).

## Menyalakan & mematikan

```bash
bin/agensi start     # nyalakan semua service, lalu buka dashboard di browser
bin/agensi stop      # matikan (data tetap aman)
bin/agensi status    # lihat service yang berjalan
bin/agensi restart
bin/agensi logs      # log worker, scheduler, bot Telegram (Ctrl+C keluar)
```

## Menjalankan lokal (pertama kali)

Butuh Docker Desktop. PHP dan Composer tidak perlu dipasang di host.

```bash
cp .env.example .env    # isi DB_PASSWORD, REDIS_PASSWORD, HOST_UID, HOST_GID
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan make:filament-user
```

Dashboard: http://localhost:8088/admin · Horizon: http://localhost:8088/horizon

## Pilot

```bash
docker compose exec app php artisan pilot:check
docker compose exec app php artisan demo:seed --with-artifacts   # klien demo fiktif untuk uji kering
```

Lihat [checklist pilot](docs/PILOT-CHECKLIST.md), [runbook publish](docs/RUNBOOK-PUBLISH.md), [runbook operasional](docs/RUNBOOK-OPS.md).

## Test & kualitas

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint
docker compose exec app ./vendor/bin/phpstan analyse
```

## Dokumen

[Arsitektur](docs/ARCHITECTURE.md) · [Data model](docs/DATA-MODEL.md) · [LLM Gateway](docs/LLM-GATEWAY.md) ·
[Artefak](docs/ARTIFACTS.md) · [ADR](docs/adr/) · [Panduan kontributor AI](CLAUDE.md)
