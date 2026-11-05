-- ============================================================================
-- Duka Mkononi — Huduma (services) and service sales (Supabase / PostgreSQL)
-- ============================================================================
--
-- A service is a sellable activity (e.g. "Hair Treatment") whose execution
-- consumes existing inventory products. This migration adds:
--
--   public.services            the service definition, owned by one business
--   public.service_consumables the recipe: product + required quantity
--   public.service_sales       immutable sale header + financial snapshot
--   public.service_sale_items  immutable per-consumable cost snapshot
--   public.create_service_sale(...)  the ONLY write path for a service sale
--
-- Why a database function instead of sequential PostgREST calls
-- --------------------------------------------------------------
-- The rest of the API talks to Supabase over PostgREST, which has no
-- cross-table transaction. A service sale must deduct N products, write the
-- sale header and write N snapshot lines. If the third product is short on
-- stock, the first two must NOT stay deducted. `create_service_sale` runs in
-- one transaction, locks each product row FOR UPDATE, and raises on the first
-- problem — PostgreSQL then rolls the whole call back. It also makes repeated
-- submissions idempotent through (seller_id, client_key).
--
-- How to run
--   Dashboard : SQL Editor -> paste this file -> Run
--   CLI       : supabase db push
--
-- Safe to re-run: tables/indexes are IF NOT EXISTS and the function is CREATE
-- OR REPLACE. No existing table, column or row is dropped or altered.
-- ============================================================================

begin;

-- ---------------------------------------------------------------------------
-- 1. services
--
-- `business_id` is the tenant boundary taken from the caller's verified JWT
-- (users.business_id), never from the request body. Names are unique per
-- business (case/whitespace-insensitive) so two admins cannot create the same
-- service twice and split its history.
-- ---------------------------------------------------------------------------
create table if not exists public.services (
    id           uuid primary key default gen_random_uuid(),
    business_id  uuid not null,
    name         text not null,
    description  text,
    status       text not null default 'active',
    created_by   uuid,
    created_at   timestamptz not null default now(),
    updated_at   timestamptz not null default now(),
    constraint services_name_not_blank check (btrim(name) <> ''),
    constraint services_status_check check (status in ('active', 'inactive'))
);

do $$
begin
    -- Only attach the tenant FK when the businesses table exists in this
    -- project; some deployments keep business ids without a parent table.
    if to_regclass('public.businesses') is not null
       and not exists (
           select 1 from pg_constraint
            where conrelid = 'public.services'::regclass
              and conname = 'services_business_id_fkey'
       )
    then
        alter table public.services
            add constraint services_business_id_fkey
            foreign key (business_id) references public.businesses (id)
            on delete cascade;
    end if;
end $$;

create unique index if not exists services_business_name_unique
    on public.services (business_id, lower(btrim(name)));

create index if not exists services_business_status_index
    on public.services (business_id, status);

-- ---------------------------------------------------------------------------
-- 2. service_consumables (the recipe)
--
-- References the EXISTING product rows: no shadow inventory is created. One
-- product may appear in many services and remains independently sellable.
--
--   service deleted  -> its recipe rows go with it (cascade)
--   product deleted  -> RESTRICT: a product still used by a recipe cannot be
--                       hard-deleted, so a sale can never reference nothing
--                       (the admin removes it from the service first; that
--                       never deletes the inventory product)
--
-- required_quantity may be 0 (a consumable that is optional by default) but
-- never negative. Quantities are numeric so a fractional unit is representable
-- even though the current `products.stock` column is integer; the sale
-- function below rejects fractional deductions rather than silently rounding.
-- ---------------------------------------------------------------------------
create table if not exists public.service_consumables (
    id                 uuid primary key default gen_random_uuid(),
    service_id         uuid not null references public.services (id) on delete cascade,
    product_id         uuid not null references public.products (id) on delete restrict,
    required_quantity  numeric(14, 3) not null default 0,
    created_at         timestamptz not null default now(),
    updated_at         timestamptz not null default now(),
    constraint service_consumables_quantity_check check (required_quantity >= 0),
    constraint service_consumables_unique unique (service_id, product_id)
);

create index if not exists service_consumables_product_index
    on public.service_consumables (product_id);

