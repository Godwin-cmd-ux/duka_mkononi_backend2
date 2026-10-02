-- =====================================================================
-- Customer orders (website + mobile share this table)
-- =====================================================================
-- One row per customer order. Separate from the seller POS `sales` table.
--
-- status lifecycle (canonical for both Track Orders and the Muuzaji module):
--   pending -> confirmed -> processing -> ready -> out_for_delivery
--           -> delivered -> completed
--   any state except completed -> cancelled
--
-- Money: the backend always recomputes subtotal / total from order_items
-- using trusted product prices; the browser-supplied total is ignored.
--
-- Idempotency: the client sends a `client_order_key`; a retried submission
-- with the same key must not create a second order (unique index below).
--
-- Phone tracking: `customer_phone` is stored as entered, and
-- `customer_phone_normalized` (digits only, e.g. 2557…) is what Track Orders
-- searches by. Both are indexed.
--
-- business_id / seller_id are TEXT to match the existing text id columns
-- (products.seller_id, sales.seller_id); no hard FK is added across them.
-- =====================================================================

create table if not exists public.orders (
    id                        uuid primary key default gen_random_uuid(),
    order_reference           text        not null,
    business_id               text,
    seller_id                 text,
    customer_name             text,
    customer_phone            text        not null,
    customer_phone_normalized text        not null,
    customer_email            text,
    customer_note             text,
    status                    text        not null default 'pending',
    source                    text        not null default 'website',
    subtotal                  numeric(14,2) not null default 0,
    shipping_cost             numeric(14,2) not null default 0,
    total_amount              numeric(14,2) not null default 0,
    currency                  text        not null default 'TZS',
    payment_status            text        not null default 'unpaid',
    payment_method            text,
    internal_note             text,
    customer_visible_note     text,
    client_order_key          text,
    stock_released            boolean     not null default false,
    placed_at                 timestamptz not null default now(),
    confirmed_at              timestamptz,
    completed_at              timestamptz,
    cancelled_at              timestamptz,
    created_at                timestamptz not null default now(),
    updated_at                timestamptz not null default now(),
    constraint orders_status_check check (status in (
        'pending', 'confirmed', 'processing', 'ready',
        'out_for_delivery', 'delivered', 'completed', 'cancelled'
    )),
    constraint orders_payment_status_check check (payment_status in (
        'unpaid', 'paid', 'refunded', 'failed'
    )),
    constraint orders_phone_not_blank  check (btrim(customer_phone) <> ''),
    constraint orders_subtotal_non_neg check (subtotal     >= 0),
    constraint orders_total_non_neg    check (total_amount >= 0)
);

create unique index if not exists orders_order_reference_key
    on public.orders (order_reference);
create unique index if not exists orders_client_order_key_key
    on public.orders (client_order_key)
    where client_order_key is not null;

create index if not exists orders_business_id_idx   on public.orders (business_id);
create index if not exists orders_seller_id_idx      on public.orders (seller_id);
create index if not exists orders_status_idx         on public.orders (status);
create index if not exists orders_phone_norm_idx     on public.orders (customer_phone_normalized);
create index if not exists orders_created_at_idx     on public.orders (created_at desc);

select 'orders ready' as result;
