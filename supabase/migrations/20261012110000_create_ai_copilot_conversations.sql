-- =====================================================================
-- Seller Copilot conversations (capability #10)
-- =====================================================================
-- One row per conversation. Owned by (business_id, user_id): every read and
-- write in the Laravel copilot filters on BOTH columns, so a conversation can
-- never cross a business or a staff-member boundary.
--
-- `messages` is a bounded JSON array of entries:
--   {"role": "user"|"assistant", "content": "...", "tool": "...",
--    "sub": "...", "at": "ISO-8601"}
-- trimmed to ai.copilot.store_messages (default 40) on each save, and rows
-- untouched for ai.copilot.retention_days (default 90) are pruned on read.
-- Deleting a conversation is a hard delete (privacy/deletion control).
-- =====================================================================

create table if not exists public.ai_copilot_conversations (
    id          uuid primary key,
    business_id uuid not null references businesses(id) on delete cascade,
    user_id     uuid not null references users(id) on delete cascade,
    title       text not null default '',
    messages    jsonb not null default '[]'::jsonb,
    created_at  timestamptz not null default now(),
    updated_at  timestamptz not null default now()
);

create index if not exists ai_copilot_conversations_owner_idx
    on public.ai_copilot_conversations (business_id, user_id, updated_at desc);

create index if not exists ai_copilot_conversations_created_idx
    on public.ai_copilot_conversations (created_at desc);

-- Report progress to the SQL editor.
select 'ai_copilot_conversations ready' as result;
