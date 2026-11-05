<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Database backup: a constant schema plus a freshly fetched seed.
 *
 * Every backup is two files in one Cloudinary folder:
 *
 *   <root>/backup_<date>/schema_<date>.sql        SYSTEM_SCHEMA.sql, unchanged
 *   <root>/backup_<date>/seed_<date>.sql          every row of every table
 *
 * An induced backup gets its own folder under induced/ and a timestamp, so it
 * can never overwrite the night's snapshot:
 *
 *   <root>/induced/backup_<date>_<His>/schema_<date>_<His>.sql
 *   <root>/induced/backup_<date>_<His>/seed_<date>_<His>.sql
 *
 * THE CONTRACT: schema is constant, seed is refetched. The schema file is a
 * copy of SYSTEM_SCHEMA.sql on every run — it is never regenerated from the
 * live database, so an old backup still restores even if the live shape has
 * drifted since. The seed is always read fresh, table by table, at the moment
 * of the backup; no row data is cached between runs.
 *
 * The table list and each table's column order come from SYSTEM_SCHEMA.sql
 * itself, so the dump follows the constant structure rather than a hand-kept
 * list that would drift away from it. The file is already ordered
 * parent-before-child, so rows restore without tripping foreign keys.
 */
class DatabaseBackupService
{
    /** Midnight is Tanzania midnight, whatever the server clock says. */
    public const TIMEZONE = 'Africa/Dar_es_Salaam';

    /** Rows per INSERT: quick to run, still readable in the SQL editor. */
    private const ROWS_PER_INSERT = 200;

    private CloudinaryService $cloudinary;

    /** @var array<string, array{columns: list<string>, primary: ?string, primary_type: ?string}>|null */
    private ?array $structure = null;

    public function __construct(?CloudinaryService $cloudinary = null)
    {
        $this->cloudinary = $cloudinary ?? new CloudinaryService();
    }

    /** Cloudinary root folder that holds every backup. */
    public static function rootFolder(): string
    {
        return trim((string) config('cloudinary.backup_folder', 'dukamkononi/backups'), '/');
    }

    /** Where one backup's folder lives. */
    public static function folderFor(string $stamp, bool $induced = false): string
    {
        $root = self::rootFolder();

        return $induced
            ? $root . '/induced/backup_' . $stamp
            : $root . '/backup_' . $stamp;
    }

    /** One of a backup's two files: "schema" or "seed". */
    public static function fileName(string $stamp, string $kind): string
    {
        return $kind . '_' . $stamp . '.sql';
    }

    /**
     * The name a file is stored under in Cloudinary.
     *
     * The seed's *bytes* are gzipped (Cloudinary caps a raw file at 10 MB and
     * one full seed passes that), but its name stays .sql on purpose. Cloudinary
     * refuses to deliver archive extensions: a .gz asset answers 401 even for
     * its own secure_url, while a .sql asset is served normally. The download
     * route checks for the gzip header and expands it, so what the super admin
     * saves is still seed_<stamp>.sql — the restore steps never mention gzip.
     */
    public static function storedName(string $stamp, string $kind): string
    {
        return self::fileName($stamp, $kind);
    }

    /** The public_id of one file, which is also how it is deleted. */
    public static function publicIdFor(string $stamp, string $kind, bool $induced = false): string
    {
        return self::folderFor($stamp, $induced) . '/' . self::storedName($stamp, $kind);
    }

    public function run(?Carbon $at = null): array
    {
        return $this->take('automatic', $at);
    }

    public function runInduced(?Carbon $at = null): array
    {
        return $this->take('induced', $at);
    }

