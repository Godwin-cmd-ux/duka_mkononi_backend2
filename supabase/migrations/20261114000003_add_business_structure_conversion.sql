-- ============================================================================
-- Duka Mkononi — Branchless <-> Branchful business conversion
-- ============================================================================
--
-- Run this ONCE in the Supabase SQL Editor (or `supabase db push`).
-- Safe to re-run: both functions are CREATE OR REPLACE and no table is altered.
--
-- Why functions and not several REST calls:
--   A conversion touches users, products, sales, customers, services,
--   expenses, fridges and the business row. PostgREST cannot run those as one
--   transaction from the Laravel side, so a network drop halfway through
--   would leave a half-converted business. These functions execute inside one
--   PostgreSQL transaction and lock the business row, so two simultaneous
--   requests cannot both convert.
--
-- Identification rule (unchanged):
--   business_id = the whole business, branch_id = one branch inside it.
--
-- Safety:
--   * The business id, users, products, sales and all history are preserved.
--   * Branch rows are never deleted; deactivation only sets status='inactive'.
--   * Financial rows are never merged or rewritten, only their branch pointer.
--   * branchful -> branchless is REFUSED when the same product name/SKU/barcode
--     exists in more than one branch, because silently merging those rows would
--     either double-count or discard stock. The API reports that conflict.
-- ============================================================================

begin;

-- ---------------------------------------------------------------------------
-- 1. Branchless -> Branchful
-- ---------------------------------------------------------------------------
create or replace function public.convert_business_to_branch(
    p_business_id uuid,
    p_branch_name text,
    p_location text default null,
    p_latitude double precision default null,
    p_longitude double precision default null,
    p_actor uuid default null
)
returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_business public.businesses%rowtype;
    v_branch public.branches%rowtype;
    v_name text := btrim(coalesce(p_branch_name, ''));
begin
    select * into v_business
      from public.businesses
     where id = p_business_id
       for update;

    if not found then
        raise exception 'BUSINESS_NOT_FOUND';
    end if;

    if v_business.business_structure = 'branch' then
        raise exception 'ALREADY_BRANCHFUL';
    end if;

    if length(v_name) < 2 then
        raise exception 'BRANCH_NAME_REQUIRED';
    end if;

    if exists (
        select 1 from public.branches b
         where b.business_id = p_business_id
           and lower(btrim(b.branch_name)) = lower(v_name)
    ) then
        raise exception 'BRANCH_NAME_TAKEN';
    end if;

    insert into public.branches (
        id, business_id, branch_name, location, latitude, longitude,
        status, created_at, updated_at
    ) values (
        gen_random_uuid(), p_business_id, v_name,
        nullif(btrim(coalesce(p_location, '')), ''),
        p_latitude, p_longitude, 'active', now(), now()
    )
    returning * into v_branch;

    -- Sellers now belong to the first branch, so login and every write have a
    -- concrete branch. Administrators stay unassigned and choose a context.
    update public.users
       set branch_id = v_branch.id, updated_at = now()
     where business_id = p_business_id
       and role = 'seller';

    -- Existing branchless operations become operations of the new branch.
    -- products/sales/customers are linked to a business through their seller.
    update public.products p
       set branch_id = v_branch.id, updated_at = now()
     where exists (
        select 1 from public.users u
         where u.business_id = p_business_id
           and u.id::text = p.seller_id
     );

    update public.sales s
       set branch_id = v_branch.id, updated_at = now()
     where exists (
        select 1 from public.users u
         where u.business_id = p_business_id
           and u.id::text = s.seller_id
     );

    update public.customers c
       set branch_id = v_branch.id, updated_at = now()
     where exists (
        select 1 from public.users u
         where u.business_id = p_business_id
           and u.id::text = c.seller_id
     );

    if to_regclass('public.services') is not null then
        update public.services
           set branch_id = v_branch.id, updated_at = now()
         where business_id = p_business_id;
    end if;

    if to_regclass('public.service_sales') is not null then
        update public.service_sales
           set branch_id = v_branch.id, updated_at = now()
         where business_id = p_business_id;
    end if;

    update public.office_expenses
       set branch_id = v_branch.id, updated_at = now()
     where business_id = p_business_id;

    if to_regclass('public.fridges') is not null then
        update public.fridges
           set branch_id = v_branch.id, updated_at = now()
         where business_id = p_business_id;
    end if;

    if to_regclass('public.fridge_stock') is not null then
        update public.fridge_stock
           set branch_id = v_branch.id, updated_at = now()
         where business_id = p_business_id;
    end if;

    if to_regclass('public.fridge_stock_movements') is not null then
        update public.fridge_stock_movements
           set branch_id = v_branch.id
         where business_id = p_business_id;
    end if;

    if to_regclass('public.fridge_stock_alerts') is not null then
        update public.fridge_stock_alerts
           set branch_id = v_branch.id, updated_at = now()
         where business_id = p_business_id;
    end if;

    if to_regclass('public.sale_item_fridge_allocations') is not null then
        update public.sale_item_fridge_allocations
           set branch_id = v_branch.id
         where business_id = p_business_id;
    end if;

    update public.businesses
       set business_structure = 'branch',
           updated_at = now()
     where id = p_business_id;

    insert into public.user_logs (id, user_id, action, endpoint, details, status, created_at)
    values (
        gen_random_uuid(), p_actor::text, 'BUSINESS_STRUCTURE_CONVERTED',
        '/api/settings/business-structure/branchful',
        jsonb_build_object(
            'business_id', p_business_id,
            'from', 'non_branch',
            'to', 'branch',
            'branch_id', v_branch.id,
            'branch_name', v_name
        )::text,
        'success', now()
    );

    return jsonb_build_object(
        'business_id', p_business_id,
        'business_structure', 'branch',
        'branch', to_jsonb(v_branch)
    );
