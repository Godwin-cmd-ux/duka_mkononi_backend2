<?php

namespace App\Models;

/**
 * One row per business. A missing row means "all defaults", which is why the
 * settings service merges defaults instead of requiring a backfill.
 */
class BusinessSetting extends SupabaseModel
{
    protected static $table = 'business_settings';
}
