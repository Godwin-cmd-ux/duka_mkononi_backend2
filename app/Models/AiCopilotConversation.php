<?php

namespace App\Models;

/**
 * A stored seller-copilot conversation (capability #10).
 *
 * One row per conversation, owned by (business_id, user_id) - every read and
 * write in SellerCopilot filters on BOTH columns so a conversation can never
 * cross a business or staff boundary. `messages` is a bounded JSON array of
 * {role, content, tool, sub, at} entries, trimmed to ai.copilot.store_messages
 * on save; rows older than ai.copilot.retention_days are pruned on read.
 *
 * The table must exist in the project's database (columns: id, business_id,
 * user_id, messages, created_at, updated_at); when it is missing the copilot
 * falls back to a short-lived cache context and says so in the response instead
 * of pretending history is durable.
 */
class AiCopilotConversation extends SupabaseModel
{
    protected static $table = 'ai_copilot_conversations';
}
