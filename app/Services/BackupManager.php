<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The Super Admin's backups, in one place.
 *
 * Listing, importing what Cloudinary has but the table does not, inducing one
 * now, opening either file, and deleting both files plus the row. The page
 * only decides how to present these operations, so anything else that needs
 * them (a command, an API endpoint) cannot drift about what "delete" means.
 *
 * A backup is two files in Cloudinary plus one row in `backups`, identified by
 * its stamp: a date for the nightly backup, date plus time for an induced one.
 */
class BackupManager
{
    /** The halves of a backup, and the only file names a download may ask for. */
    public const KINDS = ['schema', 'seed'];

    private CloudinaryService $cloudinary;

    private DatabaseBackupService $backups;

    public function __construct(?CloudinaryService $cloudinary = null, ?DatabaseBackupService $backups = null)
    {
        $this->cloudinary = $cloudinary ?? new CloudinaryService();
        $this->backups = $backups ?? new DatabaseBackupService($this->cloudinary);
    }

    /**
     * Pull any backup folder Cloudinary has that the table does not know about.
     *
     * @return array{imported: int, warning: ?string}
     */
    public function import(): array
    {
        $folderRoot = DatabaseBackupService::rootFolder();

        $files = $this->cloudinary->listRaw($folderRoot . '/');

        if ($files === null) {
            return [
                'imported' => 0,
                'warning' => $this->cloudinary->lastError()
                    ?? 'Cloudinary could not be read, so this list may be missing backups.',
            ];
        }

        try {
            $known = [];
            foreach (Supabase::table('backups')->select('folder')->limit(1000)->get() as $row) {
                $known[(string) ($row['folder'] ?? '')] = true;
            }
        } catch (\Throwable $e) {
            // The backups table may not exist yet on this deployment. The page
            // still lists nothing, but says why instead of returning a 500.
            return ['imported' => 0, 'warning' => 'The backups table could not be read: ' . $e->getMessage()];
        }

        $root = preg_quote($folderRoot, '#');

        $patterns = [
            // Current seeds keep their .sql name (gzip bytes inside); the
            // optional .gz still matches folders written by the earlier variant,
            // which are imported as-is and download as plain SQL.
            'automatic' => '#^' . $root . '/backup_(\d{4}-\d{2}-\d{2})/(schema|seed)_(\d{4}-\d{2}-\d{2})\.sql(?:\.gz)?$#',
            'induced' => '#^' . $root . '/induced/backup_(\d{4}-\d{2}-\d{2}_\d{6})/(schema|seed)_(\d{4}-\d{2}-\d{2}_\d{6})\.sql(?:\.gz)?$#',
        ];

        $found = [];

        foreach ($files as $file) {
            foreach ($patterns as $kind => $pattern) {
                if (preg_match($pattern, $file['public_id'], $matches) !== 1) {
                    continue;
                }

                if ($matches[1] !== $matches[3]) {
                    continue; // the file name disagrees with the folder it sits in
                }

                $stamp = $matches[1];

                $found[DatabaseBackupService::folderFor($stamp, $kind === 'induced')] = [
                    'kind' => $kind,
                    'stamp' => $stamp,
                    'date' => substr($stamp, 0, 10),
                    'parts' => array_merge(
                        $found[DatabaseBackupService::folderFor($stamp, $kind === 'induced')]['parts'] ?? [],
                        [$matches[2] => $file]
                    ),
                ];

                break;
            }
        }

        $imported = 0;

        foreach ($found as $folder => $backup) {
            if (isset($known[$folder])) {
                continue;
            }

            try {
                Supabase::table('backups')->insert([
                    'backup_date' => $backup['date'],
                    'kind' => $backup['kind'],
                    'stamp' => $backup['stamp'],
                    'folder' => $folder,
                    'schema_url' => $backup['parts']['schema']['url'] ?? null,
                    'schema_bytes' => $backup['parts']['schema']['bytes'] ?? null,
                    'seed_url' => $backup['parts']['seed']['url'] ?? null,
                    'seed_bytes' => $backup['parts']['seed']['bytes'] ?? null,
                    'tables_count' => 0,
                    'rows_count' => 0,
                    'seconds' => null,
                    'status' => 'imported',
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Backup found in Cloudinary but not recorded', [
                    'folder' => $folder,
                    'message' => $e->getMessage(),
                ]);

                break;
            }

            $imported++;
        }

        return ['imported' => $imported, 'warning' => null];
    }

