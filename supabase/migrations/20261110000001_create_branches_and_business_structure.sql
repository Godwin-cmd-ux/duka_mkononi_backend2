-- ============================================================================
-- Duka Mkononi — Multi-branch businesses (Supabase / PostgreSQL)
-- ============================================================================
--
-- Run this ONCE in the Supabase SQL Editor (or `supabase db push`).
-- It is safe to re-run: every table/column/index is IF NOT EXISTS and the
-- function is CREATE OR REPLACE. No existing row is deleted or rewritten,
-- except the additive backfill of office_expenses.business_id.
--
-- What this adds
--   1. businesses.business_structure   'non_branch' (default) | 'branch'
--   2. public.branches                 one row per branch of a business
--   3. users.branch_id                 a seller's assigned branch
--   4. branch_id on operational tables products, sales, customers,
--      services, service_sales (already existed) and office_expenses
--   5. office_expenses.business_id     additive backfill from business_name
--   6. register_business_with_branches(...)  one transaction for business +
--      all branches, so a partial registration can never be committed
--
-- Naming note: `businesses.business_type` ALREADY exists and means the
-- industry (pharmacy, supermarket, ...). The branch/non-branch choice is a
-- separate column, `business_structure`, so registration validation and the
-- certification/onboarding features that read business_type keep working.
--
-- Identification rule (never swap these):
--   business_id = the whole business
--   branch_id   = one branch inside that business
-- ============================================================================

begin;

-- ---------------------------------------------------------------------------
-- 1. businesses.business_structure
-- ---------------------------------------------------------------------------
alter table public.businesses
    add column if not exists business_structure text not null default 'non_branch';

do $$
begin
    if not exists (
        select 1 from pg_constraint
         where conrelid = 'public.businesses'::regclass
           and conname = 'businesses_business_structure_check'
    ) then
        alter table public.businesses
            add constraint businesses_business_structure_check
            check (business_structure in ('non_branch', 'branch'));
    end if;
end $$;

-- Every pre-existing business is a standalone business. This is the
-- backward-compatibility promise: their dashboard, login and data are
-- unchanged and no branch rows are invented for them.
update public.businesses
   set business_structure = 'non_branch'
 where business_structure is null;

-- ---------------------------------------------------------------------------
-- 2. branches
--
-- `id` is the branch_id used everywhere else (same UUID convention as
-- service_sales.branch_id). A branch belongs to exactly one business; the
-- foreign key cascades so deleting a business cannot leave orphan branches.
-- ---------------------------------------------------------------------------
create table if not exists public.branches (
    id          uuid primary key default gen_random_uuid(),
    business_id uuid not null references public.businesses (id) on delete cascade,
    branch_name text not null,
    location    text,
    latitude    double precision,
    longitude   double precision,
    status      text not null default 'active',
    created_at  timestamptz not null default now(),
    updated_at  timestamptz not null default now(),
    constraint branches_name_not_blank check (btrim(branch_name) <> ''),
    constraint branches_status_check check (status in ('active', 'inactive')),
    constraint branches_latitude_check check (latitude is null or (latitude >= -90 and latitude <= 90)),
    constraint branches_longitude_check check (longitude is null or (longitude >= -180 and longitude <= 180))
);

create unique index if not exists branches_business_name_unique
    on public.branches (business_id, lower(btrim(branch_name)));

create index if not exists branches_business_status_index
    on public.branches (business_id, status);

-- ---------------------------------------------------------------------------
-- 3. users.branch_id
--
-- A seller's branch assignment. NULL on every existing account (and on every
-- non-branch business) so nothing changes for today's users. ON DELETE SET
-- NULL keeps the account if a branch is ever removed.
-- ---------------------------------------------------------------------------
alter table public.users
    add column if not exists business_id uuid;

alter table public.users
    add column if not exists branch_id uuid;

do $$
begin
    if not exists (
        select 1 from pg_constraint
         where conrelid = 'public.users'::regclass
           and conname = 'users_branch_id_fkey'
    ) then
        alter table public.users
            add constraint users_branch_id_fkey
            foreign key (branch_id) references public.branches (id) on delete set null;
    end if;
