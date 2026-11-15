<?php

namespace App\Models;

/**
 * One row per PHYSICAL unit, with its own QR token.
 *
 * A product is registered once in `products`; every individual unit that takes
 * part in scanning is a separate row here. Only the database functions
 * (qr_receive_item, qr_reserve_item, qr_commit_sale, qr_move_item_*) may move
 * an item from one status/location to another - controllers must not.
 */
class ProductItem extends SupabaseModel
{
    protected static $table = 'product_items';

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_STOCK = 'in_stock';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_SOLD = 'sold';
    public const STATUS_VOID = 'void';

    public const LOCATION_OUTSIDE = 'outside';
    public const LOCATION_FRIDGE = 'fridge';

    /** Columns safe to return to an authenticated business member. */
    public const PUBLIC_COLUMNS = [
        'id', 'product_id', 'branch_id', 'status', 'location', 'fridge_id',
        'receipt_reference', 'sale_id', 'received_at', 'sold_at',
        'created_at', 'updated_at',
    ];
}
