-- ============================================================================
-- Unique physical items + QR scanning (stock receiving, selling, fridge
-- transfers, order identification)
-- ----------------------------------------------------------------------------
-- Authoritative DDL. Run this file ONCE in the Supabase SQL Editor (or
-- `supabase db push`) BEFORE deploying the application code that uses it.
-- The local Laravel mirror is
-- database/migrations/2026_11_15_000001_create_product_items_and_scanner.php
-- and exists only so tests/CI can build an equivalent SQLite schema.
--
-- Why a second table next to products?
--   products        = the catalogue entry (one per product)
--   product_items   = ONE ROW PER PHYSICAL UNIT, each with its own QR token
--
--   Coca-Cola (product)  ->  item A001, item A002, item A003 ...
--
-- Stock invariants that must keep holding:
--   products.stock (Business Stock)
--     = Outside Stock + SUM(fridge_stock.quantity)
--   Every in_stock item has exactly ONE location: 'outside' or one fridge.
--   Only receiving increases products.stock; only a committed sale decreases
--   it; fridge transfers move items between locations without touching it.
--
-- Everything that changes stock OR an item's location is a single Postgres
-- function = a single transaction, with SELECT ... FOR UPDATE row locks.
-- The Laravel API is the only caller and passes the business/branch/user it
-- verified from the JWT; every function re-checks ownership anyway.
-- ============================================================================

begin;

-- ---------------------------------------------------------------------------
-- 1. Receiving sessions
--    A session groups the items an administrator scans for ONE product before
--    committing them. Items stay 'pending' (they are NOT stock yet) until
--    qr_complete_receiving() commits them in one atomic receipt. Cancelling a
--    session deletes its pending rows, so no half-received stock can exist.
-- ---------------------------------------------------------------------------
create table if not exists public.scanner_receiving_sessions (
    id                 uuid primary key default gen_random_uuid(),
    business_id        uuid not null references public.businesses (id) on delete cascade,
    branch_id          uuid references public.branches (id) on delete set null,
    product_id         uuid not null references public.products (id) on delete restrict,
    status             text not null default 'open',
    scanned_count      integer not null default 0,
    completed_reference text,
    completed_at       timestamptz,
    cancelled_at       timestamptz,
    created_by         uuid references public.users (id) on delete set null,
    created_at         timestamptz not null default now(),
    updated_at         timestamptz not null default now(),
    constraint scanner_sessions_status_check check (status in ('open', 'completed', 'cancelled')),
    constraint scanner_sessions_count_check check (scanned_count >= 0)
);

create unique index if not exists scanner_sessions_one_open_per_scope
    on public.scanner_receiving_sessions (
        business_id,
        coalesce(branch_id, '00000000-0000-0000-0000-000000000000'::uuid),
        product_id,
        coalesce(created_by, '00000000-0000-0000-0000-000000000000'::uuid)
    )
    where status = 'open';

create index if not exists scanner_sessions_scope_idx
    on public.scanner_receiving_sessions (business_id, branch_id, status, created_at desc);

-- ---------------------------------------------------------------------------
-- 2. Physical items (one row per unit; one unique QR token per row)
-- ---------------------------------------------------------------------------
create table if not exists public.product_items (
    id                   uuid primary key default gen_random_uuid(),
    business_id          uuid not null references public.businesses (id) on delete cascade,
    branch_id            uuid references public.branches (id) on delete set null,
    product_id           uuid not null references public.products (id) on delete restrict,
    qr_token             text not null,
    status               text not null default 'pending',
    location             text,
    fridge_id            uuid references public.fridges (id) on delete restrict,
    receiving_session_id uuid references public.scanner_receiving_sessions (id) on delete set null,
    receipt_reference    text,
    sale_id              uuid references public.sales (id) on delete set null,
    received_at          timestamptz,
    sold_at              timestamptz,
    created_by           uuid references public.users (id) on delete set null,
    created_at           timestamptz not null default now(),
    updated_at           timestamptz not null default now(),
    constraint product_items_status_check check (
        status in ('pending', 'in_stock', 'reserved', 'sold', 'void')
    ),
    constraint product_items_location_check check (
        location is null or location in ('outside', 'fridge')
    ),
    -- A Fridge location ALWAYS has a fridge; Outside never does.
    constraint product_items_fridge_pair_check check (
        (location = 'fridge') = (fridge_id is not null)
    ),
    constraint product_items_token_not_blank check (btrim(qr_token) <> ''),
    constraint product_items_sold_has_sale check (status <> 'sold' or sale_id is not null)
);

-- A QR code must never be registered against two physical items. The index is
-- on the normalised token so " A001 " and "a001" are the same physical item.
create unique index if not exists product_items_qr_token_unique
    on public.product_items (lower(btrim(qr_token)));

create index if not exists product_items_product_idx
    on public.product_items (business_id, product_id, status);
create index if not exists product_items_branch_idx
    on public.product_items (business_id, branch_id, status);
create index if not exists product_items_fridge_idx
    on public.product_items (fridge_id, status);
create index if not exists product_items_sale_idx
    on public.product_items (sale_id)
    where sale_id is not null;
