-- ============================================================================
-- Stock ownership: every INCREASE of business stock has an authorized receipt.
--
--   products.stock = Business Stock
--   Business Stock = Outside Stock + SUM(fridge_stock.quantity)
--
-- Sellers may sell stock and move stock between Outside and Fridges, but they
-- can never create Business Stock. Only an approved business administrator
-- may call receive_product_stock(), which locks the product, increments stock
-- and writes an append-only stock_receipts row in one transaction.
--
-- The PHP API also checks the role, but this function re-checks it so a caller
-- who reaches PostgREST directly (or a future endpoint that forgets the check)
-- still cannot mint inventory.
-- ============================================================================

create table if not exists public.stock_receipts (
    id                  uuid primary key default gen_random_uuid(),
    business_id         uuid not null references public.businesses (id) on delete cascade,
    branch_id           uuid references public.branches (id) on delete set null,
    product_id          uuid not null references public.products (id) on delete cascade,
    quantity            integer not null,
    stock_before        integer not null,
    stock_after         integer not null,
    source              text not null default 'manual',
    receiving_reference text not null,
    notes               text,
    received_by         uuid references public.users (id) on delete set null,
    created_at          timestamptz not null default now(),
    constraint stock_receipts_quantity_check check (quantity > 0),
    constraint stock_receipts_balance_check check (stock_after = stock_before + quantity),
    constraint stock_receipts_source_check check (source in ('manual', 'scanner', 'ai_import')),
    constraint stock_receipts_reference_unique unique (business_id, receiving_reference)
);

create index if not exists stock_receipts_product_created_idx
    on public.stock_receipts (product_id, created_at desc);

create index if not exists stock_receipts_business_created_idx
    on public.stock_receipts (business_id, created_at desc);

create index if not exists stock_receipts_branch_created_idx
    on public.stock_receipts (branch_id, created_at desc)
    where branch_id is not null;

-- Barcode lookup: a scanner asks for one code inside one business/branch.
-- This index is only for lookup performance; uniqueness is enforced by the
-- API's ambiguity rule because products from different branches may legitimately
-- share a manufacturer barcode.
create index if not exists products_barcode_lookup_idx
    on public.products (lower(btrim(barcode)))
    where barcode is not null and btrim(barcode) <> '';

create or replace function public.receive_product_stock(
    p_product_id uuid,
    p_business_id uuid,
    p_branch_id uuid,
    p_quantity integer,
    p_user_id uuid,
    p_source text default 'manual',
    p_reference text default null,
    p_notes text default null
) returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_business record;
    v_product record;
    v_stock_before integer;
    v_stock_after integer;
    v_source text := lower(btrim(coalesce(p_source, 'manual')));
    v_reference text;
begin
    if p_quantity is null or p_quantity <= 0 then
        raise exception 'Kiasi cha stock kinapaswa kuwa namba nzima kubwa kuliko sifuri';
    end if;

    if v_source not in ('manual', 'scanner', 'ai_import') then
        raise exception 'Chanzo cha kupokea stock si sahihi';
    end if;

    select * into v_business
      from public.businesses
     where id = p_business_id;
    if not found then
        raise exception 'Biashara haipatikani';
    end if;

    -- The actor must be an approved administrator of THIS business. The
    -- legacy business_name fallback keeps pre-business_id admin rows working.
    if not exists (
        select 1
          from public.users u
         where u.id = p_user_id
           and u.status = 'approved'
           and u.role in ('admin', 'super_admin', 'system_admin')
           and (
                u.business_id = p_business_id
                or lower(btrim(coalesce(u.business_name, ''))) = lower(btrim(coalesce(v_business.business_name, '')))
           )
    ) then
        raise exception 'Huna ruhusa ya kupokea stock kwenye biashara hii'
            using errcode = '42501';
    end if;

    if v_business.business_structure = 'branch' and p_branch_id is null then
        raise exception 'Chagua tawi kabla ya kupokea stock';
    end if;

    if v_business.business_structure <> 'branch' and p_branch_id is not null then
        raise exception 'Biashara hii haina matawi';
    end if;

    select p.* into v_product
      from public.products p
      join public.users seller on seller.id = p.seller_id
     where p.id = p_product_id
       and p.is_active = true
       and seller.status = 'approved'
       and (
            seller.business_id = p_business_id
            or lower(btrim(coalesce(seller.business_name, ''))) = lower(btrim(coalesce(v_business.business_name, '')))
       )
     for update of p;
    if not found then
        raise exception 'Bidhaa haipatikani kwenye biashara hii';
    end if;

    -- Branch matching is exact. A NULL product in a branch business is a
    -- legacy row, not shared stock, and must not be silently stamped.
    if p_branch_id is not null and v_product.branch_id is distinct from p_branch_id then
        raise exception 'Bidhaa ni ya tawi lingine';
    end if;
    if p_branch_id is null and v_product.branch_id is not null then
        raise exception 'Bidhaa ni ya tawi; chagua tawi lake kabla ya kupokea stock';
    end if;

    v_reference := nullif(btrim(coalesce(p_reference, '')), '');
    if v_reference is null then
        v_reference := 'RCV-' || to_char(now(), 'YYYYMMDD-HH24MISS')
            || '-' || upper(substr(replace(gen_random_uuid()::text, '-', ''), 1, 8));
    end if;

    v_stock_before := coalesce(v_product.stock, 0);
    v_stock_after := v_stock_before + p_quantity;

    update public.products
       set stock = v_stock_after,
           updated_at = now()
     where id = p_product_id;

    insert into public.stock_receipts (
        business_id, branch_id, product_id, quantity,
        stock_before, stock_after, source, receiving_reference,
        notes, received_by
    ) values (
        p_business_id, p_branch_id, p_product_id, p_quantity,
        v_stock_before, v_stock_after, v_source, v_reference,
        nullif(btrim(coalesce(p_notes, '')), ''), p_user_id
    );

    return jsonb_build_object(
        'success', true,
        'product_id', p_product_id,
        'business_id', p_business_id,
        'branch_id', p_branch_id,
        'quantity', p_quantity,
        'stock_before', v_stock_before,
        'stock_after', v_stock_after,
        'source', v_source,
        'receiving_reference', v_reference
    );
end;
$$;

revoke all on function public.receive_product_stock(uuid, uuid, uuid, integer, uuid, text, text, text) from public, anon, authenticated;
grant execute on function public.receive_product_stock(uuid, uuid, uuid, integer, uuid, text, text, text) to service_role;
