<?php

namespace App\Models;

/** Append-only lifecycle history of one physical item. */
class ProductItemEvent extends SupabaseModel
{
    protected static $table = 'product_item_events';
}
