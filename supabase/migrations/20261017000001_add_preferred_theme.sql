-- ============================================================================
-- Duka Mkononi — theme preference (Supabase / PostgreSQL)
-- ============================================================================
--
-- Adds `preferred_theme` to `public.users`, the column the API already reads
-- and writes:
--
--   read    AuthController::login()          -> $user->preferred_theme
--   write   PUT /api/user/theme              -> ProfileController::theme()
--                                             User::where('id',...)->update(...)
--   guard   App\Services\ThemePreference     -> allow-list (server-side)
--
-- SQL equivalent of database/migrations/2026_10_17_000001_add_preferred_theme_
-- to_users_table.php. The Laravel migration cannot reach this project: the app
-- talks to Supabase over PostgREST (App\Services\Supabase, App\Models\User ->
-- table `users`), so the column has to be created here.
--
-- How to run
--   Dashboard : SQL Editor -> paste this file -> Run
--   CLI       : supabase db push      (this file is a CLI migration)
--
-- Safe to re-run: every statement is guarded, no other column is touched, and
-- no row other than `preferred_theme` is modified.
-- ============================================================================

begin;

-- ---------------------------------------------------------------------------
-- 1. The column.
--
-- `text` + a CHECK constraint rather than an enum: the approved identifiers are
-- stable strings that also live in public/js/dm-theme.js and
-- app/Services/ThemePreference.php, and adding a fourth theme later is then a
-- one-line change instead of an enum migration.
-- ---------------------------------------------------------------------------
alter table public.users
    add column if not exists preferred_theme text;

-- ---------------------------------------------------------------------------
-- 2. Backfill.
--
-- Existing accounts get the default (the original Duka Mkononi design), and any
-- value outside the allow-list is reset, exactly like the Laravel migration
-- does. Only `preferred_theme` is written.
-- ---------------------------------------------------------------------------
update public.users
   set preferred_theme = 'white-professional'
 where preferred_theme is null
    or preferred_theme not in (
        'white-professional',
        'royal-champagne-cream',
        'midnight-royal-gold'
    );

-- ---------------------------------------------------------------------------
-- 3. Default, NOT NULL and the allow-list.
--
-- The CHECK is the database-level twin of App\Services\ThemePreference: with it
-- in place, no client, PostgREST call or SQL Editor can store a theme the
-- stylesheet cannot render.
--
-- NOTE: on a very large `users` table the constraint is validated while holding
-- an ACCESS EXCLUSIVE lock. To avoid that, add it with `not valid` and then run
-- `alter table public.users validate constraint users_preferred_theme_check;`
-- (that validation only takes SHARE UPDATE EXCLUSIVE). Not needed for a
-- typical users table.
-- ---------------------------------------------------------------------------
alter table public.users
    alter column preferred_theme set default 'white-professional';

alter table public.users
    alter column preferred_theme set not null;

do $$
begin
    if not exists (
        select 1
          from pg_constraint
         where conrelid = 'public.users'::regclass
           and conname = 'users_preferred_theme_check'
    ) then
        alter table public.users
            add constraint users_preferred_theme_check
            check (
                preferred_theme in (
                    'white-professional',
                    'royal-champagne-cream',
                    'midnight-royal-gold'
                )
            );
    end if;
end $$;

commit;

-- ---------------------------------------------------------------------------
-- 4. Make PostgREST aware of the new column.
--
-- PostgREST reads the schema at boot, so without this reload the API keeps
-- answering `PGRST204: Could not find the 'preferred_theme' column` (a 400 from
-- PUT /api/user/theme) until the project restarts. The NOTIFY must run after
-- the DDL has committed, hence outside the transaction above; the short sleep
-- covers the known race where the notification is processed before the new
-- column is visible to the listener. If the column is still unknown afterwards,
-- re-run these two lines once.
-- ---------------------------------------------------------------------------
select pg_sleep(1);
notify pgrst, 'reload schema';

-- ---------------------------------------------------------------------------
-- 5. Verify.
-- ---------------------------------------------------------------------------
select column_name, data_type, column_default, is_nullable
  from information_schema.columns
 where table_schema = 'public'
   and table_name = 'users'
   and column_name = 'preferred_theme';

select preferred_theme, count(*) as accounts
  from public.users
 group by preferred_theme
 order by accounts desc;

-- ---------------------------------------------------------------------------
-- Rollback (run manually; there is no automatic down-migration here).
-- ---------------------------------------------------------------------------
-- alter table public.users drop constraint if exists users_preferred_theme_check;
-- alter table public.users alter column preferred_theme drop default;
-- alter table public.users drop column if exists preferred_theme;

-- ============================================================================
-- Notes
-- ============================================================================
-- Row Level Security: nothing to change. The `users` table keeps the policies it
-- already has, and adding a column does not alter them. Every call this app
-- makes goes through App\Services\Supabase, which authenticates with the
-- service-role key (see authHeaders()), so RLS is bypassed for the API anyway —
-- which is what lets PUT /api/user/theme update the caller's own row.
--
-- Index: deliberately none. `preferred_theme` is only ever read as part of the
-- single-row lookup by users.id (already a primary key), so an index here would
-- cost writes and never be used.
