-- ============================================================================
-- Fridge / Freezer inventory
-- ----------------------------------------------------------------------------
-- Authoritative DDL. Run this file in the Supabase SQL Editor before deploying
-- the application code. The local Laravel mirror is
-- database/migrations/2026_11_14_000001_create_fridge_inventory.php and exists
-- only so tests/CI can build an equivalent SQLite schema.
--
-- Invariant enforced by this design:
--   Business Stock (products.stock)
--     = Outside Stock + SUM(fridge_stock.quantity)
--
-- Moving stock into or out of a fridge changes ONLY fridge_stock. It NEVER
-- changes products.stock. Only a sale changes products.stock, and a sale that
-- uses inside stock also decrements the matching fridge_stock row in the same
-- database transaction.
-- ============================================================================

begin;

-- ---------------------------------------------------------------------------
-- 1. Fridge master
-- ---------------------------------------------------------------------------
create table if not exists public.fridges (
    id uuid primary key default gen_random_uuid(),
    business_id uuid not null,
    branch_id uuid,
    name text not null,
    status text not null default 'active',
    created_by uuid,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint fridges_name_not_blank check (btrim(name) <> ''),
    constraint fridges_status_valid check (status in ('active', 'archived'))
);

create unique index if not exists fridges_active_name_unique
    on public.fridges (
        business_id,
        coalesce(branch_id, '00000000-0000-0000-0000-000000000000'::uuid),
        lower(btrim(name))
    )
    where status = 'active';

create index if not exists fridges_scope_idx
    on public.fridges (business_id, branch_id, status);

-- ---------------------------------------------------------------------------
-- 2. Current stock inside each fridge (one row per fridge/product)
-- ---------------------------------------------------------------------------
create table if not exists public.fridge_stock (
    id uuid primary key default gen_random_uuid(),
    fridge_id uuid not null references public.fridges(id) on delete restrict,
    product_id uuid not null references public.products(id) on delete restrict,
    business_id uuid not null,
    branch_id uuid,
    quantity numeric(14, 3) not null default 0,
    min_stock numeric(14, 3) not null default 0,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint fridge_stock_quantity_non_negative check (quantity >= 0),
    constraint fridge_stock_min_non_negative check (min_stock >= 0),
    constraint fridge_stock_unique_product unique (fridge_id, product_id)
);

create index if not exists fridge_stock_scope_idx
    on public.fridge_stock (business_id, branch_id, product_id);

-- ---------------------------------------------------------------------------
-- 3. Immutable movement history
-- ---------------------------------------------------------------------------
create table if not exists public.fridge_stock_movements (
    id uuid primary key default gen_random_uuid(),
    business_id uuid not null,
    branch_id uuid,
    fridge_id uuid references public.fridges(id) on delete restrict,
    product_id uuid not null references public.products(id) on delete restrict,
    movement_type text not null,
    quantity numeric(14, 3) not null,
    source_location text not null,
    destination_location text not null,
    reference_type text,
    reference_id uuid,
    reason text,
    notes text,
    created_by uuid,
    created_at timestamptz not null default now(),
    constraint fridge_movement_type_valid check (
        movement_type in ('transfer_in', 'transfer_out', 'sale', 'adjustment', 'initial')
    ),
    constraint fridge_movement_quantity_positive check (quantity > 0),
    constraint fridge_movement_source_valid check (
        source_location in ('outside', 'fridge', 'sale', 'adjustment')
    ),
    constraint fridge_movement_destination_valid check (
        destination_location in ('outside', 'fridge', 'sale', 'adjustment')
    )
);

create index if not exists fridge_movements_scope_idx
    on public.fridge_stock_movements (business_id, branch_id, created_at desc);
create index if not exists fridge_movements_fridge_idx
    on public.fridge_stock_movements (fridge_id, product_id, created_at desc);
create index if not exists fridge_movements_reference_idx
    on public.fridge_stock_movements (reference_type, reference_id);

-- ---------------------------------------------------------------------------
-- 4. Which source served each sold unit
-- ---------------------------------------------------------------------------
create table if not exists public.sale_item_fridge_allocations (
    id uuid primary key default gen_random_uuid(),
    sale_id uuid not null references public.sales(id) on delete cascade,
    sale_item_id uuid,
    business_id uuid not null,
    branch_id uuid,
    product_id uuid not null references public.products(id) on delete restrict,
    fridge_id uuid references public.fridges(id) on delete set null,
    fridge_name text,
    quantity numeric(14, 3) not null,
    created_at timestamptz not null default now(),
    constraint sale_fridge_allocation_quantity_positive check (quantity > 0)
);

