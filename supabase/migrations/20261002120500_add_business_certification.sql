-- =====================================================================
-- Business certification / public listing
-- =====================================================================
-- The public "Certified Businesses" page must only show businesses that are
-- actually certified — being registered is NOT the same as being certified
-- (task requirement). These columns let a Super Admin mark a business as
-- certified and expose only certified businesses publicly.
--
-- Existing columns on `businesses` already carry the display information
-- (business_name, business_location, business_logo_url, business_type,
-- business_description), so no data is duplicated here.
--
-- All statements are additive and safe to re-run.
-- =====================================================================

alter table public.businesses
    add column if not exists is_certified       boolean     not null default false,
    add column if not exists certified_at       timestamptz,
    add column if not exists certification_note text,
    add column if not exists is_public          boolean     not null default true,
    add column if not exists public_slug        text;

create index if not exists businesses_is_certified_idx
    on public.businesses (is_certified);

create unique index if not exists businesses_public_slug_key
    on public.businesses (public_slug)
    where public_slug is not null;

select 'businesses certification columns ready' as result;