create index if not exists product_items_session_idx
    on public.product_items (receiving_session_id);

-- ---------------------------------------------------------------------------
-- 3. Append-only item history (every lifecycle transition is traceable)
-- ---------------------------------------------------------------------------
create table if not exists public.product_item_events (
    id              uuid primary key default gen_random_uuid(),
    business_id     uuid not null,
    branch_id       uuid,
    product_id      uuid not null,
    product_item_id uuid not null references public.product_items (id) on delete cascade,
    event_type      text not null,
    from_status     text,
    to_status       text,
    from_location   text,
    to_location     text,
    fridge_id       uuid,
    reference_type  text,
    reference_id    uuid,
    actor_id        uuid,
    notes           text,
    created_at      timestamptz not null default now(),
    constraint product_item_events_type_check check (event_type in (
        'received', 'receiving_cancelled', 'reserved', 'released',
        'sold', 'transfer_in', 'transfer_out', 'adjustment'
    ))
);

create index if not exists product_item_events_item_idx
    on public.product_item_events (product_item_id, created_at desc);
create index if not exists product_item_events_scope_idx
    on public.product_item_events (business_id, created_at desc);

-- ---------------------------------------------------------------------------
-- 4. Temporary cart reservations
--    Scanning an item into a sale cart does NOT move stock. It only claims the
--    physical unit for one client (client_ref) so the same unit cannot be
--    added to two carts. The claim expires, or is released when the cart is
--    abandoned, or is consumed when the sale commits.
-- ---------------------------------------------------------------------------
create table if not exists public.product_item_reservations (
    id              uuid primary key default gen_random_uuid(),
    product_item_id uuid not null references public.product_items (id) on delete cascade,
    business_id     uuid not null,
    branch_id       uuid,
    product_id      uuid not null references public.products (id) on delete restrict,
    user_id         uuid,
    client_ref      text not null,
    kind            text not null default 'sale',
    expires_at      timestamptz not null,
    released_at     timestamptz,
    release_reason  text,
    sale_id         uuid,
    created_at      timestamptz not null default now(),
    constraint product_item_reservations_kind_check check (kind in ('sale')),
    constraint product_item_reservations_client_check check (btrim(client_ref) <> '')
);

-- Only ONE active claim per physical item.
create unique index if not exists product_item_reservations_active_unique
    on public.product_item_reservations (product_item_id)
    where released_at is null;

create index if not exists product_item_reservations_client_idx
    on public.product_item_reservations (client_ref, released_at);

-- ---------------------------------------------------------------------------
-- 5. Housekeeping: release expired claims and put their items back on the
--    shelf. Safe to call at the start of every scan/commit.
-- ---------------------------------------------------------------------------
create or replace function public.qr_expire_reservations()
returns void
language plpgsql
security definer
set search_path = public
as $$
begin
    update public.product_item_reservations r
       set released_at = now(),
           release_reason = 'expired'
     where r.released_at is null
       and r.expires_at < now();

    update public.product_items i
       set status = 'in_stock',
           updated_at = now()
     where i.status = 'reserved'
       and not exists (
            select 1
              from public.product_item_reservations r
             where r.product_item_id = i.id
               and r.released_at is null
       );
end;
$$;

-- ---------------------------------------------------------------------------
-- 6. Small helper: append one lifecycle event for an existing item row.
-- ---------------------------------------------------------------------------
create or replace function public.qr_log_item_event(
    p_item_id uuid,
    p_event_type text,
    p_from_status text,
    p_to_status text,
    p_from_location text,
    p_to_location text,
    p_fridge_id uuid,
    p_reference_type text,
    p_reference_id uuid,
    p_actor uuid,
    p_notes text
) returns void
language plpgsql
security definer
set search_path = public
as $$
begin
    insert into public.product_item_events (
        business_id, branch_id, product_id, product_item_id, event_type,
        from_status, to_status, from_location, to_location, fridge_id,
        reference_type, reference_id, actor_id, notes
    )
    select i.business_id, i.branch_id, i.product_id, i.id, p_event_type,
           p_from_status, p_to_status, p_from_location, p_to_location, p_fridge_id,
           p_reference_type, p_reference_id, p_actor, p_notes
      from public.product_items i
     where i.id = p_item_id;
end;
$$;

-- ---------------------------------------------------------------------------
-- 7. Open (or resume) a receiving session. Administrator only.
-- ---------------------------------------------------------------------------
create or replace function public.qr_open_receiving_session(
    p_business_id uuid,
    p_branch_id uuid,
    p_product_id uuid,
    p_user_id uuid
) returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_business record;
    v_product record;
    v_session record;
