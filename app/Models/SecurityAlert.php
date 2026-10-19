<?php

namespace App\Models;

/**
 * An aggregated security incident: one row per (rule, subject) while an
 * incident is open, so repeated failures bump occurrence_count instead of
 * creating duplicate alerts. Acknowledgement/resolution state and the
 * investigation note trail live on the row; every status change is itself
 * audited through the existing AuditLogger.
 */
class SecurityAlert extends SupabaseModel
{
    protected static $table = 'security_alerts';
}
