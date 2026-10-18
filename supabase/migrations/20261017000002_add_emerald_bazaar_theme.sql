-- ============================================================================
-- Duka Mkononi — allow the Emerald Bazaar theme (Supabase / PostgreSQL)
-- ============================================================================
--
-- Extends the theme allow-list created by
--   supabase/migrations/20261017000001_add_preferred_theme.sql
-- with the fourth approved theme, `emerald-bazaar`.
--
-- Why a second migration instead of editing the first one: if 000001 has
-- already been applied (the SQL Editor does not record it, `supabase db push`
-- does), the CLI will not run it again. This file replaces the CHECK
-- constraint regardless, so the database accepts the new theme either way.
-- On a fresh project 000001 runs first and this file widens its constraint
-- from three values to four.
--
-- The column, its default, NOT NULL and every existing row are untouched:
-- only the constraint definition changes, and only values the app already
-- validates (App\Services\ThemePreference::ALLOWED) are admitted.
--
-- How to run
--   Dashboard : SQL Editor -> paste this file -> Run
--   CLI       : supabase db push      (this file is a CLI migration)
--
-- Safe to re-run: the constraint is dropped and re-added in one transaction.
-- ============================================================================

begin;

do $$
begin
    -- Guarded so the file is harmless if 000001 has not run yet (the column
    -- does not exist then; the earlier migration stays the one that creates
    -- it).
    if exists (
        select 1
          from information_schema.columns
         where table_schema = 'public'
           and table_name = 'users'
           and column_name = 'preferred_theme'
    ) then
        alter table public.users
            drop constraint if exists users_preferred_theme_check;

        alter table public.users
            add constraint users_preferred_theme_check
            check (
                preferred_theme in (
                    'white-professional',
                    'royal-champagne-cream',
                    'midnight-royal-gold',
                    'emerald-bazaar'
                )
            );
    end if;
end $$;

commit;

-- Make PostgREST pick up the new constraint metadata (same one-second race
-- workaround as 000001; the NOTIFY must run after the DDL committed).
select pg_sleep(1);
notify pgrst, 'reload schema';

-- ---------------------------------------------------------------------------
-- Verify: the constraint should list all four themes.
-- ---------------------------------------------------------------------------
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
-- Rollback (run manually): restore the three-theme constraint. Existing rows
-- that already use emerald-bazaar must be moved back first, or the constraint
-- validation fails:
--   update public.users set preferred_theme = 'white-professional'
--    where preferred_theme = 'emerald-bazaar';
--   alter table public.users drop constraint if exists users_preferred_theme_check;
--   alter table public.users add constraint users_preferred_theme_check
--       check (preferred_theme in ('white-professional',
--                                  'royal-champagne-cream',
--                                  'midnight-royal-gold'));
-- ---------------------------------------------------------------------------