begin
    select * into v_business from public.businesses where id = p_business_id;
    if not found then
        raise exception 'Biashara haipatikani';
    end if;

    -- Only the administrator of THIS business introduces new stock.
    if not exists (
        select 1
          from public.users u
         where u.id = p_user_id
           and u.status = 'approved'
           and u.role in ('admin', 'super_admin', 'system_admin')
           and (
                u.business_id = p_business_id
                or lower(btrim(coalesce(u.business_name, ''))) =
                   lower(btrim(coalesce(v_business.business_name, '')))
           )
    ) then
        raise exception 'Huna ruhusa ya kupokea stock kwenye biashara hii'
            using errcode = '42501';
    end if;

    select p.* into v_product
      from public.products p
      join public.users seller on seller.id = p.seller_id
     where p.id = p_product_id
       and p.is_active = true
       and seller.status = 'approved'
       and (
            seller.business_id = p_business_id
            or lower(btrim(coalesce(seller.business_name, ''))) =
               lower(btrim(coalesce(v_business.business_name, '')))
       );
    if not found then
        raise exception 'Bidhaa haipatikani kwenye biashara hii';
    end if;

    if p_branch_id is not null and v_product.branch_id is distinct from p_branch_id then
        raise exception 'Bidhaa ni ya tawi lingine';
    end if;
    if p_branch_id is null and v_product.branch_id is not null then
        raise exception 'Bidhaa ni ya tawi; chagua tawi lake kabla ya kupokea stock';
    end if;

    -- Resume the caller's own open session instead of creating a second one.
    select * into v_session
      from public.scanner_receiving_sessions
     where business_id = p_business_id
       and branch_id is not distinct from p_branch_id
       and product_id = p_product_id
       and created_by = p_user_id
       and status = 'open'
     limit 1;

    if found then
        return jsonb_build_object(
            'success', true,
            'session', to_jsonb(v_session),
            'resumed', true
        );
    end if;

    insert into public.scanner_receiving_sessions (
        business_id, branch_id, product_id, status, created_by
    ) values (
        p_business_id, p_branch_id, p_product_id, 'open', p_user_id
    )
    returning * into v_session;

    return jsonb_build_object(
        'success', true,
        'session', to_jsonb(v_session),
        'resumed', false
    );
end;
$$;

-- ---------------------------------------------------------------------------
-- 8. Register ONE scanned physical item into an open session.
--    The item is 'pending': it is not stock until the session completes.
-- ---------------------------------------------------------------------------
create or replace function public.qr_receive_item(
    p_session_id uuid,
    p_qr_token text,
    p_business_id uuid,
    p_branch_id uuid,
    p_product_id uuid,
    p_user_id uuid
) returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_session record;
    v_token text := btrim(coalesce(p_qr_token, ''));
    v_item record;
    v_count integer;
begin
    if length(v_token) < 4 then
        raise exception 'QR code ni fupi mno';
    end if;
    if length(v_token) > 200 then
        raise exception 'QR code ni ndefu mno';
    end if;

    select * into v_session
      from public.scanner_receiving_sessions
     where id = p_session_id
     for update;

    if not found then
        raise exception 'Kipindi cha kupokea stock hakipo';
    end if;
    if v_session.business_id <> p_business_id
       or v_session.branch_id is distinct from p_branch_id
       or v_session.product_id <> p_product_id then
        raise exception 'Kipindi hiki si cha bidhaa/tawi hili';
    end if;
    if v_session.created_by is not null and v_session.created_by <> p_user_id then
        raise exception 'Kipindi hiki ni cha mtumiaji mwingine';
    end if;
    if v_session.status <> 'open' then
        raise exception 'Kipindi cha kupokea stock kimeshafungwa';
    end if;

    begin
        insert into public.product_items (
            business_id, branch_id, product_id, qr_token, status,
            receiving_session_id, created_by
        ) values (
            p_business_id, p_branch_id, p_product_id, v_token, 'pending',
            p_session_id, p_user_id
        )
        returning * into v_item;
    exception
        when unique_violation then
            raise exception 'QR hii tayari imesajiliwa kwenye mfumo. Haiwezi kutumika kwa kipande kingine.';
    end;

    update public.scanner_receiving_sessions
       set scanned_count = scanned_count + 1,
           updated_at = now()
     where id = p_session_id
    returning scanned_count into v_count;

    return jsonb_build_object(
        'success', true,
        'item', to_jsonb(v_item),
        'scanned_count', v_count
    );
end;
$$;

-- ---------------------------------------------------------------------------
-- 9. Commit a receiving session: one atomic receipt for N items.
--    Calls the existing receive_product_stock() so business stock can only be
--    increased through the authorised, logged path.
-- ---------------------------------------------------------------------------
create or replace function public.qr_complete_receiving(
    p_session_id uuid,
    p_business_id uuid,
    p_branch_id uuid,
    p_user_id uuid
) returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_session record;
    v_count integer;
    v_receipt jsonb;
    v_reference text;