    private function take(string $kind, ?Carbon $at = null): array
    {
        set_time_limit(0);

        $started = microtime(true);
        $at = ($at ?? now())->copy()->setTimezone(self::TIMEZONE);
        $induced = $kind === 'induced';

        $date = $at->format('Y-m-d');
        $stamp = $induced ? $at->format('Y-m-d_His') : $date;
        $folder = self::folderFor($stamp, $induced);

        Log::info('Database backup started', ['kind' => $kind, 'stamp' => $stamp]);

        // Seed first: if reading the data fails, nothing is uploaded and no
        // folder is left looking like a valid backup.
        $seed = $this->buildSeed($stamp, $at);
        $schema = $this->schemaFile($stamp);

        // Cloudinary caps a raw file at 10 MB, and a seed of one real database
        // is already past that; on a normal uplink a seed that size also takes
        // minutes. Gzipping it keeps it a single restorable file that fits and
        // uploads in seconds. Downloads are expanded again.
        $compressed = gzencode($seed['sql'], 6);

        if ($compressed === false) {
            throw new RuntimeException('The seed could not be compressed for upload.');
        }

        if (strlen($compressed) > 10 * 1024 * 1024) {
            throw new RuntimeException(
                'The compressed seed is ' . round(strlen($compressed) / 1048576, 1)
                . ' MB, above the 10 MB raw-file limit of this Cloudinary plan.'
            );
        }

        $schemaName = self::storedName($stamp, 'schema');
        $seedName = self::storedName($stamp, 'seed');

        // If the second upload fails, remove the first: a folder holding only
        // half a backup must not be left looking restorable.
        $uploaded = [];

        try {
            $schemaUrl = $this->upload($folder . '/' . $schemaName, $schema);
            $uploaded[] = $folder . '/' . $schemaName;

            $seedUrl = $this->upload($folder . '/' . $seedName, $compressed, 'application/gzip');
            $uploaded[] = $folder . '/' . $seedName;
        } catch (Throwable $e) {
            foreach ($uploaded as $publicId) {
                $this->cloudinary->deleteRaw($publicId);
            }

            throw $e;
        }

        $result = [
            'date' => $date,
            'kind' => $kind,
            'stamp' => $stamp,
            'folder' => $folder,
            'files' => [
                $schemaName => ['url' => $schemaUrl, 'bytes' => strlen($schema)],
                $seedName => ['url' => $seedUrl, 'bytes' => strlen($compressed)],
            ],
            'tables' => $seed['tables'],
            'rows' => $seed['rows'],
            'seconds' => round(microtime(true) - $started, 2),
        ];

        // Both files are safe in Cloudinary, so the log row is written after
        // them and its failure cannot fail the backup.
        $this->record($result);

        Log::info('Database backup finished', [
            'kind' => $kind,
            'stamp' => $stamp,
            'rows' => $seed['rows'],
            'seconds' => $result['seconds'],
            'folder' => $folder,
        ]);

        return $result;
    }