end $$;

create index if not exists users_business_branch_index
    on public.users (business_id, branch_id);

-- ---------------------------------------------------------------------------
-- 4. Branch ownership on operational tables
--
-- Only tables whose rows genuinely belong to one branch get the column.
-- sale_items / service_sale_items are deliberately left alone: their branch
-- is the branch of their parent sale, so duplicating it would create a second
-- source of truth.
--
-- All columns are NULLABLE on purpose:
--   NULL = a legacy/non-branch row, or a business-wide record (services).
-- ---------------------------------------------------------------------------
alter table public.products      add column if not exists branch_id uuid;
alter table public.sales         add column if not exists branch_id uuid;
alter table public.customers     add column if not exists branch_id uuid;
alter table public.services      add column if not exists branch_id uuid;
alter table public.service_sales add column if not exists branch_id uuid;
alter table public.office_expenses add column if not exists business_id uuid;
alter table public.office_expenses add column if not exists branch_id uuid;

do $$
declare
    spec record;
begin
    for spec in
        select * from (values
            ('products',        'products_branch_id_fkey'),
            ('sales',           'sales_branch_id_fkey'),
            ('customers',       'customers_branch_id_fkey'),
            ('services',        'services_branch_id_fkey'),
            ('service_sales',   'service_sales_branch_id_fkey'),
            ('office_expenses', 'office_expenses_branch_id_fkey')
        ) as t(tbl, fk)
    loop
        if not exists (
            select 1 from pg_constraint
             where conrelid = ('public.' || spec.tbl)::regclass
               and conname = spec.fk
        ) then
            execute format(
                'alter table public.%I add constraint %I foreign key (branch_id) references public.branches (id) on delete set null',
                spec.tbl, spec.fk
            );
        end if;
    end loop;
end $$;

do $$
begin
    if not exists (
        select 1 from pg_constraint
         where conrelid = 'public.office_expenses'::regclass
           and conname = 'office_expenses_business_id_fkey'
    ) then
        alter table public.office_expenses
            add constraint office_expenses_business_id_fkey
            foreign key (business_id) references public.businesses (id) on delete cascade;
    end if;
end $$;

create index if not exists products_business_branch_index  on public.products (branch_id);
create index if not exists sales_business_branch_index     on public.sales (branch_id);
create index if not exists customers_business_branch_index on public.customers (branch_id);
create index if not exists services_branch_index           on public.services (branch_id);
create index if not exists service_sales_branch_date_index on public.service_sales (branch_id, sale_date);
create index if not exists office_expenses_business_branch_index on public.office_expenses (business_id, branch_id);

-- ---------------------------------------------------------------------------
-- 5. Additive backfill: office_expenses.business_id
--
-- Expenses historically stored only the display name. Resolve it through the
-- canonical legacy key (case/whitespace-insensitive) WITHOUT overwriting the
-- name or touching rows that already have a business_id. No rows are created
-- or deleted; unmatched rows stay NULL and remain scoped by user membership.
-- ---------------------------------------------------------------------------
update public.office_expenses e
   set business_id = b.id
  from public.businesses b
 where e.business_id is null
   and e.business_name is not null
   and lower(btrim(e.business_name)) = b.legacy_name_key;

-- ---------------------------------------------------------------------------
-- 6. Atomic business + branch registration
--
-- PostgREST cannot span several tables in a transaction, so registration goes
-- through this function exactly like create_service_sale does. If ANY branch
-- is invalid the whole call raises and PostgreSQL rolls back the business row
-- too — there is no partially registered business.
--
-- Returns:
--   { "created": bool,
--     "business": { ...businesses row... },
--     "branches": [ { ...branches row... }, ... ] }
--
-- A duplicate legacy_name_key returns the EXISTING business and its branches
-- with created=false, matching BusinessResolver::resolveOrCreate behaviour.
-- ---------------------------------------------------------------------------
create or replace function public.register_business_with_branches(
    p_business_name       text,
    p_legacy_name_key     text,
    p_business_structure  text default 'non_branch',
    p_business_type       text default null,
    p_business_description text default null,
    p_business_location   text default null,
    p_branches            jsonb default '[]'::jsonb
)
returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_business   public.businesses%rowtype;
    v_branch     public.branches%rowtype;
    v_element    jsonb;
    v_branches   jsonb := '[]'::jsonb;
    v_count      integer := 0;
    v_name       text;
    v_location   text;
    v_lat        double precision;
    v_lng        double precision;
