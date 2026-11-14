-- ============================================================================
-- Duka Mkononi — Centralized business settings (Supabase / PostgreSQL)
-- ============================================================================
--
-- Run this ONCE in the Supabase SQL Editor (or `supabase db push`).
-- Safe to re-run: the table is IF NOT EXISTS and every default is additive.
--
-- What this adds
--   public.business_settings   one row per business, holding the optional
--                              feature switches and notification preferences
--                              the admin Settings page manages.
--
-- Defaults deliberately preserve today's live behaviour:
--   fridge_enabled     true   the Fridge/Freezer module is already in use
--   services_enabled   true   service recipes and service sales already exist
--   scanner_enabled    false  no scanner implementation exists yet
--   notify_low_stock / notify_fridge_stock  true  alerts are already shown
--
-- A missing row means "all defaults". The API reads with defaults merged in,
-- so existing businesses need no backfill and disabling a feature never
-- deletes inventory, service history, or notification history.
-- ============================================================================

begin;

create table if not exists public.business_settings (
    business_id uuid primary key,
    fridge_enabled boolean not null default true,
    scanner_enabled boolean not null default false,
    services_enabled boolean not null default true,
    product_management_enabled boolean not null default false,
    notify_low_stock boolean not null default true,
    notify_fridge_stock boolean not null default true,
    updated_by uuid,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

-- Add the columns too, so a table created by an earlier partial run is
-- completed without losing whatever it already stores.
alter table public.business_settings
    add column if not exists fridge_enabled boolean not null default true;
alter table public.business_settings
    add column if not exists scanner_enabled boolean not null default false;
alter table public.business_settings
    add column if not exists services_enabled boolean not null default true;
alter table public.business_settings
    add column if not exists product_management_enabled boolean not null default false;
alter table public.business_settings
    add column if not exists notify_low_stock boolean not null default true;
alter table public.business_settings
    add column if not exists notify_fridge_stock boolean not null default true;
alter table public.business_settings
    add column if not exists updated_by uuid;
alter table public.business_settings
    add column if not exists created_at timestamptz not null default now();
alter table public.business_settings
    add column if not exists updated_at timestamptz not null default now();

create or replace function public.touch_business_settings_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = now();
    return new;
end;
$$;

drop trigger if exists business_settings_touch_updated_at on public.business_settings;
create trigger business_settings_touch_updated_at
    before update on public.business_settings
    for each row execute function public.touch_business_settings_updated_at();

commit;
