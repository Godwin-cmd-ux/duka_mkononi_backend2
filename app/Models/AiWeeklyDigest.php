<?php

namespace App\Models;

/**
 * A stored weekly business health digest (capability #5).
 *
 * One row per (business_id, period_start, period_end). The unique key is
 * enforced in application code (HealthDigestService) so a digest is never
 * duplicated for the same business and reporting period.
 */
class AiWeeklyDigest extends SupabaseModel
{
    protected static $table = 'ai_weekly_digests';
}