create index if not exists sale_fridge_allocations_sale_idx
    on public.sale_item_fridge_allocations (sale_id);
create index if not exists sale_fridge_allocations_product_idx
    on public.sale_item_fridge_allocations (business_id, product_id, created_at desc);

-- ---------------------------------------------------------------------------
-- 5. Alert history. Only one OPEN alert per scope/type/product exists.
-- ---------------------------------------------------------------------------
create table if not exists public.fridge_stock_alerts (
    id uuid primary key default gen_random_uuid(),
    business_id uuid not null,
    branch_id uuid,
    fridge_id uuid references public.fridges(id) on delete cascade,
    product_id uuid not null references public.products(id) on delete cascade,
    alert_type text not null,
    threshold numeric(14, 3),
    quantity numeric(14, 3),
    status text not null default 'open',
    resolved_at timestamptz,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint fridge_alerts_type_valid check (
        alert_type in ('business_low', 'outside_low', 'fridge_low')
    ),
    constraint fridge_alerts_status_valid check (status in ('open', 'resolved'))
);

create unique index if not exists fridge_alerts_open_unique
    on public.fridge_stock_alerts (
        business_id,
        coalesce(branch_id, '00000000-0000-0000-0000-000000000000'::uuid),
        coalesce(fridge_id, '00000000-0000-0000-0000-000000000000'::uuid),
        product_id,
        alert_type
    )
    where status = 'open';

create index if not exists fridge_alerts_scope_idx
    on public.fridge_stock_alerts (business_id, branch_id, status);

-- ---------------------------------------------------------------------------
-- 6. Expand-only product columns
--    min_stock_level already exists. Business stock stays products.stock.
-- ---------------------------------------------------------------------------
alter table public.products
    add column if not exists min_outside_stock numeric(14, 3) not null default 0;

-- ---------------------------------------------------------------------------
-- 7. Transfer stock into a fridge (Outside -> Fridge)
--    Never changes products.stock.
-- ---------------------------------------------------------------------------
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
        p_fridge_id, p_product_id, p_business_id, p_branch_id, p_quantity, greatest(coalesce(p_min_stock, 0), 0)
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

-- ---------------------------------------------------------------------------
-- 8. Return stock from a fridge (Fridge -> Outside)
-- ---------------------------------------------------------------------------
create or replace function public.fridge_transfer_out(
    p_fridge_id uuid,
    p_product_id uuid,
    p_quantity numeric,
    p_business_id uuid,
    p_branch_id uuid,
    p_user_id uuid,
    p_notes text default null
) returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_fridge record;
    v_product record;
    v_stock record;
    v_inside numeric := 0;
    v_outside numeric := 0;
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
     for update;
    if not found then
        raise exception 'Bidhaa haipatikani';
    end if;

    select * into v_stock
      from public.fridge_stock
     where fridge_id = p_fridge_id
       and product_id = p_product_id
     for update;
    if not found or v_stock.quantity < p_quantity then
        raise exception 'Stock ya friji haitoshi';
    end if;

    update public.fridge_stock
       set quantity = quantity - p_quantity,
           updated_at = now()
     where id = v_stock.id
    returning quantity into v_inside;

    select coalesce(sum(fs.quantity), 0)
      into v_outside
      from public.fridge_stock fs
      join public.fridges f on f.id = fs.fridge_id
     where fs.product_id = p_product_id
       and fs.business_id = p_business_id
       and (fs.branch_id is not distinct from p_branch_id)
       and f.status = 'active';

    insert into public.fridge_stock_movements (
        business_id, branch_id, fridge_id, product_id, movement_type, quantity,
        source_location, destination_location, reference_type, reference_id,
        reason, notes, created_by
    ) values (
        p_business_id, p_branch_id, p_fridge_id, p_product_id, 'transfer_out', p_quantity,
        'fridge', 'outside', 'fridge', p_fridge_id,
        'transfer_out', p_notes, p_user_id
    );

    return jsonb_build_object(
        'success', true,
        'fridge_id', p_fridge_id,
        'product_id', p_product_id,
        'fridge_quantity', v_inside,
        'outside_stock', coalesce(v_product.stock, 0) - v_outside,
        'business_stock', v_product.stock
    );
