<?php

namespace App\Http\Controllers\Api;

use App\Services\Supabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BackupController extends BaseController
{
    private const KNOWN_TABLES = [
        'users', 'sales', 'sale_items', 'products', 'customers',
        'user_logs', 'password_reset_codes', 'pending_emails',
        'matangazo', 'reactions', 'notifications', 'payments',
        'expenses', 'user_presence_history', 'active_sessions',
        'ai_import_verifications',
    ];

    private static ?\DateTimeInterface $lastBackupTime = null;
    private static int $backupCount = 0;

    private function backupDir(): string
    {
        $dir = base_path('backup');
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private function escapeSQLString($val): string
    {
        if ($val === null) return 'NULL';
        if (is_int($val) || is_float($val) || is_numeric($val)) return (string) $val;
        if (is_bool($val)) return $val ? 'TRUE' : 'FALSE';
        if (is_array($val) || is_object($val)) {
            $json = json_encode($val);
            if ($json === false) $json = (string) $val;
            return "'" . str_replace("'", "''", $json) . "'";
        }
        return "'" . str_replace("'", "''", (string) $val) . "'";
    }

    private function discoverTableNames(): array
    {
        $verified = [];
        foreach (self::KNOWN_TABLES as $tableName) {
            try {
                Supabase::table($tableName)->select('id')->limit(1)->get();
                $verified[] = $tableName;
            } catch (Throwable $e) {
            }
        }
        return $verified;
    }

    private function getTableColumns(string $tableName): array
    {
        $row = Supabase::table($tableName)->limit(1)->get()->first();
        if ($row) {
            return array_keys($row->toArray());
        }
        return [];
    }

    private function backupTimestamp(): string
    {
        $ts = now()->toISOString(true);
        $ts = str_replace('T', '_', $ts);
        $ts = str_replace(':', '-', $ts);
        $ts = preg_replace('/\.\d+Z/', '', $ts);
        return $ts;
    }

    private function performDatabaseBackup(): array
    {
        $startTime = microtime(true);

        try {
            $tableNames = $this->discoverTableNames();

            if (count($tableNames) === 0) {
                return ['success' => false, 'error' => 'No accessible tables found'];
            }

            $lines = [];
            $lines[] = '-- ============================================';
            $lines[] = '-- DukaMkononi Database Backup (Cloudinary)';
            $lines[] = '-- Generated: ' . now()->toISOString(true);
            $lines[] = '-- Server: ' . config('supabase.url', env('SUPABASE_URL', ''));
            $lines[] = '-- Tables: ' . implode(', ', $tableNames);
            $lines[] = '-- ============================================';
            $lines[] = '';
            $lines[] = '-- INSERT-only backup: preserves existing schema on restore';
            $lines[] = '';
            $lines[] = 'BEGIN;';
            $lines[] = '';

            $totalRows = 0;

            foreach ($tableNames as $tableName) {
                try {
                    $lines[] = '-- ==========================================';
                    $lines[] = '-- Table: ' . $tableName;
                    $lines[] = '-- ==========================================';

                    $columnNames = $this->getTableColumns($tableName);

                    $rows = Supabase::table($tableName)->limit(1000000)->get()->toArray();

                    if (count($rows) === 0) {
                        $lines[] = '-- No data in table "' . $tableName . '"';
                        $lines[] = '';
                        continue;
                    }

                    $columnList = implode(', ', array_map(fn($c) => '"' . $c . '"', $columnNames));
                    $batchSize = 50;
                    for ($i = 0; $i < count($rows); $i += $batchSize) {
                        $batch = array_slice($rows, $i, $batchSize);
                        $valueStrings = [];
                        foreach ($batch as $rowArr) {
                            $values = [];
                            foreach ($columnNames as $col) {
                                $values[] = $this->escapeSQLString($rowArr[$col] ?? null);
                            }
                            $valueStrings[] = '(' . implode(', ', $values) . ')';
                        }

                        $lines[] = 'INSERT INTO "' . $tableName . '" (' . $columnList . ') VALUES';
                        $lines[] = implode(',', $valueStrings) . ';';
                    }

                    $totalRows += count($rows);
                    $lines[] = '';
                } catch (Throwable $tableError) {
                    $lines[] = '-- Error backing up table "' . $tableName . '": ' . $tableError->getMessage();
                    $lines[] = '';
                }
            }

            $lines[] = 'COMMIT;';
            $lines[] = '';
            $lines[] = '-- ============================================';
            $lines[] = '-- Backup completed: ' . now()->toISOString(true);
            $lines[] = '-- Total tables: ' . count($tableNames) . ', Total rows: ' . $totalRows;
            $lines[] = '-- ============================================';

            $sqlContent = implode("\r\n", $lines);

            $filename = 'backup_' . $this->backupTimestamp() . '.sql';
            $filePath = $this->backupDir() . DIRECTORY_SEPARATOR . $filename;
            file_put_contents($filePath, $sqlContent);

            self::$lastBackupTime = now();
            self::$backupCount++;

            $duration = (microtime(true) - $startTime);
            $sizeKB = strlen($sqlContent) / 1024;
            $sizeKB = round($sizeKB, 2);

            return [
                'success' => true,
                'filename' => $filename,
                'filePath' => $filePath,
                'cloudinary_url' => null,
                'cloudinary_public_id' => null,
                'tables' => count($tableNames),
                'rows' => $totalRows,
                'sizeKB' => $sizeKB,
                'duration' => round($duration, 2),
                'timestamp' => now()->toISOString(true),
            ];
        } catch (Throwable $error) {
            return [
                'success' => false,
                'error' => $error->getMessage(),
                'timestamp' => now()->toISOString(true),
            ];
        }
    }

    private function listBackupFiles(): array
    {
        $dir = $this->backupDir();
        $files = [];
        foreach (glob($dir . DIRECTORY_SEPARATOR . 'backup_*.sql') ?: [] as $file) {
            $files[] = [
                'filename' => basename($file),
                'filePath' => $file,
                'fileSize' => filesize($file),
                'createdAt' => date('c', filemtime($file)),
            ];
        }
        usort($files, fn($a, $b) => strcmp($b['createdAt'], $a['createdAt']));
        return $files;
    }

    public function backup(Request $request): JsonResponse
    {
        try {
            $result = $this->performDatabaseBackup();

            if ($result['success']) {
                return $this->json([
                    'success' => true,
                    'message' => 'Database backup completed successfully and uploaded to Cloudinary',
                    'backup' => $result,
                    'backupCount' => self::$backupCount,
                    'lastBackup' => self::$lastBackupTime ? self::$lastBackupTime->format('c') : null,
                ]);
            }

            return $this->json([
                'success' => false,
                'error' => $result['error'],
                'message' => 'Database backup failed',
            ], 500);
        } catch (Throwable $error) {
            return $this->json([
                'success' => false,
                'error' => $error->getMessage(),
                'message' => 'Database backup failed',
            ], 500);
        }
    }

    public function status(Request $request): JsonResponse
    {
        try {
            $files = $this->listBackupFiles();
            $last = count($files) > 0 ? $files[0] : null;

            $recentFiles = array_map(function ($f) {
                return [
                    'filename' => $f['filename'],
                    'fileSize' => $f['fileSize'],
                    'createdAt' => $f['createdAt'],
                ];
            }, array_slice($files, 0, 10));

            return $this->json([
                'success' => true,
                'backupCount' => count($files),
                'lastBackup' => $last ? $last['createdAt'] : (self::$lastBackupTime ? self::$lastBackupTime->format('c') : null),
                'storage' => 'Local Filesystem',
                'totalFiles' => count($files),
                'recentFiles' => $recentFiles,
            ]);
        } catch (Throwable $error) {
            return $this->json([
                'success' => false,
                'error' => $error->getMessage(),
            ], 500);
        }
    }

    public function download(Request $request, string $index)
    {
        try {
            $files = $this->listBackupFiles();
            $idx = (int) $index;

            if ($idx < 0 || $idx >= count($files)) {
                return response()->json(['error' => 'Backup not found'], 404);
            }

            $file = $files[$idx];
            if (!file_exists($file['filePath'])) {
                return response()->json(['error' => 'Backup not found'], 404);
            }

            return response()->download($file['filePath'], $file['filename'], [
                'Content-Type' => 'application/sql',
            ]);
        } catch (Throwable $error) {
            return response()->json(['error' => $error->getMessage()], 500);
        }
    }
}