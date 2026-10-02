-- =====================================================================
-- Order status history
-- =====================================================================
-- Append-only log of every status change and of the notes attached to it.
-- Track Orders reads the customer-visible rows from here (note +
-- status-update dates). `internal_note` is staff-only and must never be
-- returned by the public tracking endpoint.
-- =====================================================================

create table if not exists public.order_status_history (
    id            uuid primary key default gen_random_uuid(),
    order_id      uuid        not null references public.orders (id) on delete cascade,
    status        text        not null,
    note          text,
    internal_note text,
    changed_by    text,
    created_at    timestamptz not null default now()
);

create index if not exists order_status_history_order_idx
    on public.order_status_history (order_id, created_at desc);

select 'order_status_history ready' as result;