end;
$$;

-- ---------------------------------------------------------------------------
-- 9. Atomic POS sale with optional source allocations
--    p_items = [
--      { "product_id": uuid, "quantity": n, "unit_price": n,
--        "allocations": [ {"fridge_id": uuid|null, "quantity": n} ] }
--    ]
--    If "allocations" is absent, the whole line is taken from Outside Stock.
-- ---------------------------------------------------------------------------
create or replace function public.create_pos_sale(
    p_sale_id uuid,
    p_seller_id uuid,
    p_business_id uuid,
    p_branch_id uuid,
    p_invoice_number text,
    p_customer_id uuid,
    p_sale_date date,
    p_payment_method text,
    p_notes text,
    p_items jsonb
) returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_product_id uuid;
    v_product record;
    v_item jsonb;
    v_alloc jsonb;
    v_fridge_id uuid;
    v_fridge record;
    v_stock record;
    v_qty numeric;
    v_price numeric;
    v_alloc_qty numeric;
    v_alloc_total numeric;
    v_inside_total numeric;
    v_pre_inside numeric;
    v_outside_need numeric;
    v_total_amount numeric := 0;
    v_sale_item_id uuid;
    v_sale_id uuid := coalesce(p_sale_id, gen_random_uuid());
    v_item_count integer := 0;
    v_sale record;
