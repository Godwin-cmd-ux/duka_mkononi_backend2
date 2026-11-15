<?php

namespace App\Models;

/**
 * A pending administrator receiving run for one product.
 *
 * Its items are 'pending' and are NOT stock until qr_complete_receiving()
 * commits them in a single atomic receipt; qr_cancel_receiving() deletes them.
 */
class ScannerReceivingSession extends SupabaseModel
{
    protected static $table = 'scanner_receiving_sessions';

    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
}