    /**
     * Every recorded backup, newest first, optionally narrowed to a date range.
     *
     * Ordering is done here on the stamp rather than by the database, so a row
     * written before the kind/stamp columns existed still sorts correctly.
     *
     * @return array{days: list<object>, total_stored: int, total_bytes: int, matching_bytes: int}
     */
    public function list(?string $from = null, ?string $to = null): array
    {
        $rows = [];

        foreach (Supabase::table('backups')->select('*')->limit(500)->get() as $row) {
            $rows[] = $row->toArray();
        }

        $days = [];
        $totalBytes = 0;
        $matchingBytes = 0;

        foreach ($rows as $row) {
            $folder = (string) ($row['folder'] ?? '');
            $kind = (string) ($row['kind'] ?? $this->kindFromFolder($folder));
            $date = substr((string) ($row['backup_date'] ?? ''), 0, 10);
            $stamp = (string) ($row['stamp'] ?? ($kind === 'induced' ? $this->stampFromFolder($folder) : $date));

            if ($date === '' || $stamp === '') {
                continue;
            }

            $schemaBytes = (int) ($row['schema_bytes'] ?? 0);
            $seedBytes = (int) ($row['seed_bytes'] ?? 0);
            $totalBytes += $schemaBytes + $seedBytes;

            if (($from !== null && $date < $from) || ($to !== null && $date > $to)) {
                continue;
            }

            $matchingBytes += $schemaBytes + $seedBytes;

            $days[] = (object) [
                'date' => $date,
                'stamp' => $stamp,
                'kind' => $kind,
                'time' => $kind === 'induced' ? $this->timeFromStamp($stamp) : null,
                'label' => $this->label($stamp),
                'folder' => $folder,
                'schema_url' => (string) ($row['schema_url'] ?? '') ?: $this->publicUrl($stamp, 'schema', $kind === 'induced'),
                'seed_url' => (string) ($row['seed_url'] ?? '') ?: $this->publicUrl($stamp, 'seed', $kind === 'induced'),
                'schema_bytes' => $schemaBytes,
                'seed_bytes' => $seedBytes,
                'rows_count' => (int) ($row['rows_count'] ?? 0),
                'tables_count' => (int) ($row['tables_count'] ?? 0),
                'seconds' => ($row['seconds'] ?? null) === null ? null : (float) $row['seconds'],
                'status' => (string) ($row['status'] ?? 'complete'),
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        usort($days, fn ($a, $b) => strcmp($b->stamp, $a->stamp));

        return [
            'days' => $days,
            'total_stored' => count($rows),
            'total_bytes' => $totalBytes,
            'matching_bytes' => $matchingBytes,
        ];
    }

    /**
     * Find one backup by stamp, trying the automatic and induced folders.
     *
     * The folder is the lookup key because it exists in every version of the
     * table, so a request that arrives before a migration still answers.
     */
    public function find(string $stamp): ?array
    {
        foreach ([false, true] as $induced) {
            $row = Supabase::table('backups')
                ->where('folder', DatabaseBackupService::folderFor($stamp, $induced))
                ->first();

            if ($row !== null) {
                return $row->toArray();
            }
        }

        return null;
    }

    /**
     * Take an induced backup now, on demand.
     *
     * A cache lock keeps two taps (or one landing exactly on midnight) from
     * writing the same files at once.
     *
     * @return array{ok: bool, message: string, code?: string, result?: array}
     */
    public function induce(): array
    {
        $lock = Cache::lock('database-backup', 600);

        if (! $lock->get()) {
            return [
                'ok' => false,
                'code' => 'busy',
                'message' => 'A backup is already running. Give it a moment and refresh the page.',
            ];
        }

        try {
            $result = $this->backups->runInduced();
        } catch (\Throwable $e) {
            Log::error('Induced backup failed', ['message' => $e->getMessage()]);

            return [
                'ok' => false,
                'code' => 'failed',
                'message' => 'The backup failed: ' . $e->getMessage(),
            ];
        } finally {
            $lock->release();
        }

        return [
            'ok' => true,
            'result' => $result,
            'message' => "Induced backup for {$result['date']} at " . $this->timeFromStamp($result['stamp']) . ' created — '
                . number_format($result['rows']) . ' rows from ' . count($result['tables']) . ' tables, filed in Cloudinary.',
        ];
    }

    /**
     * Delete one backup: both files, then the row.
     *
     * Nothing is removed from the table until Cloudinary confirms both files,
     * so a half-deleted backup can never look complete in the list.
     *
     * @return array{ok: bool, message: string, code?: string}
     */
    public function delete(string $stamp): array
    {
        $row = $this->find($stamp);

        if ($row === null) {
            return [
                'ok' => false,
                'code' => 'not_found',
                'message' => "There is no backup recorded for {$this->label($stamp)}.",
            ];
        }

        $induced = $this->isInduced($row, $stamp);
        $failed = [];

        foreach (self::KINDS as $kind) {
            if (! $this->cloudinary->deleteRaw(DatabaseBackupService::publicIdFor($stamp, $kind, $induced))) {
                $failed[$kind] = $this->cloudinary->lastError() ?? 'no reason given';
            }
        }

        if ($failed !== []) {
            Log::error('Backup delete refused by Cloudinary', ['stamp' => $stamp, 'failed' => $failed]);

            $details = collect($failed)->map(fn ($reason, $kind) => "{$kind}: {$reason}")->implode('; ');

            return [
                'ok' => false,
                'code' => 'cloudinary',
                'message' => "Could not delete the backup for {$this->label($stamp)} — {$details}. It is still listed.",
            ];
        }

        $folder = (string) ($row['folder'] ?? DatabaseBackupService::folderFor($stamp, $induced));

        try {
            Supabase::table('backups')->where('folder', $folder)->delete();
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'code' => 'database',
                'message' => "The files for {$this->label($stamp)} were deleted from Cloudinary, but the database row could not be removed: "
                    . $e->getMessage(),
            ];
        }

        Log::info('Backup deleted', ['stamp' => $stamp, 'kind' => $induced ? 'induced' : 'automatic']);

        return [
            'ok' => true,
            'message' => "The backup for {$this->label($stamp)} has been deleted from Cloudinary and the database.",
        ];
    }

    /** The URL one of a backup's files can be opened or fetched from. */
    public function fileUrl(array $row, string $stamp, string $file): string
    {
        $stored = (string) ($row[$file . '_url'] ?? '');

        if ($stored !== '') {
            return $stored;
        }

        return $this->publicUrl($stamp, $file, $this->isInduced($row, $stamp));
    }

    public function isInduced(array $row, string $stamp): bool
    {
        $kind = (string) ($row['kind'] ?? '');

        if ($kind !== '') {
            return $kind === 'induced';
        }

        return str_contains((string) ($row['folder'] ?? ''), '/induced/') || str_contains($stamp, '_');
    }

    /** "2026-11-05_143501" -> "14:35:01"; a plain date has no time. */
    public function timeFromStamp(string $stamp): ?string
    {
        if (preg_match('/_(\d{2})(\d{2})(\d{2})$/', $stamp, $m) !== 1) {
            return null;
        }

        return "{$m[1]}:{$m[2]}:{$m[3]}";
    }

    /** "2026-11-05" or "2026-11-05 at 14:35:01", for messages. */
    public function label(string $stamp): string
    {
        $date = substr($stamp, 0, 10);
        $time = $this->timeFromStamp($stamp);

        return $time !== null ? "{$date} at {$time}" : $date;
    }

    /** A date query parameter, or null when it is not a real YYYY-MM-DD. */
    public static function dateOrNull(mixed $value): ?string
    {
        $value = (string) $value;

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
    }

    private function kindFromFolder(string $folder): string
    {
        return str_contains($folder, '/induced/') ? 'induced' : 'automatic';
    }

    private function stampFromFolder(string $folder): string
    {
        $name = basename($folder);

        return str_starts_with($name, 'backup_') ? substr($name, 7) : $name;
    }

    private function publicUrl(string $stamp, string $file, bool $induced): string
    {
        $cloud = (string) config('cloudinary.cloud_name');

        return "https://res.cloudinary.com/{$cloud}/raw/upload/" . DatabaseBackupService::publicIdFor($stamp, $file, $induced);
    }
}
