<?php

/*
|--------------------------------------------------------------------------
| Legacy user log retention
|--------------------------------------------------------------------------
|
| `user_logs` is the audit table behind the legacy logs screens. It is only
| pruned after the nightly database backup has been recorded, so an automatic
| cleanup can never remove the only copy of a day's evidence before it has
| been captured in a backup.
|
*/

return [

    // Rows older than this many days are removed by logs:prune.
    'retention_days' => (int) env('LOG_RETENTION_DAYS', 30),

    // Read/delete in bounded batches so a large table never produces an
    // unbounded PostgREST URL or a single enormous response.
    'batch_limit' => (int) env('LOG_PRUNE_BATCH_LIMIT', 1000),
    'max_batches_per_run' => (int) env('LOG_PRUNE_MAX_BATCHES', 50),

];