begin
    select * into v_session
      from public.scanner_receiving_sessions
     where id = p_session_id
     for update;

    if not found then
        raise exception 'Kipindi cha kupokea stock hakipo';
    end if;
    if v_session.business_id <> p_business_id
       or v_session.branch_id is distinct from p_branch_id then
        raise exception 'Kipindi hiki si cha biashara/tawi hili';
    end if;
    if v_session.status = 'completed' then
        raise exception 'Kipindi hiki kimeshakamilika';
    end if;
    if v_session.status = 'cancelled' then
        raise exception 'Kipindi hiki kimeghairiwa';
    end if;

    select count(*) into v_count
      from public.product_items
     where receiving_session_id = p_session_id
       and status = 'pending';

    if v_count = 0 then
        raise exception 'Hakuna kipande kilichoskaniwa kwenye kipindi hiki';
    end if;

    v_reference := 'QR-' || to_char(now(), 'YYYYMMDD-HH24MISS') || '-'
        || upper(substr(replace(p_session_id::text, '-', ''), 1, 8));

    -- Same transaction: locks the product, increments products.stock, writes
    -- the append-only stock_receipts row, and re-checks the admin role.
    v_receipt := public.receive_product_stock(
        v_session.product_id,
        p_business_id,
        p_branch_id,
        v_count,
        p_user_id,
        'scanner',
        v_reference,
        'QR receiving session ' || p_session_id::text
    );

    update public.product_items
       set status = 'in_stock',
           location = 'outside',
           fridge_id = null,
           received_at = now(),
           receipt_reference = v_reference,
           updated_at = now()
     where receiving_session_id = p_session_id
       and status = 'pending';

    insert into public.product_item_events (
        business_id, branch_id, product_id, product_item_id, event_type,
        from_status, to_status, from_location, to_location,
        reference_type, reference_id, actor_id, notes
    )
    select i.business_id, i.branch_id, i.product_id, i.id, 'received',
           'pending', 'in_stock', null, 'outside',
           'stock_receipt', p_session_id, p_user_id, v_reference
      from public.product_items i
     where i.receiving_session_id = p_session_id;

    update public.scanner_receiving_sessions
       set status = 'completed',
           scanned_count = v_count,
           completed_reference = v_reference,
           completed_at = now(),
           updated_at = now()
     where id = p_session_id;

    return jsonb_build_object(
        'success', true,
        'session_id', p_session_id,
        'quantity', v_count,
        'receiving_reference', v_reference,
        'stock_before', (v_receipt->>'stock_before')::numeric,
        'stock_after', (v_receipt->>'stock_after')::numeric
    );
end;
$$;

-- ---------------------------------------------------------------------------
-- 10. Cancel a receiving session. Pending rows are deleted, so nothing was
--     ever added to stock and no orphaned item can be sold or transferred.
-- ---------------------------------------------------------------------------
create or replace function public.qr_cancel_receiving(
    p_session_id uuid,
    p_business_id uuid,
    p_user_id uuid
) returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_session record;
    v_removed integer;
begin
    select * into v_session
      from public.scanner_receiving_sessions
     where id = p_session_id
     for update;

    if not found then
        raise exception 'Kipindi cha kupokea stock hakipo';
    end if;
    if v_session.business_id <> p_business_id then
        raise exception 'Kipindi hiki si cha biashara hii';
    end if;
    if v_session.status = 'completed' then
        raise exception 'Kipindi hiki kimeshakamilika; stock iliyopokelewa haiwezi kufutwa kwa kughairi';
    end if;
    if v_session.status = 'cancelled' then
        return jsonb_build_object('success', true, 'removed', 0, 'already_cancelled', true);
    end if;

    delete from public.product_items
     where receiving_session_id = p_session_id
       and status = 'pending';
    get diagnostics v_removed = row_count;

    update public.scanner_receiving_sessions
       set status = 'cancelled',
           cancelled_at = now(),
           updated_at = now()
     where id = p_session_id;

    return jsonb_build_object(
        'success', true,
        'session_id', p_session_id,
        'removed', v_removed
    );
end;
$$;

-- ---------------------------------------------------------------------------
-- 11. SELLING: claim one physical item for this cart.
--     No stock moves here. The claim stops the same unit being sold twice.
-- ---------------------------------------------------------------------------
create or replace function public.qr_reserve_item(
    p_qr_token text,
    p_product_id uuid,
    p_business_id uuid,
    p_branch_id uuid,
    p_user_id uuid,
    p_client_ref text,
    p_ttl_minutes integer default 30
) returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_item record;
    v_product record;
    v_reservation record;
    v_existing record;
    v_token text := btrim(coalesce(p_qr_token, ''));
    v_ttl integer := least(greatest(coalesce(p_ttl_minutes, 30), 1), 240);
    v_expires timestamptz;
