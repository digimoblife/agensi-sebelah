<?php

namespace App\Services\Ops;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * F9.9 daily database backup: pg_dump (custom format) encrypted with AES-256-GCM using a key derived from
 * APP_KEY. Without APP_KEY a backup cannot be read, so APP_KEY must live in a password manager too.
 * Uploaded files (storage/app/private/assets) are backed up with the Mac's own backup (Time Machine).
 */
class BackupService
{
    private const MAGIC = "AGSB1\n";

    public function run(): string
    {
        $dir = $this->dir();
        $plain = tempnam(sys_get_temp_dir(), 'dump');
        if ($plain === false) {
            throw new RuntimeException('No temp file.');
        }

        try {
            $result = Process::timeout(600)->env($this->pgEnv())->run(['pg_dump', '--format=custom', '--no-owner', '--file', $plain]);
            if (! $result->successful() || filesize($plain) === 0) {
                throw new RuntimeException('pg_dump failed (exit '.$result->exitCode().').');
            }

            $target = $dir.'/agensi-'.now('Asia/Jakarta')->format('Ymd-His').'.dump.enc';
            File::put($target, $this->encrypt((string) file_get_contents($plain)));
            @chmod($target, 0600);
        } finally {
            @unlink($plain);
        }

        $this->prune();

        return $target;
    }

    /** Decrypts the newest (or a given) backup and checks pg_restore can read its table of contents. */
    public function verify(?string $file = null): int
    {
        $file ??= $this->latest() ?? throw new RuntimeException('No backup found.');
        $plain = $this->decryptToTemp($file);

        try {
            $result = Process::timeout(120)->run(['pg_restore', '--list', $plain]);
            if (! $result->successful()) {
                throw new RuntimeException('Backup is not a readable pg_dump archive.');
            }

            return substr_count($result->output(), ' TABLE DATA ');
        } finally {
            @unlink($plain);
        }
    }

    /** For a manual restore (docs/RUNBOOK-OPS.md): writes the plain dump to $target. */
    public function decrypt(string $file, string $target): void
    {
        File::put($target, $this->decryptRaw((string) File::get($file)));
    }

    public function latest(): ?string
    {
        $files = glob($this->dir().'/agensi-*.dump.enc') ?: [];
        sort($files);

        return end($files) ?: null;
    }

    private function decryptToTemp(string $file): string
    {
        $plain = tempnam(sys_get_temp_dir(), 'restore');
        if ($plain === false) {
            throw new RuntimeException('No temp file.');
        }
        File::put($plain, $this->decryptRaw((string) File::get($file)));

        return $plain;
    }

    private function encrypt(string $data): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($data, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return self::MAGIC.$iv.$tag.$cipher;
    }

    private function decryptRaw(string $blob): string
    {
        if (! str_starts_with($blob, self::MAGIC)) {
            throw new RuntimeException('Not an Agensi Sebelah backup.');
        }
        $body = substr($blob, strlen(self::MAGIC));
        $plain = openssl_decrypt(substr($body, 28), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, substr($body, 0, 12), substr($body, 12, 16));
        if ($plain === false) {
            throw new RuntimeException('Cannot decrypt backup (wrong APP_KEY or damaged file).');
        }

        return $plain;
    }

    private function prune(): void
    {
        $cutoff = now()->subDays((int) config('services.backup.keep_days'))->timestamp;
        foreach (glob($this->dir().'/agensi-*.dump.enc') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    private function key(): string
    {
        $appKey = (string) config('app.key');
        $raw = str_starts_with($appKey, 'base64:') ? (string) base64_decode(substr($appKey, 7)) : $appKey;

        return hash_hmac('sha256', 'agensi-backup-v1', $raw, true);
    }

    /** @return array<string, string> */
    private function pgEnv(): array
    {
        $db = (array) config('database.connections.'.config('database.default'));

        return [
            'PGHOST' => (string) $db['host'], 'PGPORT' => (string) $db['port'], 'PGDATABASE' => (string) $db['database'],
            'PGUSER' => (string) $db['username'], 'PGPASSWORD' => (string) $db['password'],
        ];
    }

    private function dir(): string
    {
        $dir = (string) config('services.backup.dir');
        File::ensureDirectoryExists($dir, 0700);

        return $dir;
    }
}
