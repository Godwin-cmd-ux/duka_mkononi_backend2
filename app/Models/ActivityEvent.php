<?php

namespace App\Models;

/**
 * One row per meaningful event (browser-observed or server-accepted), stored
 * in the monitoring schema. Reads/writes go through the project's PostgREST
 * client like every other App\Models class.
 */
class ActivityEvent extends SupabaseModel
{
    protected static $table = 'activity_events';
}