begin
    if p_items is null or jsonb_typeof(p_items) <> 'array' or jsonb_array_length(p_items) = 0 then
        raise exception 'Bidhaa za kuuza zinahitajika';
    end if;

    if exists (select 1 from public.sales where id = v_sale_id) then
        raise exception 'Mauzo haya yameshahifadhiwa tayari';
    end if;

    -- Lock every affected product in a deterministic order. This is also the
    -- lock that serializes concurrent transfers for the same product.
    for v_product_id in
        select distinct (x->>'product_id')::uuid
          from jsonb_array_elements(p_items) x
         order by 1
    loop
        select * into v_product
          from public.products
         where id = v_product_id
         for update;

        if not found then
            raise exception 'Bidhaa haipo kwenye mfumo';
        end if;
        if coalesce(v_product.is_active, true) = false then
            raise exception 'Bidhaa % haipatikani', v_product.name;
        end if;
        if p_branch_id is not null and v_product.branch_id is distinct from p_branch_id then
            raise exception 'Bidhaa % ni ya tawi lingine', v_product.name;
        end if;

        select coalesce(sum((x->>'quantity')::numeric), 0)
          into v_qty
          from jsonb_array_elements(p_items) x
         where (x->>'product_id')::uuid = v_product_id;

        if v_qty <= 0 then
            raise exception 'Kiasi si sahihi kwa bidhaa %', v_product.name;
        end if;
        if coalesce(v_product.stock, 0) < v_qty then
            raise exception 'Stock haitoshi kwa % (inapatikana %)', v_product.name, v_product.stock;
        end if;

        -- How much of this product must come from Outside Stock?
        select coalesce(sum(
                   case
                     when x ? 'allocations' and jsonb_typeof(x->'allocations') = 'array' then
                        (select coalesce(sum(
                                    case
                                      when nullif(a->>'fridge_id', '') is null then (a->>'quantity')::numeric
                                      else 0
                                    end
                                ), 0)
                           from jsonb_array_elements(x->'allocations') a)
                     else (x->>'quantity')::numeric
                   end
               ), 0)
          into v_outside_need
          from jsonb_array_elements(p_items) x
         where (x->>'product_id')::uuid = v_product_id;

        select coalesce(sum(fs.quantity), 0)
          into v_pre_inside
          from public.fridge_stock fs
          join public.fridges f on f.id = fs.fridge_id
         where fs.product_id = v_product_id
           and fs.business_id = p_business_id
           and (fs.branch_id is not distinct from p_branch_id)
           and f.status = 'active';

        if v_outside_need > (coalesce(v_product.stock, 0) - v_pre_inside) then
            raise exception 'Stock ya nje haitoshi kwa % (inapatikana %)',
                v_product.name, (coalesce(v_product.stock, 0) - v_pre_inside);
        end if;
    end loop;

    insert into public.sales (
        id, seller_id, branch_id, customer_id, sale_date, total_amount,
        payment_method, notes, invoice_number, created_at, updated_at
    ) values (
        v_sale_id, p_seller_id, p_branch_id, p_customer_id,
        coalesce(p_sale_date, current_date), 0,
        coalesce(nullif(p_payment_method, ''), 'cash'), p_notes,
        p_invoice_number, now(), now()
    )
    returning * into v_sale;

    for v_item in select * from jsonb_array_elements(p_items)
    loop
        v_product_id := (v_item->>'product_id')::uuid;
        v_qty := (v_item->>'quantity')::numeric;
        v_price := (v_item->>'unit_price')::numeric;

        if v_qty is null or v_qty <= 0 then
            raise exception 'Kiasi cha bidhaa si sahihi';
        end if;
        if v_price is null or v_price < 0 then
            raise exception 'Bei ya bidhaa si sahihi';
        end if;

        v_total_amount := v_total_amount + (v_qty * v_price);
        v_sale_item_id := gen_random_uuid();
        v_item_count := v_item_count + 1;

        insert into public.sale_items (
            id, sale_id, product_id, quantity, unit_price, total_price, created_at
        ) values (
            v_sale_item_id, v_sale_id, v_product_id, v_qty, v_price,
            v_qty * v_price, now()
        );

        if v_item ? 'allocations' and jsonb_typeof(v_item->'allocations') = 'array' then
            select coalesce(sum((a->>'quantity')::numeric), 0)
              into v_alloc_total
              from jsonb_array_elements(v_item->'allocations') a;

            if abs(v_alloc_total - v_qty) > 0.0005 then
                raise exception 'Mgawanyo wa vyanzo haujakamilika kwa bidhaa %', v_product.name;
            end if;

            for v_alloc in select * from jsonb_array_elements(v_item->'allocations')
            loop
                v_alloc_qty := (v_alloc->>'quantity')::numeric;
                if v_alloc_qty is null or v_alloc_qty <= 0 then
                    continue;
                end if;

                v_fridge_id := nullif(v_alloc->>'fridge_id', '')::uuid;

                if v_fridge_id is null then
                    insert into public.sale_item_fridge_allocations (
                        sale_id, sale_item_id, business_id, branch_id, product_id,
                        fridge_id, fridge_name, quantity
                    ) values (
                        v_sale_id, v_sale_item_id, p_business_id, p_branch_id, v_product_id,
                        null, 'Outside', v_alloc_qty
                    );

                    insert into public.fridge_stock_movements (
                        business_id, branch_id, fridge_id, product_id, movement_type,
                        quantity, source_location, destination_location,
                        reference_type, reference_id, reason, created_by
                    ) values (
                        p_business_id, p_branch_id, null, v_product_id, 'sale',
                        v_alloc_qty, 'outside', 'sale',
                        'sale', v_sale_id, 'sale', p_seller_id
                    );
                    continue;
                end if;

                select fs.*, f.name as fridge_name
                  into v_stock
                  from public.fridge_stock fs
                  join public.fridges f on f.id = fs.fridge_id
                 where fs.fridge_id = v_fridge_id
                   and fs.product_id = v_product_id
                   and fs.business_id = p_business_id
                   and (fs.branch_id is not distinct from p_branch_id)
                   and f.status = 'active'
                 for update of fs;

                if not found then
                    raise exception 'Friji haipatikani kwa bidhaa %', v_product.name;
                end if;
                if v_stock.quantity < v_alloc_qty then
                    raise exception 'Stock ya friji % haitoshi kwa % (inapatikana %)',
                        v_stock.fridge_name, v_product.name, v_stock.quantity;
                end if;

                update public.fridge_stock
                   set quantity = quantity - v_alloc_qty,
                       updated_at = now()
                 where id = v_stock.id;

                insert into public.sale_item_fridge_allocations (
                    sale_id, sale_item_id, business_id, branch_id, product_id,
                    fridge_id, fridge_name, quantity
                ) values (
                    v_sale_id, v_sale_item_id, p_business_id, p_branch_id, v_product_id,
                    v_fridge_id, v_stock.fridge_name, v_alloc_qty
                );

                insert into public.fridge_stock_movements (
                    business_id, branch_id, fridge_id, product_id, movement_type,
                    quantity, source_location, destination_location,
                    reference_type, reference_id, reason, created_by
                ) values (
                    p_business_id, p_branch_id, v_fridge_id, v_product_id, 'sale',
                    v_alloc_qty, 'fridge', 'sale',
                    'sale', v_sale_id, 'sale', p_seller_id
                );
            end loop;
        else
            insert into public.sale_item_fridge_allocations (
                sale_id, sale_item_id, business_id, branch_id, product_id,
                fridge_id, fridge_name, quantity
            ) values (
                v_sale_id, v_sale_item_id, p_business_id, p_branch_id, v_product_id,
                null, 'Outside', v_qty
            );

            insert into public.fridge_stock_movements (
                business_id, branch_id, fridge_id, product_id, movement_type,
                quantity, source_location, destination_location,
                reference_type, reference_id, reason, created_by
            ) values (
                p_business_id, p_branch_id, null, v_product_id, 'sale',
                v_qty, 'outside', 'sale',
                'sale', v_sale_id, 'sale', p_seller_id
            );
        end if;
    end loop;

    update public.products p
       set stock = p.stock - agg.qty,
           updated_at = now()
      from (
          select (x->>'product_id')::uuid as product_id,
                 sum((x->>'quantity')::numeric) as qty
            from jsonb_array_elements(p_items) x
           group by 1
      ) agg
     where p.id = agg.product_id;

    if p_customer_id is not null then
        update public.customers
           set total_purchases = coalesce(total_purchases, 0) + v_total_amount,
               purchases_count = coalesce(purchases_count, 0) + 1,
               last_purchase_date = now(),
               updated_at = now()
         where id = p_customer_id;
    end if;

    update public.sales
       set total_amount = v_total_amount,
           updated_at = now()
     where id = v_sale_id
    returning * into v_sale;

    return jsonb_build_object(
        'success', true,
        'sale', to_jsonb(v_sale),
        'invoice_number', p_invoice_number,
        'total_amount', v_total_amount,
        'items_count', v_item_count
    );