-- ---------------------------------------------------------------------------
-- 3. service_sales (history)
--
-- Everything reported here is a SNAPSHOT taken at sale time:
--   service_name   survives renaming/deleting the service
--   selling_price  the amount actually charged (services have no fixed price)
--   total_cost     sum of (quantity used x buying price at that moment)
--   profit         selling_price - total_cost, computed by the database
--
-- service_id is nullable and ON DELETE SET NULL on purpose: deleting a service
-- must never delete or rewrite the history of sales already performed.
-- `branch_id` is carried for deployments that add branches later; this
-- application currently has one stock pool per business, so it stays null.
-- ---------------------------------------------------------------------------
create table if not exists public.service_sales (
    id                uuid primary key default gen_random_uuid(),
    invoice_number    text not null unique,
    business_id       uuid not null,
    branch_id         uuid,
    seller_id         uuid not null,
    service_id        uuid references public.services (id) on delete set null,
    service_name      text not null,
    service_quantity  numeric(14, 3) not null default 1,
    selling_price     numeric(14, 2) not null default 0,
    total_cost        numeric(14, 2) not null default 0,
    profit            numeric(14, 2) not null default 0,
    customer_id       uuid,
    sale_date         date not null default current_date,
    payment_method    text not null default 'cash',
    status            text not null default 'completed',
    notes             text,
    client_key        text,
    created_at        timestamptz not null default now(),
    updated_at        timestamptz not null default now(),
    constraint service_sales_quantity_check check (service_quantity > 0),
    constraint service_sales_price_check check (selling_price >= 0),
    constraint service_sales_cost_check check (total_cost >= 0),
    constraint service_sales_status_check check (status in ('completed', 'voided'))
);

create index if not exists service_sales_business_date_index
    on public.service_sales (business_id, sale_date);

create index if not exists service_sales_seller_date_index
    on public.service_sales (seller_id, sale_date);

create index if not exists service_sales_service_index
    on public.service_sales (service_id);

-- Idempotency: a retried request (same seller, same client key) returns the
-- original sale instead of deducting stock a second time.
create unique index if not exists service_sales_client_key_unique
    on public.service_sales (seller_id, client_key)
    where client_key is not null and btrim(client_key) <> '';

-- ---------------------------------------------------------------------------
-- 4. service_sale_items (what was consumed)
--
-- product_id is nullable with ON DELETE SET NULL: the name and buying price
-- below are the permanent record, so historical costs never follow a future
-- price change or a deleted product.
-- ---------------------------------------------------------------------------
create table if not exists public.service_sale_items (
    id               uuid primary key default gen_random_uuid(),
    service_sale_id  uuid not null references public.service_sales (id) on delete cascade,
    product_id       uuid references public.products (id) on delete set null,
    product_name     text not null,
    quantity         numeric(14, 3) not null,
    buying_price     numeric(14, 2) not null default 0,
    total_cost       numeric(14, 2) not null default 0,
    created_at       timestamptz not null default now(),
    constraint service_sale_items_quantity_check check (quantity > 0),
    constraint service_sale_items_price_check check (buying_price >= 0)
);

create index if not exists service_sale_items_sale_index
    on public.service_sale_items (service_sale_id);

create index if not exists service_sale_items_product_index
    on public.service_sale_items (product_id);

-- ---------------------------------------------------------------------------
-- 5. create_service_sale — the atomic write path
--
-- Parameters (all resolved server-side by the API; none is trusted from the
-- browser beyond the value the seller typed):
--   p_seller_id         authenticated user id
--   p_business_id       authenticated business id
--   p_service_id        service being performed (must belong to p_business_id)
--   p_service_quantity  how many units of the service were performed
--   p_selling_price     total amount charged to the customer (>= 0)
--   p_consumables       [{"product_id": uuid, "quantity": number}, ...]
--   p_client_key        optional idempotency key
--
-- Failure modes raise SQLSTATE P0001 with a machine-readable code; the API
-- maps them to friendly messages. Any raise rolls back already-deducted stock.
--
-- security definer: the API connects with the service-role key and this
-- function must be able to write sales and products regardless of the
-- caller's RLS grants.
-- ---------------------------------------------------------------------------
create or replace function public.create_service_sale(
    p_seller_id         uuid,
    p_business_id       uuid,
    p_service_id        uuid,
    p_service_quantity  numeric,
    p_selling_price     numeric,
    p_consumables       jsonb,
    p_customer_id       uuid    default null,
    p_payment_method    text    default 'cash',
    p_sale_date         date    default null,
    p_notes             text    default null,
    p_client_key        text    default null
)
returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_service      public.services%rowtype;
    v_sale         public.service_sales%rowtype;
    v_product      public.products%rowtype;
    v_elem         jsonb;
    v_qty          numeric;
    v_line_cost    numeric(14, 2);
    v_total_cost   numeric(14, 2) := 0;
    v_sale_id      uuid := gen_random_uuid();
    v_items        jsonb := '[]'::jsonb;
    v_rows         integer;
    v_key          text := nullif(btrim(coalesce(p_client_key, '')), '');
    v_invoice      text;
    v_member_ok    boolean;