begin
    if btrim(coalesce(p_client_ref, '')) = '' then
        raise exception 'Kumbukumbu ya mauzo inahitajika kabla ya kuskani';
    end if;
    if v_token = '' then
        raise exception 'QR code inahitajika';
    end if;

    perform public.qr_expire_reservations();

    select * into v_item
      from public.product_items
     where lower(btrim(qr_token)) = lower(v_token)
     for update;

    if not found then
        raise exception 'QR hii haijasajiliwa kwenye mfumo';
    end if;

    if v_item.business_id <> p_business_id then
        raise exception 'Bidhaa hii ni ya biashara nyingine';
    end if;
    if p_branch_id is not null and v_item.branch_id is distinct from p_branch_id then
        raise exception 'Bidhaa hii ni ya tawi lingine';
    end if;
    if p_branch_id is null and v_item.branch_id is not null then
        raise exception 'Bidhaa hii ni ya tawi; chagua tawi kabla ya kuuza';
    end if;

    if v_item.product_id <> p_product_id then
        select * into v_product from public.products where id = v_item.product_id;
        raise exception 'Kipande hiki ni cha bidhaa tofauti (%)', coalesce(v_product.name, 'isiyojulikana');
    end if;

    if v_item.status = 'sold' then
        raise exception 'Kipande hiki kimeshauzwa';
    end if;
    if v_item.status = 'pending' then
        raise exception 'Kipande hiki hakijakamilika kupokelewa';
    end if;
    if v_item.status = 'void' then
        raise exception 'Kipande hiki kimeondolewa kwenye mfumo';
    end if;

    if v_item.status = 'reserved' then
        select * into v_existing
          from public.product_item_reservations
         where product_item_id = v_item.id
           and released_at is null
         limit 1;

        if v_existing.id is not null then
            if v_existing.client_ref = p_client_ref then
                raise exception 'Kipande hiki tayari kiko kwenye mauzo haya';
            end if;
            raise exception 'Kipande hiki kimehifadhiwa kwenye mauzo mengine. Jaribu tena baada ya muda.';
        end if;
    end if;

    if v_item.status <> 'in_stock' then
        raise exception 'Kipande hiki hakipatikani kwa mauzo';
    end if;

    v_expires := now() + make_interval(mins => v_ttl);

    insert into public.product_item_reservations (
        product_item_id, business_id, branch_id, product_id,
        user_id, client_ref, kind, expires_at
    ) values (
        v_item.id, v_item.business_id, v_item.branch_id, v_item.product_id,
        p_user_id, p_client_ref, 'sale', v_expires
    )
    returning * into v_reservation;

    update public.product_items
       set status = 'reserved',
           updated_at = now()
     where id = v_item.id;

    perform public.qr_log_item_event(
        v_item.id, 'reserved', 'in_stock', 'reserved',
        v_item.location, v_item.location, v_item.fridge_id,
        'sale_cart', null, p_user_id, p_client_ref
    );

    return jsonb_build_object(
        'success', true,
        'item_id', v_item.id,
        'product_id', v_item.product_id,
        'qr_token', v_item.qr_token,
        'location', v_item.location,
        'fridge_id', v_item.fridge_id,
        'reservation_id', v_reservation.id,
        'expires_at', v_expires
    );
end;
$$;

-- ---------------------------------------------------------------------------
-- 12. Release the claims of an abandoned cart.
-- ---------------------------------------------------------------------------
create or replace function public.qr_release_sale_items(
    p_item_ids uuid[],
    p_business_id uuid,
    p_user_id uuid,
    p_client_ref text
) returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_released integer := 0;
    v_rows integer;
    v_item_id uuid;
begin
    if p_item_ids is null or array_length(p_item_ids, 1) is null then
        return jsonb_build_object('success', true, 'released', 0);
    end if;

    foreach v_item_id in array p_item_ids loop
        update public.product_item_reservations
           set released_at = now(),
               release_reason = 'cart_released'
         where product_item_id = v_item_id
           and released_at is null
           and business_id = p_business_id
           and (btrim(coalesce(p_client_ref, '')) = '' or client_ref = p_client_ref);
        get diagnostics v_rows = row_count;
        v_released := v_released + v_rows;

        update public.product_items
           set status = 'in_stock',
               updated_at = now()
         where id = v_item_id
           and business_id = p_business_id
           and status = 'reserved'
           and not exists (
                select 1 from public.product_item_reservations r
                 where r.product_item_id = product_items.id
                   and r.released_at is null
           );

        if found then
            perform public.qr_log_item_event(
                v_item_id, 'released', 'reserved', 'in_stock',
                null, null, null, 'sale_cart', null, p_user_id, 'cart released'
            );
        end if;
    end loop;

    return jsonb_build_object('success', true, 'released', v_released);
end;
$$;

-- ---------------------------------------------------------------------------
-- 13. COMMIT A SALE THAT CONTAINS SCANNED PHYSICAL ITEMS.
--     One transaction: validate + lock items, derive each product's stock
--     allocation from where the items physically are, reuse create_pos_sale()
--     to write the sale/stock/customer rows, then mark the items sold.
-- ---------------------------------------------------------------------------
create or replace function public.qr_commit_sale(
    p_sale_id uuid,
    p_seller_id uuid,
    p_business_id uuid,
    p_branch_id uuid,
    p_invoice_number text,
    p_customer_id uuid,
    p_sale_date date,
    p_payment_method text,
    p_notes text,
    p_items jsonb,
    p_item_ids uuid[],
    p_client_ref text
) returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_sale_id uuid := coalesce(p_sale_id, gen_random_uuid());
    v_item record;
    v_locked integer := 0;
    v_expected integer;
    v_scanned_products uuid[];
    v_product_id uuid;
    v_line_qty numeric;
    v_line_count integer;
    v_unit_price numeric;
    v_allocations jsonb;
    v_rpc_items jsonb := '[]'::jsonb;
    v_line jsonb;
    v_reserved_by_other boolean;
    v_result jsonb;