end;
$$;

-- ---------------------------------------------------------------------------
-- 10. Threshold update helper (keeps 0 as "no threshold" for outside stock)
-- ---------------------------------------------------------------------------
create or replace function public.fridge_set_threshold(
    p_fridge_id uuid,
    p_product_id uuid,
    p_min_stock numeric,
    p_business_id uuid,
    p_branch_id uuid
) returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_stock record;
begin
    if p_min_stock is null or p_min_stock < 0 then
        raise exception 'Kiwango cha chini si sahihi';
    end if;

    update public.fridge_stock
       set min_stock = p_min_stock,
           updated_at = now()
     where fridge_id = p_fridge_id
       and product_id = p_product_id
       and business_id = p_business_id
       and (branch_id is not distinct from p_branch_id)
    returning * into v_stock;

    if not found then
        raise exception 'Bidhaa haipo kwenye friji hii';
    end if;

    return jsonb_build_object('success', true, 'stock', to_jsonb(v_stock));
end;
$$;

-- ---------------------------------------------------------------------------
-- 11. Access control: only the service role may run these functions.
--     The Laravel server is the only caller and it supplies the verified
--     business/branch from the JWT, never from the browser payload.
-- ---------------------------------------------------------------------------
revoke all on function public.fridge_transfer_in(uuid, uuid, numeric, uuid, uuid, uuid, numeric, text) from public, anon, authenticated;
revoke all on function public.fridge_transfer_out(uuid, uuid, numeric, uuid, uuid, uuid, text) from public, anon, authenticated;
revoke all on function public.create_pos_sale(uuid, uuid, uuid, uuid, text, uuid, date, text, text, jsonb) from public, anon, authenticated;
revoke all on function public.fridge_set_threshold(uuid, uuid, numeric, uuid, uuid) from public, anon, authenticated;

grant execute on function public.fridge_transfer_in(uuid, uuid, numeric, uuid, uuid, uuid, numeric, text) to service_role;
grant execute on function public.fridge_transfer_out(uuid, uuid, numeric, uuid, uuid, uuid, text) to service_role;
grant execute on function public.create_pos_sale(uuid, uuid, uuid, uuid, text, uuid, date, text, text, jsonb) to service_role;
grant execute on function public.fridge_set_threshold(uuid, uuid, numeric, uuid, uuid) to service_role;

commit;
