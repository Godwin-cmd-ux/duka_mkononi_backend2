-- =====================================================================
-- Order line items
-- =====================================================================
-- One row per product in an order. Prices are SNAPSHOTS taken from the
-- products table at order time, so historical orders never change when a
-- product price is later edited.
--
-- product_id / business_id are TEXT to match products.id / businesses.id
-- usage elsewhere in the app. order_id is a real FK (both UUID).
-- =====================================================================

create table if not exists public.order_items (
    id           uuid primary key default gen_random_uuid(),
    order_id     uuid          not null references public.orders (id) on delete cascade,
    product_id   text,
    business_id  text,
    product_name text          not null,
    unit_price   numeric(14,2) not null,
    quantity     integer       not null,
    line_total   numeric(14,2) not null,
    created_at   timestamptz   not null default now(),
    constraint order_items_quantity_positive check (quantity > 0),
    constraint order_items_unit_price_non_neg check (unit_price >= 0),
    constraint order_items_line_total_non_neg check (line_total >= 0)
);

create index if not exists order_items_order_id_idx   on public.order_items (order_id);
create index if not exists order_items_product_id_idx on public.order_items (product_id);
create index if not exists order_items_business_id_idx on public.order_items (business_id);

select 'order_items ready' as result;