begin
    if p_items is null or jsonb_typeof(p_items) <> 'array' or jsonb_array_length(p_items) = 0 then
        raise exception 'Bidhaa za kuuza zinahitajika';
    end if;
    if p_item_ids is null or array_length(p_item_ids, 1) is null then
        raise exception 'Hakuna vipande vilivyoskaniwa kwenye mauzo haya';
    end if;

    perform public.qr_expire_reservations();

    if exists (select 1 from public.sales where id = v_sale_id) then
        raise exception 'Mauzo haya yameshahifadhiwa tayari';
    end if;

    -- Lock every physical item in a deterministic order.
    for v_item in
        select *
          from public.product_items
         where id = any (p_item_ids)
         order by id
         for update
    loop
        v_locked := v_locked + 1;

        if v_item.business_id <> p_business_id then
            raise exception 'Kipande kimoja ni cha biashara nyingine';
        end if;
        if p_branch_id is not null and v_item.branch_id is distinct from p_branch_id then
            raise exception 'Kipande kimoja ni cha tawi lingine';
        end if;
        if p_branch_id is null and v_item.branch_id is not null then
            raise exception 'Kipande kimoja ni cha tawi; chagua tawi kabla ya kuuza';
        end if;
        if v_item.status = 'sold' then
            raise exception 'Kipande kimoja kimeshauzwa tayari';
        end if;
        if v_item.status not in ('in_stock', 'reserved') then
            raise exception 'Kipande kimoja hakipatikani kwa mauzo';
        end if;

        if v_item.status = 'reserved' then
            select exists (
                select 1
                  from public.product_item_reservations r
                 where r.product_item_id = v_item.id
                   and r.released_at is null
                   and btrim(coalesce(p_client_ref, '')) <> ''
                   and r.client_ref <> p_client_ref
            ) into v_reserved_by_other;
            if v_reserved_by_other then
                raise exception 'Kipande kimoja kimehifadhiwa kwenye mauzo mengine';
            end if;
        end if;
    end loop;

    v_expected := array_length(p_item_ids, 1);
    if v_locked <> v_expected then
        raise exception 'Baadhi ya vipande vilivyoskaniwa havipo kwenye mfumo';
    end if;

    -- Refuse duplicate ids in the payload (the same couple counted twice).
    if (select count(distinct x) from unnest(p_item_ids) as x) <> v_expected then
        raise exception 'Kipande kimoja kimeorodheshwa zaidi ya mara moja';
    end if;

    select array_agg(distinct product_id) into v_scanned_products
      from public.product_items
     where id = any (p_item_ids);

    foreach v_product_id in array v_scanned_products loop
        -- Scanned products must appear on exactly ONE sale line: quantity per
        -- product is derived from the physical items, never from the client.
        select count(*), coalesce(sum((x->>'quantity')::numeric), 0),
               max((x->>'unit_price')::numeric)
          into v_line_count, v_line_qty, v_unit_price
          from jsonb_array_elements(p_items) x
         where (x->>'product_id')::uuid = v_product_id;

        if v_line_count <> 1 then
            raise exception 'Bidhaa moja iliyoskaniwa lazima iwe kwenye mstari mmoja wa mauzo';
        end if;
        if v_line_qty <> (select count(*) from public.product_items where id = any (p_item_ids) and product_id = v_product_id) then
            raise exception 'Kiasi cha bidhaa hakiendani na vipande vilivyoskaniwa';
        end if;

        -- Where are those physical units right now? That decides which stock
        -- location is deducted (outside vs a specific fridge).
        select coalesce(jsonb_agg(jsonb_build_object(
                   'fridge_id', s.fridge_id,
                   'quantity', s.qty
               )), '[]'::jsonb)
          into v_allocations
          from (
                select i.fridge_id, count(*)::numeric as qty
                  from public.product_items i
                 where i.id = any (p_item_ids)
                   and i.product_id = v_product_id
                 group by i.fridge_id
          ) s;

        v_rpc_items := v_rpc_items || jsonb_build_array(jsonb_build_object(
            'product_id', v_product_id,
            'quantity', v_line_qty,
            'unit_price', v_unit_price,
            'allocations', v_allocations
        ));
    end loop;

    -- Products sold without scanning (manual quantity) pass straight through.
    for v_line in
        select x
          from jsonb_array_elements(p_items) x
         where (x->>'product_id')::uuid <> all (v_scanned_products)
    loop
        v_rpc_items := v_rpc_items || jsonb_build_array(v_line);
    end loop;

    -- Existing atomic sale writer: locks products, validates stock, writes
    -- sales + sale_items + allocations + fridge moves + customer totals.
    v_result := public.create_pos_sale(
        v_sale_id, p_seller_id, p_business_id, p_branch_id, p_invoice_number,
        p_customer_id, p_sale_date, p_payment_method, p_notes, v_rpc_items
    );

    update public.product_items
       set status = 'sold',
           sale_id = v_sale_id,
           sold_at = now(),
           updated_at = now()
     where id = any (p_item_ids);

    update public.product_item_reservations
       set released_at = now(),
           release_reason = 'sold',
           sale_id = v_sale_id
     where product_item_id = any (p_item_ids)
       and released_at is null;

    insert into public.product_item_events (
        business_id, branch_id, product_id, product_item_id, event_type,
        from_status, to_status, from_location, to_location, fridge_id,
        reference_type, reference_id, actor_id
    )
    select i.business_id, i.branch_id, i.product_id, i.id, 'sold',
           'reserved', 'sold', i.location, i.location, i.fridge_id,
           'sale', v_sale_id, p_seller_id
      from public.product_items i
     where i.id = any (p_item_ids);

    return v_result || jsonb_build_object(
        'items_sold', v_expected,
        'sale_id', v_sale_id
    );
