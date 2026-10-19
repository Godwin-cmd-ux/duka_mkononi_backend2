<?php

namespace App\Models;

/**
 * An authentication session, keyed by a server-derived reference
 * (sha256 of the presented JWT - never the token itself). Created on login
 * (or on first observed API activity for a token issued before this feature)
 * and refreshed by throttled heartbeats.
 */
class SessionActivity extends SupabaseModel
{
    protected static $table = 'session_activity';
}
