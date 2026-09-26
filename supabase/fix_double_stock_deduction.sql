-- =====================================================================
-- Fix: double stock deduction on every sale
-- =====================================================================
-- Symptom: selling 2 items of a product deducted 4 from stock.
--
-- Root cause: this Supabase project has a TRIGGER on `sale_items` that
-- deducts the product's stock automatically whenever a sale item row is
-- inserted. The app ALREADY deducts stock in PHP when a sale is saved
-- (SaleController::store and AiImportController::commitAiSale), so every
-- sale deducts twice: once by the trigger, once by PHP.
--
-- Proof: a controlled test inserted ONLY a sale_items row through the
-- REST API (no PHP involved) and the product's stock moved 10 -> 9 by
-- itself. The SQL below re-runs that check safely with a transaction
-- that always rolls back, then drops the trigger.
--
-- Run this whole file in: Supabase Dashboard -> SQL Editor -> New query
-- =====================================================================

-- ---------------------------------------------------------------------
-- STEP 1 (optional, read-only): see what triggers exist on sale_items
-- ---------------------------------------------------------------------
select
    tg.tgname                as trigger_name,
    c.relname                as table_name,
    p.proname                as function_name,
    pg_get_triggerdef(tg.oid) as definition
from pg_trigger tg
join pg_class  c on c.oid = tg.tgrelid
join pg_proc   p on p.oid = tg.tgfoid
join pg_namespace n on n.oid = c.relnamespace
where c.relname = 'sale_items'
  and not tg.tgisinternal;

-- ---------------------------------------------------------------------
-- STEP 2 (optional, read-only): prove the trigger deducts stock.
-- Everything happens inside a transaction that is ALWAYS rolled back,
-- so nothing is changed and no real sale is created.
-- Expected output: stock_after_item_insert = stock_before - 1.
-- If the two are equal, the trigger is already gone; skip to Step 4.
-- ---------------------------------------------------------------------
do $$
declare
    v_seller   uuid;
    v_product  uuid;
    v_sale     uuid;
    v_item     uuid;
    v_before   numeric;
    v_after    numeric;
begin
    select seller_id into v_seller from sales order by created_at desc limit 1;

    insert into products (id, seller_id, name, category, description,
                          price, expected_selling_price, stock, is_active,
                          created_at, updated_at)
    values (gen_random_uuid(), v_seller, 'ZZ-DIAG-TRIGGER-TEST (rollback)',
            'diagnostic', 'temporary row - rolled back',
            1, 1, 10, true, now(), now())
    returning id, stock into v_product, v_before;

    insert into sales (id, seller_id, customer_id, sale_date, total_amount,
                       payment_method, notes, invoice_number,
                       created_at, updated_at)
    values (gen_random_uuid(), v_seller, null, current_date, 1, 'cash',
            'DIAG - rolled back', 'DIAG-ROLLBACK', now(), now())
    returning id into v_sale;

    insert into sale_items (id, sale_id, product_id, quantity, unit_price,
                            total_price, created_at)
    values (gen_random_uuid(), v_sale, v_product, 1, 1, 1, now())
    returning id into v_item;

    select stock into v_after from products where id = v_product;

    raise notice 'stock_before=%, stock_after_item_insert=%', v_before, v_after;
    raise notice 'If stock_after is stock_before - 1, a trigger is still active.';

    -- Force the whole diagnostic to be reverted, always.
    raise exception 'DIAGNOSTIC ROLLBACK (this error is expected and harmless)';
exception
    when raise_exception then
        raise notice 'Diagnostic rolled back. Nothing was changed.';
end $$;

-- ---------------------------------------------------------------------
-- STEP 3: drop the trigger.
-- This is the exact trigger found by STEP 1
-- (trigger_name = update_stock_after_sale_item, function = update_product_stock).
drop trigger if exists update_stock_after_sale_item on sale_items;

-- Optional cleanup: the trigger function is now unused. If it has
-- arguments the drop is skipped harmlessly and you can remove it later
-- from Database -> Functions in the dashboard.
drop function if exists update_product_stock();

-- Other common names, kept in case of duplicates:
drop trigger if exists trg_deduct_stock_on_sale_item on sale_items;
drop trigger if exists deduct_stock_trigger on sale_items;
drop trigger if exists update_stock_trigger on sale_items;
drop trigger if exists sale_items_stock_trigger on sale_items;

-- ---------------------------------------------------------------------
-- STEP 4 (optional, read-only): re-verify with the same rollback test.
-- Run ONLY the STEP 2 block again. Now it must print
-- stock_after_item_insert = stock_before (equal). That is the success
-- signal: the insert no longer moves stock by itself.
-- ---------------------------------------------------------------------

-- Done. From now on every sale deducts stock exactly once, from the
-- app's own logic (which logs stock_updates in the audit log and is
-- protected by clientSaleKey idempotency). No other code needs to change.