begin
    if btrim(coalesce(p_business_name, '')) = '' then
        raise exception 'Jina la biashara linahitajika';
    end if;

    if btrim(coalesce(p_legacy_name_key, '')) = '' then
        raise exception 'legacy_name_key linahitajika';
    end if;

    if p_business_structure not in ('non_branch', 'branch') then
        raise exception 'Aina ya biashara si sahihi';
    end if;

    if p_branches is null or jsonb_typeof(p_branches) <> 'array' then
        p_branches := '[]'::jsonb;
    end if;

    -- One business per legacy key: return the existing parent instead of
    -- creating a second business for the same shop.
    select * into v_business
      from public.businesses
     where legacy_name_key = p_legacy_name_key
     limit 1;

    if found then
        select coalesce(jsonb_agg(to_jsonb(b)), '[]'::jsonb)
          into v_branches
          from public.branches b
         where b.business_id = v_business.id;

        return jsonb_build_object(
            'created', false,
            'business', to_jsonb(v_business),
            'branches', v_branches
        );
    end if;

    if p_business_structure = 'branch' and jsonb_array_length(p_branches) = 0 then
        raise exception 'Biashara yenye matawi inahitaji tawi moja au zaidi';
    end if;

    insert into public.businesses (
        id,
        business_name,
        legacy_name_key,
        business_structure,
        business_type,
        business_description,
        business_location,
        merged_from,
        created_at,
        updated_at
    ) values (
        gen_random_uuid(),
        btrim(p_business_name),
        p_legacy_name_key,
        p_business_structure,
        p_business_type,
        p_business_description,
        p_business_location,
        jsonb_build_object('source', 'signup'),
        now(),
        now()
    )
    returning * into v_business;

    if p_business_structure = 'branch' then
        for v_element in select * from jsonb_array_elements(p_branches)
        loop
            v_count := v_count + 1;

            v_name := btrim(coalesce(v_element->>'branch_name', ''));
            if v_name = '' then
                raise exception 'Jina la tawi linahitajika';
            end if;

            v_location := nullif(btrim(coalesce(v_element->>'location', '')), '');
            if v_location is null then
                raise exception 'Mahali pa tawi linahitajika';
            end if;

            v_lat := null;
            v_lng := null;
            if (v_element->>'latitude') is not null and (v_element->>'latitude') <> '' then
                v_lat := (v_element->>'latitude')::double precision;
            end if;
            if (v_element->>'longitude') is not null and (v_element->>'longitude') <> '' then
                v_lng := (v_element->>'longitude')::double precision;
            end if;

            insert into public.branches (
                id, business_id, branch_name, location, latitude, longitude, status, created_at, updated_at
            ) values (
                gen_random_uuid(), v_business.id, v_name, v_location, v_lat, v_lng, 'active', now(), now()
            )
            returning * into v_branch;

            v_branches := v_branches || to_jsonb(v_branch);
        end loop;
    end if;

    return jsonb_build_object(
        'created', true,
        'business', to_jsonb(v_business),
        'branches', v_branches
    );
end;
$$;

-- ---------------------------------------------------------------------------
-- 7. Keep updated_at honest on branch edits made directly in SQL
-- ---------------------------------------------------------------------------
create or replace function public.touch_branches_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = now();
    return new;
end;
$$;

drop trigger if exists branches_touch_updated_at on public.branches;
create trigger branches_touch_updated_at
    before update on public.branches
    for each row execute function public.touch_branches_updated_at();

commit;
