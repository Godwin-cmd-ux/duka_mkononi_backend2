-- =====================================================================
-- updated_at maintenance for the new tables
-- =====================================================================
-- Handlers set updated_at explicitly (like the existing controllers), but a
-- trigger guarantees it is never stale even if a future write path forgets.
-- Only the tables created by these migrations are touched — existing tables
-- keep their current behaviour.
-- =====================================================================

create or replace function public.duka_set_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = now();
    return new;
end;
$$;

do $$
declare
    t text;
begin
    foreach t in array array['inquiries', 'customer_reviews', 'orders']
    loop
        execute format('drop trigger if exists duka_set_updated_at on public.%I', t);
        execute format(
            'create trigger duka_set_updated_at before update on public.%I '
            'for each row execute function public.duka_set_updated_at()', t
        );
    end loop;
end $$;

select 'updated_at triggers ready' as result;
