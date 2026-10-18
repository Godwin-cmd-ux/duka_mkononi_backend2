-- ============================================================================
-- Duka Mkononi — allow the Oceanic Sapphire theme (Supabase / PostgreSQL)
-- ============================================================================
--
-- Brings `public.users.preferred_theme` to the five-theme allow-list:
--
--   'white-professional' | 'royal-champagne-cream' | 'midnight-royal-gold'
--   | 'emerald-bazaar' | 'oceanic-sapphire'
--
-- This file is SELF-SUFFICIENT: it also creates the column when
-- supabase/migrations/20261017000001_add_preferred_theme.sql was never
-- applied, and it is a no-op for a database that already has it. That matters
-- because the SQL Editor does not record applied files, so a project can be at
-- any point of this series — running this file alone must leave the database
-- correct rather than failing on a missing column.
--
-- What it does
--   1. adds `preferred_theme text` if the column is missing,
--   2. resets NULL / unknown values to the default,
--   3. sets the default and NOT NULL (idempotent),
--   4. replaces `users_preferred_theme_check` with the five-theme CHECK,
--   5. reloads the PostgREST schema cache so the API sees the column.
--
-- No other column, table, policy, grant or row is touched: the only write is
-- `preferred_theme` on rows that held no valid value.
--
-- How to run
--   Dashboard : SQL Editor -> paste this file -> Run
--   CLI       : supabase db push      (this file is a CLI migration)
--
-- Safe to re-run: every statement is idempotent.
-- ============================================================================

begin;

-- ---------------------------------------------------------------------------
-- 1. The column.
--
-- `text` + a CHECK constraint rather than an enum: the approved identifiers
-- are stable strings that also live in public/js/dm-theme.js and
-- app/Services/ThemePreference.php, and adding the next theme stays a
-- one-line change instead of an enum migration.
--
-- Created here if 000001 never ran; otherwise a no-op.
-- ---------------------------------------------------------------------------
alter table public.users
    add column if not exists preferred_theme text;

-- ---------------------------------------------------------------------------
-- 2. Backfill.
--
-- Existing accounts get the default (the original Duka Mkononi design), and
-- any value outside the allow-list is reset, exactly like 000001 does. Only
-- `preferred_theme` is written.
-- ---------------------------------------------------------------------------
update public.users
   set preferred_theme = 'white-professional'
 where preferred_theme is null
    or preferred_theme not in (
        'white-professional',
        'royal-champagne-cream',
        'midnight-royal-gold',
        'emerald-bazaar',
        'oceanic-sapphire'
    );

-- ---------------------------------------------------------------------------
-- 3. Default and NOT NULL (idempotent — setting them again is harmless).
--
-- NOTE: on a very large `users` table `set not null` is validated while
-- holding a lock. For a large table, add the constraint below with
-- `not valid` and run
--   alter table public.users validate constraint users_preferred_theme_check;
-- afterwards (that validation only takes SHARE UPDATE EXCLUSIVE). Not needed
-- for a typical users table.
-- ---------------------------------------------------------------------------
alter table public.users
    alter column preferred_theme set default 'white-professional';

alter table public.users
    alter column preferred_theme set not null;

-- ---------------------------------------------------------------------------
-- 4. The allow-list.
--
-- The CHECK is the database-level twin of App\Services\ThemePreference: with
-- it in place, no client, PostgREST call or SQL Editor can store a theme the
-- stylesheet cannot render. Dropped first so it can be replaced whether the
-- database was at three, four or zero themes.
-- ---------------------------------------------------------------------------
alter table public.users
    drop constraint if exists users_preferred_theme_check;

alter table public.users
    add constraint users_preferred_theme_check
    check (
        preferred_theme in (
            'white-professional',
            'royal-champagne-cream',
            'midnight-royal-gold',
            'emerald-bazaar',
            'oceanic-sapphire'
        )
    );

commit;

-- ---------------------------------------------------------------------------
-- 5. Make PostgREST aware of the column and constraint.
--
-- PostgREST reads the schema at boot, so without this reload the API keeps
-- answering `PGRST204: Could not find the 'preferred_theme' column` (a 400
-- from PUT /api/user/theme) until the project restarts. The NOTIFY must run
-- after the DDL has committed, hence outside the transaction above; the short
-- sleep covers the known race where the notification is processed before the
-- new column is visible to the listener. If the column is still unknown
-- afterwards, re-run these two lines once.
-- ---------------------------------------------------------------------------
select pg_sleep(1);
notify pgrst, 'reload schema';

-- ---------------------------------------------------------------------------
-- 6. Verify.
--
-- The column and constraint are guaranteed by the statements above, so these
-- reads cannot fail on a missing column the way a "widen only" migration did
-- when it was run before 000001.
-- ---------------------------------------------------------------------------
select column_name, data_type, column_default, is_nullable
  from information_schema.columns
 where table_schema = 'public'
   and table_name = 'users'
   and column_name = 'preferred_theme';

select pg_get_constraintdef(c.oid) as definition
  from pg_constraint c
  join pg_class t on t.oid = c.conrelid
  join pg_namespace n on n.oid = t.relnamespace
 where n.nspname = 'public'
   and t.relname = 'users'
   and c.conname = 'users_preferred_theme_check';

select preferred_theme, count(*) as accounts
  from public.users
 group by preferred_theme
 order by accounts desc;

-- ---------------------------------------------------------------------------
-- Rollback (run manually; there is no automatic down-migration here).
--
-- Back to four themes:
--   update public.users set preferred_theme = 'white-professional'
--    where preferred_theme = 'oceanic-sapphire';
--   alter table public.users drop constraint if exists users_preferred_theme_check;
--   alter table public.users add constraint users_preferred_theme_check
--       check (preferred_theme in ('white-professional',
--                                  'royal-champagne-cream',
--                                  'midnight-royal-gold',
--                                  'emerald-bazaar'));
--
-- Back to no theme preference at all (only if 000001 was never applied):
--   alter table public.users drop constraint if exists users_preferred_theme_check;
--   alter table public.users alter column preferred_theme drop default;
--   alter table public.users drop column if exists preferred_theme;
-- ---------------------------------------------------------------------------

-- ============================================================================
-- Notes
-- ============================================================================
-- Row Level Security: nothing to change. The `users` table keeps the policies
-- it already has, and adding a column does not alter them. Every call this app
-- makes goes through App\Services\Supabase, which authenticates with the
-- service-role key (see authHeaders()), so RLS is bypassed for the API anyway —
-- which is what lets PUT /api/user/theme update the caller's own row.
--
-- Index: deliberately none. `preferred_theme` is only ever read as part of the
-- single-row lookup by users.id (already a primary key), so an index here would
-- cost writes and never be used.
-- ============================================================================