end;
$$;

-- ---------------------------------------------------------------------------
-- 14. FRIDGE TRANSFER IN: move ONE existing outside item into a fridge.
--     Business stock is unchanged; only the location moves.
-- ---------------------------------------------------------------------------
create or replace function public.qr_move_item_to_fridge(
    p_qr_token text,
    p_fridge_id uuid,
    p_product_id uuid,
    p_business_id uuid,
    p_branch_id uuid,
    p_user_id uuid
) returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_item record;
    v_product record;
    v_result jsonb;
    v_token text := btrim(coalesce(p_qr_token, ''));
begin
    if v_token = '' then
        raise exception 'QR code inahitajika';
    end if;

    perform public.qr_expire_reservations();

    select * into v_item
      from public.product_items
     where lower(btrim(qr_token)) = lower(v_token)
     for update;
    if not found then
        raise exception 'QR hii haijasajiliwa kwenye mfumo';
    end if;

    if v_item.business_id <> p_business_id then
        raise exception 'Kipande hiki ni cha biashara nyingine';
    end if;
    if p_branch_id is not null and v_item.branch_id is distinct from p_branch_id then
        raise exception 'Kipande hiki ni cha tawi lingine';
    end if;
    if v_item.product_id <> p_product_id then
        select * into v_product from public.products where id = v_item.product_id;
        raise exception 'Kipande hiki ni cha bidhaa tofauti (%)', coalesce(v_product.name, 'isiyojulikana');
    end if;
    if v_item.status = 'sold' then
        raise exception 'Kipande hiki kimeshauzwa';
    end if;
    if v_item.status = 'reserved' then
        raise exception 'Kipande hiki kimehifadhiwa kwenye mauzo yanayoendelea';
    end if;
    if v_item.status <> 'in_stock' then
        raise exception 'Kipande hiki hakipatikani';
    end if;
    if v_item.location = 'fridge' then
        raise exception 'Kipande hiki kiko kwenye friji tayari';
    end if;

    v_result := public.fridge_transfer_in(
        p_fridge_id, p_product_id, 1, p_business_id, p_branch_id, p_user_id, 0,
        'QR transfer in'
    );

    update public.product_items
       set location = 'fridge',
           fridge_id = p_fridge_id,
           updated_at = now()
     where id = v_item.id;

    perform public.qr_log_item_event(
        v_item.id, 'transfer_in', 'in_stock', 'in_stock',
        'outside', 'fridge', p_fridge_id, 'fridge', p_fridge_id, p_user_id, null
    );

    return v_result || jsonb_build_object(
        'item_id', v_item.id,
        'qr_token', v_item.qr_token,
        'location', 'fridge',
        'fridge_id', p_fridge_id
    );
end;
$$;

-- ---------------------------------------------------------------------------
-- 15. FRIDGE TRANSFER OUT: move ONE item from a fridge back to Outside.
-- ---------------------------------------------------------------------------
create or replace function public.qr_move_item_out_of_fridge(
    p_qr_token text,
    p_fridge_id uuid,
    p_product_id uuid,
    p_business_id uuid,
    p_branch_id uuid,
    p_user_id uuid
) returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_item record;
    v_product record;
    v_result jsonb;
    v_token text := btrim(coalesce(p_qr_token, ''));
begin
    if v_token = '' then
        raise exception 'QR code inahitajika';
    end if;

    perform public.qr_expire_reservations();

    select * into v_item
      from public.product_items
     where lower(btrim(qr_token)) = lower(v_token)
     for update;
    if not found then
        raise exception 'QR hii haijasajiliwa kwenye mfumo';
    end if;

    if v_item.business_id <> p_business_id then
        raise exception 'Kipande hiki ni cha biashara nyingine';
    end if;
    if p_branch_id is not null and v_item.branch_id is distinct from p_branch_id then
        raise exception 'Kipande hiki ni cha tawi lingine';
    end if;
    if v_item.product_id <> p_product_id then
        select * into v_product from public.products where id = v_item.product_id;
        raise exception 'Kipande hiki ni cha bidhaa tofauti (%)', coalesce(v_product.name, 'isiyojulikana');
    end if;
    if v_item.status = 'sold' then
        raise exception 'Kipande hiki kimeshauzwa';
    end if;
    if v_item.status = 'reserved' then
        raise exception 'Kipande hiki kimehifadhiwa kwenye mauzo yanayoendelea';
    end if;
    if v_item.status <> 'in_stock' then
        raise exception 'Kipande hiki hakipatikani';
    end if;
    if v_item.location <> 'fridge' or v_item.fridge_id is distinct from p_fridge_id then
        raise exception 'Kipande hiki hakiko kwenye friji uliyochagua';
    end if;

    v_result := public.fridge_transfer_out(
        p_fridge_id, p_product_id, 1, p_business_id, p_branch_id, p_user_id,
        'QR transfer out'
    );

    update public.product_items
       set location = 'outside',
           fridge_id = null,
           updated_at = now()
     where id = v_item.id;

    perform public.qr_log_item_event(
        v_item.id, 'transfer_out', 'in_stock', 'in_stock',
        'fridge', 'outside', p_fridge_id, 'fridge', p_fridge_id, p_user_id, null
    );

    return v_result || jsonb_build_object(
        'item_id', v_item.id,
        'qr_token', v_item.qr_token,
        'location', 'outside',
        'fridge_id', null
    );