end;
$$;

-- ---------------------------------------------------------------------------
-- 2. Branchful -> Branchless
-- ---------------------------------------------------------------------------
create or replace function public.convert_business_to_branchless(
    p_business_id uuid,
    p_business_name text,
    p_location text default null,
    p_description text default null,
    p_business_type text default null,
    p_logo_url text default null,
    p_latitude double precision default null,
    p_longitude double precision default null,
    p_actor uuid default null
)
returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_business public.businesses%rowtype;
    v_name text := btrim(coalesce(p_business_name, ''));
    v_key text;
    v_duplicate int := 0;
    v_branches_deactivated int := 0;
begin
    select * into v_business
      from public.businesses
     where id = p_business_id
       for update;

    if not found then
        raise exception 'BUSINESS_NOT_FOUND';
    end if;

    if v_business.business_structure = 'non_branch' then
        raise exception 'ALREADY_BRANCHLESS';
    end if;

    if length(v_name) < 2 then
        raise exception 'BUSINESS_NAME_REQUIRED';
    end if;

    v_key := lower(v_name);

    if exists (
        select 1 from public.businesses b
         where b.id <> p_business_id
           and (
                b.legacy_name_key = v_key
                or lower(btrim(b.business_name)) = v_key
           )
    ) then
        raise exception 'BUSINESS_NAME_TAKEN';
    end if;

    -- A unified business would show both rows and count their stock twice.
    -- Refusing is safer than merging or discarding inventory. Count products
    -- of this business that live in more than one branch under the same
    -- non-empty name, SKU or barcode.
    select count(*) into v_duplicate
      from (
        select lower(btrim(p.name)) as product_key
          from public.products p
          join public.users u on u.id::text = p.seller_id
         where u.business_id = p_business_id
           and btrim(coalesce(p.name, '')) <> ''
         group by lower(btrim(p.name))
        having count(distinct coalesce(p.branch_id, '00000000-0000-0000-0000-000000000000'::uuid)) > 1

        union all

        select lower(btrim(p.sku)) as product_key
          from public.products p
          join public.users u on u.id::text = p.seller_id
         where u.business_id = p_business_id
           and btrim(coalesce(p.sku, '')) <> ''
         group by lower(btrim(p.sku))
        having count(distinct coalesce(p.branch_id, '00000000-0000-0000-0000-000000000000'::uuid)) > 1

        union all

        select lower(btrim(p.barcode)) as product_key
          from public.products p
          join public.users u on u.id::text = p.seller_id
         where u.business_id = p_business_id
           and btrim(coalesce(p.barcode, '')) <> ''
         group by lower(btrim(p.barcode))
        having count(distinct coalesce(p.branch_id, '00000000-0000-0000-0000-000000000000'::uuid)) > 1
      ) conflicts;

    if v_duplicate > 0 then
        raise exception 'UNSAFE_DUPLICATE_PRODUCTS';
    end if;

    -- Only overwrite the fields the form actually supplied. An omitted logo,
    -- type or map pin must not be erased by the conversion.
    update public.businesses
       set business_name = v_name,
           legacy_name_key = v_key,
           business_location = coalesce(nullif(btrim(coalesce(p_location, '')), ''), business_location),
           business_description = coalesce(nullif(btrim(coalesce(p_description, '')), ''), business_description),
           business_type = coalesce(nullif(btrim(coalesce(p_business_type, '')), ''), business_type),
           business_logo_url = coalesce(nullif(btrim(coalesce(p_logo_url, '')), ''), business_logo_url),
           business_latitude = coalesce(p_latitude, business_latitude),
           business_longitude = coalesce(p_longitude, business_longitude),
           business_structure = 'non_branch',
           updated_at = now()
     where id = p_business_id;

    -- Branchless access: sellers no longer carry an assignment, so login and
    -- every query resolve to the unified business. Roles are unchanged.
    update public.users
       set branch_id = null,
           business_name = v_name,
           updated_at = now()
     where business_id = p_business_id;

    -- Branches are preserved for history. Inactive rows are not selectable,
    -- and a later conversion back creates a fresh active branch.
    select count(*) into v_branches_deactivated
      from public.branches
     where business_id = p_business_id
       and status = 'active';

    update public.branches
       set status = 'inactive', updated_at = now()
     where business_id = p_business_id
       and status = 'active';

    -- branch_id values on operational rows are intentionally left as they
    -- were: branchless scope ignores them, so all inventory, sales, services,
    -- payments and reports stay visible and auditable at their original branch.

    insert into public.user_logs (id, user_id, action, endpoint, details, status, created_at)
    values (
        gen_random_uuid(), p_actor::text, 'BUSINESS_STRUCTURE_CONVERTED',
        '/api/settings/business-structure/branchless',
        jsonb_build_object(
            'business_id', p_business_id,
            'from', 'branch',
            'to', 'non_branch',
            'business_name', v_name,
            'branches_deactivated', v_branches_deactivated
        )::text,
        'success', now()
    );

    return jsonb_build_object(
        'business_id', p_business_id,
        'business_structure', 'non_branch',
        'business_name', v_name
    );
end;
$$;

-- These functions move tenant-wide data, so only the backend service role may
-- execute them. Browser/anon keys must never be able to call them directly.
revoke all on function public.convert_business_to_branch(uuid, text, text, double precision, double precision, uuid) from public;
revoke all on function public.convert_business_to_branchless(uuid, text, text, text, text, text, double precision, double precision, uuid) from public;

grant execute on function public.convert_business_to_branch(uuid, text, text, double precision, double precision, uuid) to service_role;
grant execute on function public.convert_business_to_branchless(uuid, text, text, text, text, text, double precision, double precision, uuid) to service_role;

commit;
