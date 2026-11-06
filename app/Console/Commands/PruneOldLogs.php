<?php

namespace App\Console\Commands;

use App\Models\UserLog;
use App\Services\AuditLogger;
use App\Services\DatabaseBackupService;
use App\Services\Supabase;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Removes legacy `user_logs` rows past the retention window, but only after
 * the nightly backup for the current Tanzania date has been recorded.
 *
 * The backup gate is deliberately fail-closed: if the backup table cannot be
 * read, or the row is missing / incomplete, this command stops without
 * deleting anything. The scheduler runs it every hour after midnight, so a
 * backup that finishes late is still honoured by a later run.
 */
class PruneOldLogs extends Command
{
    protected $signature = 'logs:prune
        {--days= : Retention window in days (defaults to config/logs.php)}
        {--force : Skip the nightly-backup gate}
        {--dry-run : Count eligible rows without deleting them}';

    protected $description = 'Delete user_logs older than the retention window after the nightly backup';

    public function handle(): int
    {
        $now = Carbon::now(DatabaseBackupService::TIMEZONE);
        $backupDate = $now->format('Y-m-d');

        $daysOption = $this->option('days');
        $days = $daysOption !== null && $daysOption !== ''
            ? max(1, (int) $daysOption)
            : max(1, (int) config('logs.retention_days', 30));

        if (! $this->option('force') && ! $this->dailyBackupExists($backupDate)) {
            $this->warn("No completed daily backup found for {$backupDate}; log cleanup stopped.");

            return self::SUCCESS;
        }

        $cutoff = $now->copy()->subDays($days)->utc()->format('Y-m-d\TH:i:s.v\Z');
        $deleted = 0;
        $truncated = false;

        try {
            if ($this->option('dry-run')) {
                $deleted = UserLog::query()->where('created_at', '<', $cutoff)->count();
            } else {
                [$deleted, $truncated] = $this->deleteBatches($cutoff);
            }
        } catch (Throwable $e) {
            Log::error('Automatic user log cleanup failed', [
                'cutoff_date' => $cutoff,
                'backup_date' => $backupDate,
                'message' => $e->getMessage(),
            ]);

            if (! $this->option('dry-run')) {
                AuditLogger::log(null, 'LOGS_AUTO_PRUNED', '/console/logs:prune', [
                    'deleted_count' => $deleted,
                    'retention_days' => $days,
                    'cutoff_date' => $cutoff,
                    'backup_date' => $backupDate,
                    'error' => $e->getMessage(),
                ], null, 'failed');
            }

            $this->error('Log cleanup failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $message = "{$deleted} rows of logs were deleted {$backupDate}";

        if ($this->option('dry-run')) {
            $this->info('DRY RUN: ' . $message);
        } else {
            AuditLogger::log(null, 'LOGS_AUTO_PRUNED', '/console/logs:prune', [
                'deleted_count' => $deleted,
                'retention_days' => $days,
                'cutoff_date' => $cutoff,
                'backup_date' => $backupDate,
                'truncated' => $truncated,
                'message' => $message,
            ], null, 'success');

            $this->info($message);
        }

        if ($truncated) {
            $this->warn('The batch limit was reached; older rows remain for the next hourly run.');
        }

        return self::SUCCESS;
    }

    /**
     * A backup counts only when the automatic folder for the date is recorded,
     * marked complete and carries a seed file. A half-written or induced-only
     * backup must never unlock deletion.
     */
    private function dailyBackupExists(string $date): bool
    {
        try {
            $row = Supabase::table('backups')
                ->where('folder', DatabaseBackupService::folderFor($date))
                ->first();
        } catch (Throwable $e) {
            Log::warning('Log cleanup could not check the daily backup', [
                'backup_date' => $date,
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        if ($row === null) {
            return false;
        }

        $status = strtolower((string) ($row['status'] ?? 'complete'));

        return $status === 'complete' && ! empty($row['seed_url']);
    }

    /**
     * @return array{0:int,1:bool} deleted count, whether the batch cap stopped a full run
     */
    private function deleteBatches(string $cutoff): array
    {
        $batch = max(1, (int) config('logs.batch_limit', 1000));
        $maxBatches = max(1, (int) config('logs.max_batches_per_run', 50));
        $deleted = 0;

        for ($batchNo = 0; $batchNo < $maxBatches; $batchNo++) {
            $ids = UserLog::query()
                ->where('created_at', '<', $cutoff)
                ->limit($batch)
                ->pluck('id')
                ->all();

            if ($ids === []) {
                return [$deleted, false];
            }

            // PostgREST accepts a bounded `in.(...)` list; keep each request
            // far below the URL length most proxies allow.
            foreach (array_chunk($ids, 100) as $chunk) {
                UserLog::whereIn('id', $chunk)->delete();
            }

            $deleted += count($ids);

            if (count($ids) < $batch) {
                return [$deleted, false];
            }
        }

        return [$deleted, true];
    }
}