begin
    -- ---- 5.1 Input sanity ------------------------------------------------
    if p_service_quantity is null or p_service_quantity <= 0 then
        raise exception 'SERVICE_QUANTITY_INVALID' using errcode = 'P0001';
    end if;

    if p_selling_price is null or p_selling_price < 0 then
        raise exception 'SERVICE_PRICE_INVALID' using errcode = 'P0001';
    end if;

    if p_consumables is null
       or jsonb_typeof(p_consumables) <> 'array'
       or jsonb_array_length(p_consumables) = 0 then
        raise exception 'SERVICE_CONSUMABLES_REQUIRED' using errcode = 'P0001';
    end if;

    -- ---- 5.2 Seller must belong to this business -------------------------
    select exists (
        select 1
          from public.users u
         where u.id = p_seller_id
           and u.business_id = p_business_id
           and u.status = 'approved'
           and u.role in ('admin', 'seller', 'super_admin', 'system_admin')
    ) into v_member_ok;

    if not v_member_ok then
        raise exception 'SELLER_NOT_IN_BUSINESS' using errcode = 'P0001';
    end if;

    -- ---- 5.3 Service must exist in this business, and be locked ----------
    select *
      into v_service
      from public.services
     where id = p_service_id
       and business_id = p_business_id
       for update;

    if not found then
        raise exception 'SERVICE_NOT_FOUND' using errcode = 'P0001';
    end if;

    if v_service.status <> 'active' then
        raise exception 'SERVICE_INACTIVE' using errcode = 'P0001';
    end if;

    -- ---- 5.4 Idempotent replay ------------------------------------------
    if v_key is not null then
        select *
          into v_sale
          from public.service_sales
         where seller_id = p_seller_id
           and client_key = v_key
         limit 1;

        if found then
            return jsonb_build_object(
                'duplicate', true,
                'sale', to_jsonb(v_sale),
                'items', coalesce((
                    select jsonb_agg(to_jsonb(i) order by i.product_name)
                      from public.service_sale_items i
                     where i.service_sale_id = v_sale.id
                ), '[]'::jsonb)
            );
        end if;
    end if;

    -- ---- 5.5 Consume each product ---------------------------------------
    -- Duplicate product lines are merged first. Products are processed in a
    -- deterministic (uuid) order so two concurrent services that share products
    -- always take the same row locks in the same order: no deadlocks.
    for v_elem in
        select jsonb_build_object(
                   'product_id', elem ->> 'product_id',
                   'quantity',   sum((elem ->> 'quantity')::numeric)
               )
          from jsonb_array_elements(p_consumables) as elem
         group by elem ->> 'product_id'
         order by elem ->> 'product_id'
    loop
        v_qty := (v_elem ->> 'quantity')::numeric;

        if v_qty is null or v_qty <= 0 then
            raise exception 'CONSUMABLE_QUANTITY_INVALID:%', coalesce(v_elem ->> 'product_id', '')
                using errcode = 'P0001';
        end if;

        -- products.stock is an integer column. Refuse a fractional deduction
        -- rather than rounding a customer's inventory silently.
        if v_qty <> trunc(v_qty) then
            raise exception 'QUANTITY_NOT_INTEGER:%', coalesce(v_elem ->> 'product_id', '')
                using errcode = 'P0001';
        end if;

        if coalesce(v_elem ->> 'product_id', '') !~
           '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$' then
            raise exception 'PRODUCT_NOT_FOUND:%', coalesce(v_elem ->> 'product_id', '')
                using errcode = 'P0001';
        end if;

        select *
          into v_product
          from public.products
         where id = (v_elem ->> 'product_id')::uuid
           and coalesce(is_active, true)
           for update;

        if not found then
            raise exception 'PRODUCT_NOT_FOUND:%', v_elem ->> 'product_id'
                using errcode = 'P0001';
        end if;

        -- The product's owner must be a member of the SAME business. This is
        -- the stock-level IDOR guard: a caller cannot make a service consume
        -- another business's product even by guessing its uuid.
        select exists (
            select 1
              from public.users u
             where u.id = v_product.seller_id
               and u.business_id = p_business_id
               and u.role in ('admin', 'seller', 'super_admin', 'system_admin')
        ) into v_member_ok;

        if not v_member_ok then
            raise exception 'PRODUCT_NOT_IN_BUSINESS:%', v_product.id
                using errcode = 'P0001';
        end if;

        -- Conditional update = the stock check and the deduction are one
        -- atomic statement; -1 is impossible even under concurrency.
        update public.products
           set stock = stock - v_qty,
               updated_at = now()
         where id = v_product.id
           and coalesce(stock, 0) >= v_qty;

        get diagnostics v_rows = row_count;

        if v_rows = 0 then
            raise exception 'INSUFFICIENT_STOCK:%:%:%',
                v_product.id, coalesce(v_product.stock, 0), v_qty
                using errcode = 'P0001';
        end if;

        -- Buying price is products.price ("Bei ya Kununua" throughout the app).
        -- It is captured NOW so later price changes cannot rewrite history.
        v_line_cost := round(v_qty * coalesce(v_product.price, 0), 2);
        v_total_cost := v_total_cost + v_line_cost;

        v_items := v_items || jsonb_build_object(
            'product_id',   v_product.id,
            'product_name', v_product.name,
            'quantity',     v_qty,
            'buying_price', coalesce(v_product.price, 0),
            'total_cost',   v_line_cost
        );
    end loop;

    -- ---- 5.6 Write the sale header --------------------------------------
    v_invoice := 'SRV-' || to_char(now() at time zone 'utc', 'YYYYMMDD') || '-' ||
                 upper(substr(replace(v_sale_id::text, '-', ''), 1, 8));

    insert into public.service_sales (
        id, invoice_number, business_id, seller_id, service_id, service_name,
        service_quantity, selling_price, total_cost, profit, customer_id,
        sale_date, payment_method, status, notes, client_key, created_at, updated_at
    ) values (
        v_sale_id, v_invoice, p_business_id, p_seller_id, v_service.id, v_service.name,
        p_service_quantity, round(p_selling_price, 2), v_total_cost,
        round(p_selling_price - v_total_cost, 2), p_customer_id,
        coalesce(p_sale_date, current_date),
        coalesce(nullif(btrim(coalesce(p_payment_method, '')), ''), 'cash'),
        'completed', p_notes, v_key, now(), now()
    )
    returning * into v_sale;

    -- ---- 5.7 Write the immutable consumable snapshot --------------------
    insert into public.service_sale_items (
        service_sale_id, product_id, product_name, quantity, buying_price, total_cost
    )
    select v_sale_id,
           (item ->> 'product_id')::uuid,
           item ->> 'product_name',
           (item ->> 'quantity')::numeric,
           (item ->> 'buying_price')::numeric,
           (item ->> 'total_cost')::numeric
      from jsonb_array_elements(v_items) as item;

    return jsonb_build_object(
        'duplicate', false,
        'sale', to_jsonb(v_sale),
        'items', coalesce((
            select jsonb_agg(to_jsonb(i) order by i.product_name)
              from public.service_sale_items i
             where i.service_sale_id = v_sale_id
        ), '[]'::jsonb)
    );