end;
$$;

-- ---------------------------------------------------------------------------
-- 16. Deploy-time reconciliation helpers (read-only for the app, useful for
--     verifying that per-item tracking agrees with the stock ledger).
-- ---------------------------------------------------------------------------
create or replace function public.qr_item_stock_summary(p_business_id uuid)
returns table (
    product_id uuid,
    product_name text,
    business_stock numeric,
    outside_items bigint,
    fridge_items bigint,
    sold_items bigint
)
language sql
security definer
set search_path = public
as $$
    select p.id,
           p.name,
           coalesce(p.stock, 0)::numeric,
           count(*) filter (where i.status in ('in_stock', 'reserved') and i.location = 'outside'),
           count(*) filter (where i.status in ('in_stock', 'reserved') and i.location = 'fridge'),
           count(*) filter (where i.status = 'sold')
      from public.products p
      left join public.product_items i on i.product_id = p.id
     where p.id in (
            select pr.id
              from public.products pr
              join public.users seller on seller.id = pr.seller_id
             where seller.business_id = p_business_id
     )
     group by p.id, p.name, p.stock;
$$;

-- ---------------------------------------------------------------------------
-- 17. Access control: service_role only (the Laravel server). The browser and
--     the mobile app never talk to these functions directly.
-- ---------------------------------------------------------------------------
revoke all on function public.qr_expire_reservations() from public, anon, authenticated;
revoke all on function public.qr_log_item_event(uuid, text, text, text, text, text, uuid, text, uuid, uuid, text) from public, anon, authenticated;
revoke all on function public.qr_open_receiving_session(uuid, uuid, uuid, uuid) from public, anon, authenticated;
revoke all on function public.qr_receive_item(uuid, text, uuid, uuid, uuid, uuid) from public, anon, authenticated;
revoke all on function public.qr_complete_receiving(uuid, uuid, uuid, uuid) from public, anon, authenticated;
revoke all on function public.qr_cancel_receiving(uuid, uuid, uuid) from public, anon, authenticated;
revoke all on function public.qr_reserve_item(text, uuid, uuid, uuid, uuid, text, integer) from public, anon, authenticated;
revoke all on function public.qr_release_sale_items(uuid[], uuid, uuid, text) from public, anon, authenticated;
revoke all on function public.qr_commit_sale(uuid, uuid, uuid, uuid, text, uuid, date, text, text, jsonb, uuid[], text) from public, anon, authenticated;
revoke all on function public.qr_move_item_to_fridge(text, uuid, uuid, uuid, uuid, uuid) from public, anon, authenticated;
revoke all on function public.qr_move_item_out_of_fridge(text, uuid, uuid, uuid, uuid, uuid) from public, anon, authenticated;
revoke all on function public.qr_item_stock_summary(uuid) from public, anon, authenticated;

grant execute on function public.qr_expire_reservations() to service_role;
grant execute on function public.qr_log_item_event(uuid, text, text, text, text, text, uuid, text, uuid, uuid, text) to service_role;
grant execute on function public.qr_open_receiving_session(uuid, uuid, uuid, uuid) to service_role;
grant execute on function public.qr_receive_item(uuid, text, uuid, uuid, uuid, uuid) to service_role;
grant execute on function public.qr_complete_receiving(uuid, uuid, uuid, uuid) to service_role;
grant execute on function public.qr_cancel_receiving(uuid, uuid, uuid) to service_role;
grant execute on function public.qr_reserve_item(text, uuid, uuid, uuid, uuid, text, integer) to service_role;
grant execute on function public.qr_release_sale_items(uuid[], uuid, uuid, text) to service_role;
grant execute on function public.qr_commit_sale(uuid, uuid, uuid, uuid, text, uuid, date, text, text, jsonb, uuid[], text) to service_role;
grant execute on function public.qr_move_item_to_fridge(text, uuid, uuid, uuid, uuid, uuid) to service_role;
grant execute on function public.qr_move_item_out_of_fridge(text, uuid, uuid, uuid, uuid, uuid) to service_role;
grant execute on function public.qr_item_stock_summary(uuid) to service_role;

commit;
