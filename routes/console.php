<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled AI digests (capability #5)
|--------------------------------------------------------------------------
| The weekly business health digest runs on the configured day/time. The
| command is idempotent, so a re-run for the same week will not duplicate a
| business's digest. Delivery is disabled entirely when
| ai.health.delivery.enabled is false.
*/
Schedule::command('ai:weekly-digests')
    ->weeklyOn(
        (int) config('ai.health.delivery.day_of_week', 1),
        (string) config('ai.health.delivery.time', '06:00'),
    )
    ->withoutOverlapping()
    ->name('ai:weekly-digests');

/*
|--------------------------------------------------------------------------
| Monitoring retention (Phase 9)
|--------------------------------------------------------------------------
| Bounded, batched pruning of ordinary activity events and sessions past
| their configured retention windows. Security events and alerts are NEVER
| auto-deleted unless monitoring.retention.delete_security is explicitly
| enabled - retention of security evidence is an organizational decision,
| not a technical default. Each run writes its own MONITORING_PRUNED audit
| record (counts only), so the retention policy itself is auditable.
*/
Artisan::command('monitoring:prune', function () {
    $retention = (array) config('monitoring.retention', []);
    $batch = max(1, (int) ($retention['batch_limit'] ?? 1000));
    $maxBatches = max(1, (int) ($retention['max_batches_per_run'] ?? 50));

    $plans = [
        // Ordinary (non-security) events.
        [
            'label' => 'activity_events',
            'table' => \App\Models\ActivityEvent::class,
            'cutoff' => now()->subDays((int) ($retention['activity_days'] ?? 180))->toISOString(true),
            'extra' => fn ($q) => $q->where('is_security', false),
        ],
        // Browser-observed events churn fastest; shorter default window.
        [
            'label' => 'activity_events_browser',
            'table' => \App\Models\ActivityEvent::class,
            'cutoff' => now()->subDays((int) ($retention['browser_event_days'] ?? 90))->toISOString(true),
            'extra' => fn ($q) => $q->where('source', 'browser'),
        ],
        [
            'label' => 'session_activity',
            'table' => \App\Models\SessionActivity::class,
            'cutoff' => now()->subDays((int) ($retention['session_days'] ?? 120))->toISOString(true),
            'extra' => null,
        ],
    ];

    if (! empty($retention['delete_security'])) {
        $plans[] = [
            'label' => 'activity_events_security',
            'table' => \App\Models\ActivityEvent::class,
            'cutoff' => now()->subDays((int) ($retention['security_days'] ?? 365))->toISOString(true),
            'extra' => fn ($q) => $q->where('is_security', true),
        ];
        $plans[] = [
            'label' => 'security_alerts',
            'table' => \App\Models\SecurityAlert::class,
            'cutoff' => now()->subDays((int) ($retention['security_days'] ?? 365))->toISOString(true),
            'extra' => fn ($q) => $q->where('status', 'resolved'),
        ];
    }

    $totals = [];
    $failed = [];

    foreach ($plans as $plan) {
        $deleted = 0;
        try {
            for ($batchNo = 0; $batchNo < $maxBatches; $batchNo++) {
                $query = $plan['table']::query()->where('created_at', '<', $plan['cutoff']);
                if ($plan['extra']) {
                    ($plan['extra'])($query);
                }
                $ids = $query->limit($batch)->pluck('id')->all();
                if (! $ids) {
                    break;
                }
                // Delete in sub-chunks: an `in.(...)` filter with 1000 ids
                // would build a URL most servers refuse.
                foreach (array_chunk($ids, 100) as $chunk) {
                    $plan['table']::whereIn('id', $chunk)->delete();
                }
                $deleted += count($ids);
                if (count($ids) < $batch) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            // Table missing / API down: report and continue with other plans.
            $failed[$plan['label']] = $e->getMessage();
        }
        $totals[$plan['label']] = $deleted;
    }

    // The retention run is itself auditable (counts only, no payloads).
    \App\Services\AuditLogger::log(null, 'MONITORING_PRUNED', '/console/monitoring:prune', [
        'totals' => $totals,
        'failed' => array_keys($failed),
        'delete_security' => ! empty($retention['delete_security']),
    ], null, $failed === [] ? 'success' : 'failed');

    $this->comment('monitoring:prune ' . json_encode($totals));
    if ($failed) {
        $this->warn('failed: ' . json_encode(array_keys($failed)));
    }
})->purpose('Prune monitoring events/sessions past retention windows');

Schedule::command('monitoring:prune')
    ->daily()
    ->withoutOverlapping()
    ->name('monitoring:prune');