exception
    -- Two identical submissions raced past 5.4: the unique index rejects the
    -- second insert. Return the winner instead of failing, and (because the
    -- handler rolls back this call's subtransaction) the loser's stock
    -- deductions are undone first.
    when unique_violation then
        if v_key is not null then
            select *
              into v_sale
              from public.service_sales
             where seller_id = p_seller_id
               and client_key = v_key
             limit 1;

            if found then
                return jsonb_build_object(
                    'duplicate', true,
                    'sale', to_jsonb(v_sale),
                    'items', coalesce((
                        select jsonb_agg(to_jsonb(i) order by i.product_name)
                          from public.service_sale_items i
                         where i.service_sale_id = v_sale.id
                    ), '[]'::jsonb)
                );
            end if;
        end if;
        raise;
end;
$$;

-- The API uses the service-role key, which already bypasses RLS; these grants
-- keep the function callable if the deployment ever uses anon/authenticated.
revoke all on function public.create_service_sale(
    uuid, uuid, uuid, numeric, numeric, jsonb, uuid, text, date, text, text
) from public;

grant execute on function public.create_service_sale(
    uuid, uuid, uuid, numeric, numeric, jsonb, uuid, text, date, text, text
) to service_role;

commit;

-- ---------------------------------------------------------------------------
-- 6. Make PostgREST aware of the new tables/function.
-- Must run after the DDL committed, hence outside the transaction.
-- ---------------------------------------------------------------------------
select pg_sleep(1);
notify pgrst, 'reload schema';

-- ---------------------------------------------------------------------------
-- 7. Verify
-- ---------------------------------------------------------------------------
select table_name, count(*) as columns
  from information_schema.columns
 where table_schema = 'public'
   and table_name in ('services', 'service_consumables', 'service_sales', 'service_sale_items')
 group by table_name
 order by table_name;

-- Rollback (run manually; there is no automatic down-migration here).
-- ---------------------------------------------------------------------------
-- drop function if exists public.create_service_sale(uuid, uuid, uuid, numeric, numeric, jsonb, uuid, text, date, text, text);
-- drop table if exists public.service_sale_items;
-- drop table if exists public.service_sales;
-- drop table if exists public.service_consumables;
-- drop table if exists public.services;
