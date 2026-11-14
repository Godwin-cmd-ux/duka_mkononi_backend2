-- ============================================================================
-- Fix: transfers into a fridge did not increase inside stock
-- ============================================================================
--
-- The original function inserted a new fridge_stock row with quantity 0 and,
-- on conflict, added excluded.quantity -- which was also 0. The movement was
-- written, so the activity feed looked correct, but the fridge quantity stayed
-- at zero (or unchanged) forever.
--
-- This migration replaces only the function body. It is safe to re-run and
-- does not touch existing rows. Run it once in the Supabase SQL Editor for
-- environments that already applied 20261114000001_create_fridge_inventory.sql.
--
-- Historical movements that were recorded with the bug are intentionally NOT
-- replayed into stock: a ledger entry alone cannot prove whether the operator
-- retried the same transfer. Current fridge quantities stay authoritative.
-- ============================================================================

begin;

create or replace function public.fridge_transfer_in(
    p_fridge_id uuid,
    p_product_id uuid,
    p_quantity numeric,
    p_business_id uuid,
    p_branch_id uuid,
    p_user_id uuid,
    p_min_stock numeric default 0,
    p_notes text default null
) returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_fridge record;
    v_product record;
    v_inside numeric := 0;
    v_outside numeric := 0;
    v_new_qty numeric := 0;
begin
    if p_quantity is null or p_quantity <= 0 then
        raise exception 'Kiasi lazima kiwe kikubwa kuliko sifuri';
    end if;

    select * into v_fridge
      from public.fridges
     where id = p_fridge_id
       and business_id = p_business_id
       and (branch_id is not distinct from p_branch_id)
       and status = 'active'
     for update;
    if not found then
        raise exception 'Friji haipatikani kwenye biashara/tawi hili';
    end if;

    select * into v_product
      from public.products
     where id = p_product_id
       and is_active = true
     for update;
    if not found then
        raise exception 'Bidhaa haipatikani';
    end if;
    if p_branch_id is not null and v_product.branch_id is distinct from p_branch_id then
        raise exception 'Bidhaa ni ya tawi lingine';
    end if;

    -- products.stock is still the full business stock; stock already inside
    -- active fridges is not available to transfer a second time.
    select coalesce(sum(fs.quantity), 0)
      into v_inside
      from public.fridge_stock fs
      join public.fridges f on f.id = fs.fridge_id
     where fs.product_id = p_product_id
       and fs.business_id = p_business_id
       and (fs.branch_id is not distinct from p_branch_id)
       and f.status = 'active';

    v_outside := coalesce(v_product.stock, 0) - v_inside;
    if p_quantity > v_outside then
        raise exception 'Stock ya nje haitoshi. Inapatikana %', v_outside;
    end if;

    insert into public.fridge_stock (
        fridge_id, product_id, business_id, branch_id, quantity, min_stock
    ) values (
        p_fridge_id, p_product_id, p_business_id, p_branch_id,
        p_quantity, greatest(coalesce(p_min_stock, 0), 0)
    )
    on conflict (fridge_id, product_id) do update
       set quantity = public.fridge_stock.quantity + p_quantity,
           min_stock = greatest(public.fridge_stock.min_stock, excluded.min_stock),
           updated_at = now()
    returning quantity into v_new_qty;

    insert into public.fridge_stock_movements (
        business_id, branch_id, fridge_id, product_id, movement_type, quantity,
        source_location, destination_location, reference_type, reference_id,
        reason, notes, created_by
    ) values (
        p_business_id, p_branch_id, p_fridge_id, p_product_id, 'transfer_in', p_quantity,
        'outside', 'fridge', 'fridge', p_fridge_id,
        'transfer_in', p_notes, p_user_id
    );

    return jsonb_build_object(
        'success', true,
        'fridge_id', p_fridge_id,
        'product_id', p_product_id,
        'fridge_quantity', v_new_qty,
        'outside_stock', v_outside - p_quantity,
        'business_stock', v_product.stock
    );
end;
$$;

revoke all on function public.fridge_transfer_in(uuid, uuid, numeric, uuid, uuid, uuid, numeric, text) from public, anon, authenticated;
grant execute on function public.fridge_transfer_in(uuid, uuid, numeric, uuid, uuid, uuid, numeric, text) to service_role;

commit;
