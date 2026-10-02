-- =====================================================================
-- Customer reviews (published from an inquiry)
-- =====================================================================
-- A review is a SEPARATE public record derived from an inquiry, so private
-- contact details (email / phone) on `inquiries` are never reachable from the
-- public homepage. One inquiry can be published at most once
-- (unique inquiry_id), which prevents duplicate reviews.
--
-- The homepage Customer Reviews section must only render rows where
-- is_published = true. Unpublishing just flips is_published back to false.
-- =====================================================================

create table if not exists public.customer_reviews (
    id           uuid primary key default gen_random_uuid(),
    inquiry_id   uuid unique references public.inquiries (id) on delete set null,
    display_name text,
    title        text,
    body         text        not null,
    rating       smallint,
    is_published boolean     not null default false,
    published_at timestamptz,
    created_at   timestamptz not null default now(),
    updated_at   timestamptz not null default now(),
    constraint customer_reviews_body_not_blank check (btrim(body) <> ''),
    constraint customer_reviews_rating_check   check (rating is null or rating between 1 and 5)
);

create index if not exists customer_reviews_published_idx
    on public.customer_reviews (is_published, published_at desc);

select 'customer_reviews ready' as result;
