<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    | Keys are read server-side only. Never expose these to the mobile/web
    | client; every AI call is proxied through the Laravel API.
    */
    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
    'api_key' => env('GEMINI_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Shared provider service defaults
    |--------------------------------------------------------------------------
    | Every capability goes through App\Services\Ai\GeminiClient, so timeouts,
    | retries, output limits and prompt-size guards live here once instead of
    | being duplicated per capability.
    */
    'enabled' => env('AI_ENABLED', true),
    'timeout' => (int) env('AI_TIMEOUT', 45),
    'retries' => (int) env('AI_MAX_RETRIES', 3),
    'retry_base_delay_ms' => (int) env('AI_RETRY_BASE_DELAY_MS', 800),
    'retry_max_delay_ms' => (int) env('AI_RETRY_MAX_DELAY_MS', 8000),
    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 4096),
    // Thinking models spend part of the output budget before writing the answer,
    // so requests for fewer tokens are raised to this floor. Without it a small
    // budget silently truncates the JSON and surfaces as "invalid JSON".
    'min_output_tokens' => (int) env('AI_MIN_OUTPUT_TOKENS', 1024),
    'temperature' => (float) env('AI_TEMPERATURE', 0.2),
    // Hard ceiling on the characters we will ever send to the provider in one
    // request. Larger prompts are rejected before any network call is made.
    'max_prompt_chars' => (int) env('AI_MAX_PROMPT_CHARS', 120000),

    /*
    |--------------------------------------------------------------------------
    | Language + cost
    |--------------------------------------------------------------------------
    */
    'locales' => ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'],
    'default_locale' => env('AI_DEFAULT_LOCALE', 'sw'),
    'currency' => env('AI_CURRENCY', 'TZS'),

    // Optional token pricing (per 1,000,000 tokens). Left at 0 so cost is
    // reported as 0 unless an operator opts in with real figures.
    'cost' => [
        'input_per_million' => (float) env('AI_COST_INPUT_PER_MILLION', 0),
        'output_per_million' => (float) env('AI_COST_OUTPUT_PER_MILLION', 0),
        'currency' => env('AI_COST_CURRENCY', 'USD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Capability feature flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'price_advisor' => env('AI_FEATURE_PRICE_ADVISOR', true),
        'restock' => env('AI_FEATURE_RESTOCK', true),
        'reports' => env('AI_FEATURE_REPORTS', true),
        'health' => env('AI_FEATURE_HEALTH', true),
        // Capability #6 - Anomaly & Leak Detection (rule-based, manager review).
        'anomalies' => env('AI_FEATURE_ANOMALIES', true),
        // Capability #13 - Order & Delivery Q&A (grounded in recorded orders).
        'order_qa' => env('AI_FEATURE_ORDER_QA', true),
        // Capability #7 - Smart Expense Categorisation (NL -> structured expense).
        'expense_parse' => env('AI_FEATURE_EXPENSE_PARSE', true),
        // Capability #8 - Advertisement & Product-Copy Generator (draft only).
        'ad_copy' => env('AI_FEATURE_AD_COPY', true),
        // Capability #9 - Customer-Reply Drafting (draft only; the seller sends).
        'reply_draft' => env('AI_FEATURE_REPLY_DRAFT', true),
        // Capability #10 - Seller Copilot Q&A (multi-turn, read-only tools).
        'copilot' => env('AI_FEATURE_COPILOT', true),
        // Capability #11 - Conversational Shopping Assistant (customer side;
        // public catalogue only; deterministic parse, optional model assist).
        'shopping_assistant' => env('AI_FEATURE_SHOPPING_ASSISTANT', true),
        // Capability #12 - Business & Review Summarisation (customer side;
        // published reviews + public business records only; deterministic
        // stats with optional model rewording).
        'business_summary' => env('AI_FEATURE_BUSINESS_SUMMARY', true),
        // Capability #14 - Personalised Recommendations (customer side;
        // public catalogue + the caller's own history only; deterministic
        // ranking with real-signal explanations and cold-start fallback).
        'recommendations' => env('AI_FEATURE_RECOMMENDATIONS', true),

        // Capability #15 - AI Report Summaries (grounded explanations for the
        // manager reporting screens).
        'report_summary' => env('AI_FEATURE_REPORT_SUMMARY', true),

        // Capability #16 - Advertisement & Content Review Assistant (staff
        // moderation aid; the decision is always a recorded human one).
        'reviews' => env('AI_FEATURE_REVIEWS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Personalised recommendations (capability #14)
    |--------------------------------------------------------------------------
    | Ranking is deterministic; these bound the data it may look at: only the
    | caller's own recent orders and likes for personalisation, and a bounded
    | recent window of rows for aggregate popularity counts (counts only -
    | never individual customer rows). Dismissals expire after `dismiss_ttl_days`
    | and are capped at `dismiss_max` per account (data minimisation).
    */
    'recommendations' => [
        'max_products' => (int) env('AI_RECO_MAX_PRODUCTS', 12),
        'max_businesses' => (int) env('AI_RECO_MAX_BUSINESSES', 6),
        'history_orders' => (int) env('AI_RECO_HISTORY_ORDERS', 50),
        'popularity_rows' => (int) env('AI_RECO_POPULARITY_ROWS', 2000),
        'dismiss_ttl_days' => (int) env('AI_RECO_DISMISS_TTL_DAYS', 90),
        'dismiss_max' => (int) env('AI_RECO_DISMISS_MAX', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anomaly & leak detection (capability #6)
    |--------------------------------------------------------------------------
    | Detection itself is deterministic: these thresholds live here (not in
    | the model), so a rule always means the same thing. Two adjacent windows
    | of `compare_days` each are compared (recent vs prior); `min_*` values are
    | the evidence floors below which we stay silent rather than guess.
    */
    'anomalies' => [
        'compare_days' => (int) env('AI_ANOMALY_COMPARE_DAYS', 14),
        // Percentage-point drop in gross margin that counts as meaningful.
        'margin_drop_pp' => (float) env('AI_ANOMALY_MARGIN_DROP_PP', 10.0),
        // Minimum completed sale lines in each window before margin is compared.
        'min_margin_lines' => (int) env('AI_ANOMALY_MIN_MARGIN_LINES', 3),
        // Recent expenses must reach this multiple of the prior window.
        'expense_spike_ratio' => (float) env('AI_ANOMALY_EXPENSE_RATIO', 2.0),
        // ...and differ by at least this absolute amount before it is flagged.
        'expense_spike_min_abs' => (float) env('AI_ANOMALY_EXPENSE_MIN_ABS', 10000),
        // Upper bound on alerts returned in one scan.
        'max_alerts' => (int) env('AI_ANOMALY_MAX_ALERTS', 20),
        // AI explanations are cached per alert set for this long (cost guard).
        'explain_ttl_minutes' => (int) env('AI_ANOMALY_EXPLAIN_TTL', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Business Health Score (capability #5)
    |--------------------------------------------------------------------------
    | The score is assembled from four documented components. Weights sum to 1.
    | When a component has no evidence its weight is removed and the remaining
    | weights are renormalised; if the covered weight falls below min_coverage
    | (or fewer than min_components are present) NO score is produced at all,
    | so we never label a business from insufficient evidence.
    |
    | Each component maps its measured value to 0-100 through `thresholds`, an
    | ordered list of [value, score] breakpoints (values beyond the ends are
    | clamped). Sales trend is scored directly from the period-over-period change
    | percentage using `trend_breakpoints` in the same [value, score] shape.
    */
    'health' => [
        'window_days' => (int) env('AI_HEALTH_WINDOW_DAYS', 7),
        'min_coverage' => (float) env('AI_HEALTH_MIN_COVERAGE', 0.6),
        'min_components' => (int) env('AI_HEALTH_MIN_COMPONENTS', 2),
        'max_actions' => (int) env('AI_HEALTH_MAX_ACTIONS', 3),
        'weights' => [
            'margin' => 0.30,
            'stock_cover' => 0.25,
            'sales_trend' => 0.25,
            'expense_ratio' => 0.20,
        ],
        // Target days of cover a shop should hold (lead + safety).
        'target_cover_days' => (float) env('AI_HEALTH_TARGET_COVER_DAYS', 14),
        // Ordered [value, score] breakpoints; higher value -> higher score.
        'thresholds' => [
            // Gross margin percentage.
            'margin' => [[0, 0], [3, 30], [8, 55], [15, 75], [25, 90], [40, 100]],
            // Expense-to-revenue percentage (lower is better -> inverted below).
            'expense_ratio' => [[0, 100], [10, 90], [20, 75], [35, 55], [50, 30], [80, 0]],
            // Period-over-period revenue change percentage.
            'sales_trend' => [[-40, 0], [-15, 30], [-5, 50], [0, 60], [10, 80], [25, 95], [50, 100]],
        ],
        // A component's measured value is treated as strong/steady/watch/attention.
        'bands' => [
            'strong' => 80,
            'steady' => 60,
            'watch' => 40,
        ],
        // A change must move at least this percentage to be reported as meaningful.
        'meaningful_change_pct' => (float) env('AI_HEALTH_MEANINGFUL_CHANGE_PCT', 5.0),

        /*
        | Configurable delivery. Only in-app (stored + reviewable) delivery is
        | implemented; the scheduler runs on the configured day/time and only
        | when `enabled` is true. `locales` are the languages a digest is stored
        | in (the seller's own `users.language` is preferred per member).
        */
        'delivery' => [
            'enabled' => env('AI_HEALTH_DELIVERY_ENABLED', true),
            'channels' => array_values(array_filter(explode(',', (string) env('AI_HEALTH_DELIVERY_CHANNELS', 'in_app')))),
            'day_of_week' => (int) env('AI_HEALTH_DELIVERY_DAY', 1), // Monday
            'time' => (string) env('AI_HEALTH_DELIVERY_TIME', '06:00'),
            'max_businesses' => (int) env('AI_HEALTH_MAX_BUSINESSES', 500),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Restock planning defaults
    |--------------------------------------------------------------------------
    | Lead time, safety stock and the review period decide how many days of
    | demand a replenishment order must cover. Overridable per request.
    */
    'restock' => [
        'lead_time_days' => (int) env('AI_RESTOCK_LEAD_TIME_DAYS', 7),
        'safety_days' => (int) env('AI_RESTOCK_SAFETY_DAYS', 7),
        'review_days' => (int) env('AI_RESTOCK_REVIEW_DAYS', 7),
        'overstock_days' => (int) env('AI_RESTOCK_OVERSTOCK_DAYS', 60),
        'fast_moving_daily' => (float) env('AI_RESTOCK_FAST_MOVING_DAILY', 1.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Smart expense categorisation (capability #7)
    |--------------------------------------------------------------------------
    | Amounts, dates and keyword categories are pulled from the seller's own
    | words by trusted code; the model is consulted only to tidy the
    | description and to choose from the fixed category allow-list when the
    | keyword table has no confident match. The model can never introduce an
    | amount the text does not contain.
    */
    'expense_parse' => [
        // Hard cap on the free-text note accepted for parsing.
        'max_text' => (int) env('AI_EXPENSE_MAX_TEXT', 600),
        // Look-back window in days used to warn about a likely duplicate.
        'duplicate_window_days' => (int) env('AI_EXPENSE_DUP_WINDOW_DAYS', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Advertisement & product-copy studio (capability #8)
    |--------------------------------------------------------------------------
    | Hard length caps validated AFTER generation (never trusted from the
    | model). Copy that exceeds a cap is clipped at a sentence/word boundary
    | and flagged, so the seller sees exactly what they are about to publish.
    */
    'ad_copy' => [
        'max_headline' => (int) env('AI_AD_MAX_HEADLINE', 90),
        'max_description' => (int) env('AI_AD_MAX_DESCRIPTION', 480),
        'max_product_description' => (int) env('AI_AD_MAX_PRODUCT_DESC', 700),
        'max_promo' => (int) env('AI_AD_MAX_PROMO', 240),
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer-reply drafting (capability #9)
    |--------------------------------------------------------------------------
    | A reply is a personal message, not marketing copy: the cap is validated
    | AFTER generation (never trusted from the model) and the draft is clipped
    | at a sentence boundary. Promise/claim guards and duplicate detection
    | live in ReplyDrafter so they are unit-testable.
    */
    'reply_draft' => [
        'max_chars' => (int) env('AI_REPLY_MAX_CHARS', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Seller Copilot Q&A (capability #10)
    |--------------------------------------------------------------------------
    | The copilot is multi-turn but bounded on purpose: `store_messages` caps
    | what is persisted per conversation, `history_turns` caps what the model
    | sees, and `retention_days` is the deletion window (pruned on read).
    | When the conversation table is unavailable the context falls back to
    | `cache_hours` of cache and the response says so instead of pretending
    | history is durable.
    */
    'copilot' => [
        'max_question' => (int) env('AI_COPILOT_MAX_QUESTION', 400),
        'store_messages' => (int) env('AI_COPILOT_STORE_MESSAGES', 40),
        'history_turns' => (int) env('AI_COPILOT_HISTORY_TURNS', 3),
        'retention_days' => (int) env('AI_COPILOT_RETENTION_DAYS', 90),
        'cache_hours' => (int) env('AI_COPILOT_CACHE_HOURS', 24),
        'list_limit' => (int) env('AI_COPILOT_LIST_LIMIT', 20),
        'max_payload_chars' => (int) env('AI_COPILOT_MAX_PAYLOAD_CHARS', 6000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversational Shopping Assistant (capability #11)
    |--------------------------------------------------------------------------
    | Public-catalogue only: every row the assistant returns already passed
    | PublicCatalog's visibility and availability rules. Parsing is
    | deterministic by default; the model may only PROPOSE the same
    | allow-listed parse shape when the plain parser finds nothing usable.
    | `memory_minutes` is how long ONE remembered turn lasts for a
    | signed-in shopper (cache only - nothing durable is stored).
    */
    'assistant' => [
        'max_query' => (int) env('AI_SHOP_MAX_QUERY', 240),
        'result_limit' => (int) env('AI_SHOP_RESULT_LIMIT', 12),
        'business_limit' => (int) env('AI_SHOP_BUSINESS_LIMIT', 5),
        'suggest_limit' => (int) env('AI_SHOP_SUGGEST_LIMIT', 8),
        'memory_minutes' => (int) env('AI_SHOP_MEMORY_MINUTES', 30),
        'max_payload_chars' => (int) env('AI_SHOP_MAX_PAYLOAD_CHARS', 6000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Business & Review Summarisation (capability #12)
    |--------------------------------------------------------------------------
    | Summaries are cached per locale with version-key invalidation (review
    | publish/unpublish, business certify/uncertify). `max_reviews` bounds the
    | published corpus that is analysed, `max_orders_scanned` bounds the
    | aggregate activity scan, and the theme controls set the document-
    | frequency floor for what counts as a recurring theme.
    */
    'trust' => [
        'cache_minutes' => (int) env('AI_TRUST_CACHE_MINUTES', 15),
        'max_reviews' => (int) env('AI_TRUST_MAX_REVIEWS', 100),
        'max_orders_scanned' => (int) env('AI_TRUST_MAX_ORDERS', 500),
        'theme_min_reviews' => (int) env('AI_TRUST_THEME_MIN', 2),
        'theme_limit' => (int) env('AI_TRUST_THEME_LIMIT', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Report Summaries (capability #15)
    |--------------------------------------------------------------------------
    | The summary is deterministic (PHP over the existing report queries);
    | these only bound it: the longest window a caller may request, and how
    | long the finished payload is cached per business + period + locale.
    */
    'report_summary' => [
        'max_days' => (int) env('AI_REPORT_MAX_DAYS', 366),
        'cache_minutes' => (int) env('AI_REPORT_CACHE_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content review policy (capability #16)
    |--------------------------------------------------------------------------
    | The deterministic moderation checks run against this configurable
    | marketplace policy. A term hit is an OBJECTIVE finding (a human still
    | decides); claim keywords are hardcoded concerns in the service. The AI
    | classification is advisory only and can be switched off entirely.
    */
    'reviews' => [
        'min_description_length' => (int) env('AI_REVIEW_MIN_DESC', 20),
        'max_title_length' => (int) env('AI_REVIEW_MAX_TITLE', 80),
        'max_price_multiplier' => (float) env('AI_REVIEW_MAX_MARKUP', 20),
        'block_contact_details' => (bool) env('AI_REVIEW_BLOCK_CONTACT', true),
        'prohibited_terms' => env('AI_REVIEW_PROHIBITED_TERMS')
            ? array_values(array_filter(array_map('trim', explode(',', (string) env('AI_REVIEW_PROHIBITED_TERMS')))))
            : App\Services\Ai\ReviewAssistant::PROHIBITED_TERMS,
        'ai_classify' => (bool) env('AI_REVIEW_AI_CLASSIFY', true),
        'max_items' => (int) env('AI_REVIEW_MAX_ITEMS', 200),
        'cache_minutes' => (int) env('AI_REVIEW_CACHE_MINUTES', 5),
    ],
];
