<?php

namespace Tests\Feature;

use App\Services\DatabaseBackupService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The automatic legacy-log cleanup is safe by construction: it only deletes
 * after that day's automatic backup is recorded, and it stops when the backup
 * is missing or incomplete. These tests pin both the gate and the exact
 * operator-facing message.
 */
class LogRetentionTest extends TestCase
{
    /** @var list<array{url:string,method:string,body:string}> */
    private array $requests = [];

    /** @var list<array<int, array<string, mixed>>> */
    private array $logPages = [];

    private bool $backupExists = true;

    private string $backupStatus = 'complete';

    private ?string $backupSeedUrl = 'https://cdn.test/seed.sql';

    protected function setUp(): void
    {
        parent::setUp();

        $this->requests = [];
        $this->logPages = [];
        $this->backupExists = true;
        $this->backupStatus = 'complete';
        $this->backupSeedUrl = 'https://cdn.test/seed.sql';

        config([
            'supabase.url' => 'http://supabase.test',
            'supabase.service_role_key' => 'test-service-key',
            'supabase.timeout' => 5,
            'logs.retention_days' => 30,
            'logs.batch_limit' => 1000,
            'logs.max_batches_per_run' => 5,
        ]);

        $requests = &$this->requests;
        $logPages = &$this->logPages;
        $backupExists = &$this->backupExists;
        $backupStatus = &$this->backupStatus;
        $backupSeedUrl = &$this->backupSeedUrl;

        Http::fake(function ($request) use (&$requests, &$logPages, &$backupExists, &$backupStatus, &$backupSeedUrl) {
            $requests[] = [
                'url' => $request->url(),
                'method' => $request->method(),
                'body' => $request->body(),
            ];

            $url = $request->url();
            $get = $request->method() === 'GET';

            if (str_contains($url, '/rest/v1/backups')) {
                if (! $backupExists) {
                    return Http::response([], 200);
                }

                return Http::response([[
                    'id' => 1,
                    'backup_date' => '2026-11-06',
                    'kind' => 'automatic',
                    'stamp' => '2026-11-06',
                    'folder' => DatabaseBackupService::folderFor('2026-11-06'),
                    'seed_url' => $backupSeedUrl,
                    'status' => $backupStatus,
                ]], 200);
            }

            if (str_contains($url, '/rest/v1/user_logs')) {
                if ($get) {
                    return Http::response(array_shift($logPages) ?? [], 200);
                }

                if ($request->method() === 'DELETE') {
                    return Http::response([], 204);
                }

                return Http::response([['id' => 'audit-row']], 201);
            }

            if (str_contains($url, '/rest/v1/activity_events')) {
                return Http::response([], $get ? 200 : 201);
            }

            return Http::response([], 200);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return list<array{url:string,method:string,body:string}> */
    private function callsTo(string $needle, ?string $method = null): array
    {
        return array_values(array_filter($this->requests, function ($request) use ($needle, $method) {
            return str_contains($request['url'], $needle)
                && ($method === null || $request['method'] === $method);
        }));
    }

    private function freezeAtFirstHourAfterMidnight(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-06 01:00:00', DatabaseBackupService::TIMEZONE));
    }

    public function test_it_is_scheduled_hourly_after_midnight_in_tanzania_time(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'logs:prune'));

        $this->assertNotNull($event, 'logs:prune must be registered with the scheduler.');
        $this->assertSame('0 1-23 * * *', $event->expression, 'Cleanup must start after midnight and run hourly.');
        $this->assertSame(DatabaseBackupService::TIMEZONE, $event->timezone);
    }

    public function test_it_deletes_old_rows_only_when_the_daily_backup_exists(): void
    {
        $this->freezeAtFirstHourAfterMidnight();
        $this->logPages = [
            [['id' => 'log-old-1'], ['id' => 'log-old-2']],
            [],
        ];

        $exit = Artisan::call('logs:prune');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('2 rows of logs were deleted 2026-11-06', $output);
        $this->assertCount(1, $this->callsTo('/rest/v1/backups', 'GET'), 'The backup gate must be checked first.');
        $this->assertNotEmpty($this->callsTo('/rest/v1/user_logs', 'DELETE'), 'Eligible rows must be deleted.');

        $reads = $this->callsTo('/rest/v1/user_logs', 'GET');
        $this->assertNotEmpty($reads);
        $this->assertStringContainsString(
            'created_at=lt.2026-10-06T22:00:00.000Z',
            urldecode($reads[0]['url']),
            'The cutoff must be exactly 30 days before the Tanzania run time.'
        );

        $audit = $this->callsTo('/rest/v1/user_logs', 'POST');
        $this->assertCount(1, $audit, 'The cleanup must write one audit record.');
        $row = json_decode($audit[0]['body'], true);
        $this->assertSame('LOGS_AUTO_PRUNED', $row['action']);
        $this->assertStringContainsString('2 rows of logs were deleted 2026-11-06', (string) ($row['details'] ?? ''));
    }

    public function test_it_stops_without_deleting_when_the_daily_backup_is_missing(): void
    {
        $this->freezeAtFirstHourAfterMidnight();
        $this->backupExists = false;

        $exit = Artisan::call('logs:prune');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No completed daily backup found for 2026-11-06', $output);
        $this->assertEmpty($this->callsTo('/rest/v1/user_logs', 'DELETE'), 'No row may be deleted without a backup.');
        $this->assertEmpty($this->callsTo('/rest/v1/user_logs', 'GET'), 'The command must stop before reading logs.');
    }

    public function test_an_incomplete_backup_does_not_unlock_deletion(): void
    {
        $this->freezeAtFirstHourAfterMidnight();
        $this->backupStatus = 'pending';

        $exit = Artisan::call('logs:prune');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No completed daily backup found for 2026-11-06', $output);
        $this->assertEmpty($this->callsTo('/rest/v1/user_logs', 'DELETE'));
    }

    public function test_a_backup_without_a_seed_file_does_not_unlock_deletion(): void
    {
        $this->freezeAtFirstHourAfterMidnight();
        $this->backupSeedUrl = null;

        $exit = Artisan::call('logs:prune');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No completed daily backup found for 2026-11-06', $output);
        $this->assertEmpty($this->callsTo('/rest/v1/user_logs', 'DELETE'));
    }
}
