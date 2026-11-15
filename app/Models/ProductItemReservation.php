<?php

namespace App\Models;

/**
 * Temporary claim on a physical item while it sits in a seller's sale cart.
 *
 * A claim never moves stock. It expires, is released when the cart is
 * abandoned, or is consumed when the sale commits.
 */
class ProductItemReservation extends SupabaseModel
{
    protected static $table = 'product_item_reservations';
}