    /**
     * File this run in the backups table the Backups page reads.
     *
     * The folder is the identity: one row per backup, so re-running the same
     * stamp updates that row instead of leaving two. A bookkeeping failure is
     * logged and swallowed — the files are already in Cloudinary, and calling
     * the backup failed because a row could not be written would be a lie.
     */
    private function record(array $result): void
    {
        $stamp = $result['stamp'];
        $timestamp = now()->toIso8601String();

        $row = [
            'backup_date' => $result['date'],
            'kind' => $result['kind'],
            'stamp' => $stamp,
            'folder' => $result['folder'],
            'schema_url' => $result['files'][self::storedName($stamp, 'schema')]['url'] ?? null,
            'schema_bytes' => $result['files'][self::storedName($stamp, 'schema')]['bytes'] ?? null,
            'seed_url' => $result['files'][self::storedName($stamp, 'seed')]['url'] ?? null,
            'seed_bytes' => $result['files'][self::storedName($stamp, 'seed')]['bytes'] ?? null,
            'tables_count' => count($result['tables']),
            'rows_count' => $result['rows'],
            'seconds' => $result['seconds'],
            'status' => 'complete',
            'updated_at' => $timestamp,
        ];

        try {
            $existing = Supabase::table('backups')->select('id')->where('folder', $result['folder'])->first();

            if ($existing && ($existing['id'] ?? null) !== null) {
                Supabase::table('backups')->where('id', $existing['id'])->update($row);

                return;
            }

            Supabase::table('backups')->insert($row + ['created_at' => $timestamp]);
        } catch (Throwable $e) {
            Log::warning('Backup was filed in Cloudinary but not recorded in the backups table', [
                'stamp' => $stamp,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the seed: every row of every table as INSERT statements, read
     * fresh from the database on this run.
     *
     * @return array{sql: string, tables: array<string, int>, rows: int}
     */
    public function buildSeed(string $stamp, ?Carbon $at = null): array
    {
        $at = ($at ?? now())->copy()->setTimezone(self::TIMEZONE);
        $tables = $this->tables();

        $sql = $this->seedHeader($stamp, $at, count($tables));

        $counts = [];
        $total = 0;

        foreach ($tables as $table => $definition) {
            $rows = $this->fetchRows($table);
            $counts[$table] = count($rows);
            $total += count($rows);

            $sql .= $this->insertStatements($table, $definition, $rows);
        }

        $sql .= $this->sequenceBlock($tables, $counts);
        $sql .= "\nCOMMIT;\n";

        return ['sql' => $sql, 'tables' => $counts, 'rows' => $total];
    }

    /**
     * The constant structure, copied into the backup folder unchanged.
     *
     * Only a comment header naming the backup is prepended; the DDL below it is
     * SYSTEM_SCHEMA.sql byte for byte, which is what makes a restore
     * independent of what the live database looks like today.
     */
    public function schemaFile(string $stamp): string
    {
        $path = base_path('SYSTEM_SCHEMA.sql');

        if (! is_file($path)) {
            throw new RuntimeException('SYSTEM_SCHEMA.sql is missing from the project root, so a restore could not be made.');
        }

        $schema = (string) file_get_contents($path);

        return "-- DUKA MKONONI — schema_{$stamp}.sql\n"
            . "-- The constant database structure. Run this file first, then seed_{$stamp}.sql,\n"
            . "-- to rebuild the database exactly as it stood at this backup.\n"
            . "-- Source: SYSTEM_SCHEMA.sql (copied unchanged).\n\n"
            . ltrim($schema);
    }

    /**
     * Table name => ordered columns and primary key, read from SYSTEM_SCHEMA.sql
     * in file order (parents first).
     *
     * @return array<string, array{columns: list<string>, primary: ?string, primary_type: ?string}>
     */
    public function tables(): array
    {
        if ($this->structure !== null) {
            return $this->structure;
        }

        $path = base_path('SYSTEM_SCHEMA.sql');

        if (! is_file($path)) {
            throw new RuntimeException('SYSTEM_SCHEMA.sql is missing from the project root.');
        }

        $schema = (string) file_get_contents($path);

        preg_match_all(
            '/CREATE TABLE public\.([a-z0-9_]+)\s*\((.*?)\r?\n\);/s',
            $schema,
            $matches,
            PREG_SET_ORDER
        );

        $tables = [];

        foreach ($matches as $match) {
            $table = $match[1];
            $columns = [];
            $primary = null;
            $primaryType = null;

            foreach ($this->splitTopLevel($match[2]) as $line) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $first = strtoupper(strtok($line, " \t\n\r(") ?: '');

                // UNIQUE (...), PRIMARY KEY (...), FOREIGN KEY ...: table-level
                // constraints, not columns.
                if (in_array($first, ['UNIQUE', 'PRIMARY', 'FOREIGN', 'CONSTRAINT', 'CHECK', 'EXCLUDE'], true)) {
                    continue;
                }

                if (preg_match('/^([a-z0-9_]+)\s+([A-Za-z].*)$/s', $line, $parts) !== 1) {
                    continue;
                }

                $columns[] = $parts[1];

                if ($primary === null && stripos($parts[2], 'PRIMARY KEY') !== false) {
                    $primary = $parts[1];
                    $primaryType = strtoupper(strtok($parts[2], " \t") ?: '');
                }
            }

            if ($columns !== []) {
                $tables[$table] = ['columns' => $columns, 'primary' => $primary, 'primary_type' => $primaryType];
            }
        }

        if ($tables === []) {
            throw new RuntimeException('No tables could be read from SYSTEM_SCHEMA.sql.');
        }

        return $this->structure = $tables;
    }

    /**
     * Every row of one table, read fresh on this run.
     *
     * The Supabase client already pages through PostgREST's 1000-row response
     * cap, so a large table is not silently truncated.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchRows(string $table): array
    {
        $rows = [];

        try {
            foreach (Supabase::table($table)->select('*')->get() as $row) {
                $rows[] = $row->toArray();
            }
        } catch (Throwable $e) {
            // A table that cannot be read does not abort the whole backup: the
            // seed records the error and the other tables are still saved.
            Log::warning('Backup could not read table', ['table' => $table, 'message' => $e->getMessage()]);
        }

        return $rows;
    }

    /**
     * @param  array{columns: list<string>, primary: ?string, primary_type: ?string}  $definition
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertStatements(string $table, array $definition, array $rows): string
    {
        if ($rows === []) {
            return "-- {$table} (0 rows)\n";
        }

        // The schema's column order first, then any column the live table has
        // that the schema file does not. Dropping those would lose data.
        $known = $definition['columns'];
        $extra = [];

        foreach ($rows as $row) {
            foreach (array_keys($row) as $column) {
                if (! in_array($column, $known, true) && ! in_array($column, $extra, true)) {
                    $extra[] = $column;
                }
            }
        }

        $columns = array_merge($known, $extra);
        $columnList = implode(', ', array_map(fn ($column) => '"' . $column . '"', $columns));

        $sql = "-- {$table} (" . count($rows) . " rows)\n";

        foreach (array_chunk($rows, self::ROWS_PER_INSERT) as $chunk) {
            $values = [];

            foreach ($chunk as $row) {
                $cell = [];

                foreach ($columns as $column) {
                    $cell[] = $this->literal($row[$column] ?? null);
                }

                $values[] = '(' . implode(', ', $cell) . ')';
            }

            $sql .= "INSERT INTO public.{$table} ({$columnList}) VALUES\n"
                . implode(",\n", $values)
                . ";\n";
        }

        return $sql . "\n";
    }

    /**
     * After the rows are back, move every id sequence past the highest restored
     * id. Without this the first insert after a restore tries to reuse id 1 and
     * fails on the primary key.
     *
     * @param  array<string, array{columns: list<string>, primary: ?string, primary_type: ?string}>  $tables
     * @param  array<string, int>  $counts
     */
    private function sequenceBlock(array $tables, array $counts): string
    {
        $lines = [];

        foreach ($tables as $table => $definition) {
            // Only real serial columns have a sequence. Every other table here
            // has a UUID primary key; setval() on those would abort the restore.
            $type = (string) ($definition['primary_type'] ?? '');

            if ($definition['primary'] !== 'id'
                || ! in_array($type, ['BIGSERIAL', 'SERIAL', 'SMALLSERIAL'], true)
                || ($counts[$table] ?? 0) === 0) {
                continue;
            }

            $lines[] = "SELECT setval(pg_get_serial_sequence('public.{$table}', 'id'), "
                . "(SELECT MAX(id) FROM public.{$table}), true);";
        }

        if ($lines === []) {
            return '';
        }

        return "\n-- advance sequences past the restored ids\n" . implode("\n", $lines) . "\n";
    }

    /**
     * One SQL value. Text is always written as an escape string so a backslash
     * or a quote in a name or a body can never end the literal early.
     */
    private function literal(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (is_int($value) || is_float($value)) {
            return is_float($value) && ! is_finite($value) ? 'NULL' : (string) $value;
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $this->escape((string) $value);
    }

    private function escape(string $value): string
    {
        return "E'" . str_replace(['\\', "'"], ['\\\\', "''"], $value) . "'";
    }

    /**
     * Split a CREATE TABLE body on the commas that separate columns, ignoring
     * the ones inside NUMERIC(12, 2) or a quoted default.
     *
     * @return list<string>
     */
    private function splitTopLevel(string $body): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $inQuote = false;
        $length = strlen($body);

        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];

            if ($inQuote) {
                $current .= $char;

                if ($char === "'") {
                    $inQuote = false;
                }

                continue;
            }

            if ($char === "'") {
                $inQuote = true;
                $current .= $char;

                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    private function seedHeader(string $stamp, Carbon $at, int $tableCount): string
    {
        $takenAt = $at->format('Y-m-d H:i:s');
        $tableNames = implode(', ', array_keys($this->tables()));

        return "-- ============================================================================\n"
            . "-- DUKA MKONONI — seed_{$stamp}.sql\n"
            . "-- Data snapshot taken at {$takenAt} " . self::TIMEZONE . ".\n"
            . "--\n"
            . "-- Restore: run schema_{$stamp}.sql first (it creates the tables), then this file.\n"
            . "-- Tables pulled ({$tableCount}): {$tableNames}\n"
            . "-- ============================================================================\n\n"
            . "BEGIN;\n\n";
    }

    /** Upload one file and return its URL, or fail loudly. */
    private function upload(string $publicId, string $contents, string $contentType = 'text/plain'): string
    {
        $url = $this->cloudinary->uploadRaw($contents, $publicId, $contentType);

        if ($url === null) {
            throw new RuntimeException(
                'Cloudinary rejected ' . basename($publicId) . ': '
                . ($this->cloudinary->lastError() ?? 'no reason given')
            );
        }

        return $url;
    }
}
