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

        // Capability #17 - Team Activity Digest (manager-facing summary of the
        // business's own activity log; never a performance assessment).
        'team_activity' => env('AI_FEATURE_TEAM_ACTIVITY', true),

        // Capability #18 - Inquiry Triage & Auto-Draft Replies (Super Admin
        // only; classification/routing/draft are composed from the approved
        // help content below, and nothing is sent without human approval
        // unless the narrow auto-send policy is explicitly enabled).
        'inquiries' => env('AI_FEATURE_INQUIRIES', true),

        // Capability #19 - Certification & Onboarding Assistant. The
        // certification review is an evidence checklist for Super Admins (it
        // never certifies anything); the onboarding guide walks a signed-in user
        // through setup with approved content in their own language.
        'certification' => env('AI_FEATURE_CERTIFICATION', true),
        'onboarding' => env('AI_FEATURE_ONBOARDING', true),

        // Capability #20 - Natural-Language Admin Analytics (system admins only).
        // The question selects one allow-listed read-only metric; the model may
        // only pick from that list and write one acknowledgement sentence. No SQL
        // is ever generated and the reads are bounded below.
        'admin_analytics' => env('AI_FEATURE_ADMIN_ANALYTICS', true),

        // Capability #21 - Public AI Concierge. The only PUBLIC AI surface:
        // it answers from the approved public knowledge base and the public
        // catalogue only, is throttled per IP per minute AND per hour, and can
        // never reach private data or the platform's internals.
        'concierge' => env('AI_FEATURE_CONCIERGE', true),

        // Capability #23 - Voice Input & Voice Answers. Speech runs on the
        // platform itself (the browser's Web Speech API + SpeechSynthesis, and
        // the device's own text-to-speech on mobile), so NO provider credential
        // is required and no audio ever reaches our server. This flag turns the
        // voice controls off; `ai.speech.*` records which locales have VERIFIED
        // coverage - the clients must never claim more than that.
        'voice' => env('AI_FEATURE_VOICE', true),

        // Capability #24 - Semantic & Typo-Tolerant Search. Upgrades the plain
        // substring match on the public catalogue to a ranked hybrid: exact
        // phrase, exact token, prefix, synonym/transliteration expansion and a
        // bounded fuzzy pass, with the existing public-visibility, business and
        // availability filters still applied. Embeddings are OPTIONAL and off
        // by default (see ai.search.embeddings) - the lexical path must always
        // work on its own.
        'search' => env('AI_FEATURE_SEARCH', true),

        // Capability #25 - Schema-Free Natural-Language Data Entry. One sentence
        // describes ONE business operation; the preview is read-only and the
        // confirmed record is written through the existing endpoint for that
        // type. See ai.entry below for the tunables and the guard rails.
        'nl_entry' => env('AI_FEATURE_NL_ENTRY', true),
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

    /*
    |--------------------------------------------------------------------------
    | Team Activity Digest (capability #17)
    |--------------------------------------------------------------------------
    | Built from the existing activity log (`user_logs`) of the caller's own
    | business members only. `max_rows` caps how many log rows are analysed per
    | window - the response states when the cap was hit instead of presenting a
    | partial window as complete - and `ai_narrate` switches the optional model
    | reword off entirely (the deterministic narrative always remains).
    */
    'team_activity' => [
        'max_rows' => (int) env('AI_ACTIVITY_MAX_ROWS', 4000),
        'noteworthy_limit' => (int) env('AI_ACTIVITY_NOTEWORTHY_LIMIT', 12),
        'history_limit' => (int) env('AI_ACTIVITY_HISTORY_LIMIT', 20),
        'cache_minutes' => (int) env('AI_ACTIVITY_CACHE_MINUTES', 5),
        'ai_narrate' => (bool) env('AI_ACTIVITY_NARRATE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inquiry triage & auto-draft replies (capability #18)
    |--------------------------------------------------------------------------
    | Classification, urgency and routing are deterministic (InquiryTriage); the
    | reply draft is COMPOSED from the approved help content - `help` replaces
    | the built-in approved answers with your own (JSON: {"en":{"help_reset":
    | "..."}, ...}) so support can review exactly what customers may be told.
    | `ai_narrate` only allows the optional acknowledgement sentence and topic
    | selection (both gated). `auto_send` DEFAULTS TO FALSE: with it off every
    | reply needs an explicit human approval; with it on, ONLY a general,
    | low-urgency inquiry that needs no human may be answered automatically.
    */
    'inquiry_triage' => [
        'max_message_chars' => (int) env('AI_INQUIRY_MAX_MESSAGE_CHARS', 4000),
        'queue_limit' => (int) env('AI_INQUIRY_QUEUE_LIMIT', 100),
        'cache_minutes' => (int) env('AI_INQUIRY_CACHE_MINUTES', 10),
        'ai_narrate' => (bool) env('AI_INQUIRY_NARRATE', true),
        'auto_send' => (bool) env('AI_INQUIRY_AUTO_SEND', false),
        'help' => env('AI_INQUIRY_HELP_JSON')
            ? (json_decode((string) env('AI_INQUIRY_HELP_JSON'), true) ?: App\Services\Ai\InquiryTriage::HELP)
            : App\Services\Ai\InquiryTriage::HELP,
    ],

    /*
    |--------------------------------------------------------------------------
    | Certification review (capability #19)
    |--------------------------------------------------------------------------
    | The checklist is computed in PHP from real rows (`businesses.*`, the
    | business's own `users` rows, its products); `requirements` lets the
    | operator replace the documented requirement set with their own (JSON list of
    | {id, rule, severity, source, min_chars?}) without touching code. A
    | requirement whose rule or severity is unknown is DROPPED, never guessed.
    | There is no model in this capability, so there is nothing to tune for text
    | generation - and no setting can make the review approve anything.
    */
    'certification' => [
        'queue_limit' => (int) env('AI_CERTIFICATION_QUEUE_LIMIT', 100),
        // Bound for the bulk member/product reads used by the checklist. When the
        // bound is hit, the affected items are reported as "not read" instead of
        // being silently under-counted.
        'max_read_rows' => (int) env('AI_CERTIFICATION_MAX_READ_ROWS', 20000),
        'requirements' => env('AI_CERTIFICATION_REQUIREMENTS_JSON')
            ? (json_decode((string) env('AI_CERTIFICATION_REQUIREMENTS_JSON'), true) ?: App\Services\Ai\CertificationReview::REQUIREMENTS)
            : App\Services\Ai\CertificationReview::REQUIREMENTS,
    ],

    /*
    |--------------------------------------------------------------------------
    | Onboarding guide (capability #19)
    |--------------------------------------------------------------------------
    | The checklist is computed in PHP from the caller's own rows only. `kb`
    | replaces or corrects individual approved answers (JSON: {"en":
    | {"kb_password": "..."}, ...}) - the operator reviews exactly what a new
    | user may be told. `ai_narrate` only allows the optional acknowledgement
    | sentence and the topic narrowing (both gated); with it off the approved
    | answers are returned untouched.
    */
    'onboarding' => [
        'max_question_chars' => (int) env('AI_ONBOARDING_MAX_QUESTION_CHARS', 500),
        'cache_minutes' => (int) env('AI_ONBOARDING_CACHE_MINUTES', 10),
        'ai_narrate' => (bool) env('AI_ONBOARDING_NARRATE', true),
        'kb' => env('AI_ONBOARDING_KB_JSON')
            ? (json_decode((string) env('AI_ONBOARDING_KB_JSON'), true) ?: App\Services\Ai\OnboardingGuide::KB)
            : App\Services\Ai\OnboardingGuide::KB,
        'steps' => env('AI_ONBOARDING_STEPS_JSON')
            ? (json_decode((string) env('AI_ONBOARDING_STEPS_JSON'), true) ?: App\Services\Ai\OnboardingGuide::STEPS)
            : App\Services\Ai\OnboardingGuide::STEPS,
    ],

    /*
    |--------------------------------------------------------------------------
    | Natural-language admin analytics (capability #20)
    |--------------------------------------------------------------------------
    | Platform-wide, system-admin-only answers over the same trusted sources the
    | admin dashboard uses. Cost is bounded here: `max_rows` caps every row read
    | (a hit is REPORTED as `truncated`, never hidden), `max_range_days` clamps a
    | time range, `max_named_businesses` caps the only named list, and
    | `ai_narrate` allows only one sanitised acknowledgement sentence plus a
    | choice from the allow-listed tools. Set AI_FEATURE_ADMIN_ANALYTICS=false to
    | disable the capability entirely.
    */
    'admin_analytics' => [
        'max_question_chars' => (int) env('AI_ADMIN_ANALYTICS_MAX_QUESTION_CHARS', 300),
        'max_rows' => (int) env('AI_ADMIN_ANALYTICS_MAX_ROWS', 20000),
        'max_range_days' => (int) env('AI_ADMIN_ANALYTICS_MAX_RANGE_DAYS', 366),
        'max_named_businesses' => (int) env('AI_ADMIN_ANALYTICS_MAX_NAMED_BUSINESSES', 5),
        'presence_window_minutes' => (int) env('AI_ADMIN_ANALYTICS_PRESENCE_WINDOW_MINUTES', 5),
        'cache_minutes' => (int) env('AI_ADMIN_ANALYTICS_CACHE_MINUTES', 10),
        'ai_narrate' => (bool) env('AI_ADMIN_ANALYTICS_NARRATE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public AI Concierge (capability #21)
    |--------------------------------------------------------------------------
    | The visitor-facing assistant of the public pages. Every answer comes from
    | the approved knowledge base in PublicConcierge plus the same public
    | catalogue the shop pages render; `max_per_hour` is the per-IP budget that
    | sits on top of the route throttle, and `links` may only override the
    | already-published external links (the APK, email, phone and WhatsApp URLs
    | in the website footer/navbar).
    */
    'concierge' => [
        'max_question_chars' => (int) env('AI_CONCIERGE_MAX_QUESTION_CHARS', 300),
        'max_per_hour' => (int) env('AI_CONCIERGE_MAX_PER_HOUR', 60),
        'cache_minutes' => (int) env('AI_CONCIERGE_CACHE_MINUTES', 10),
        'ai_narrate' => (bool) env('AI_CONCIERGE_NARRATE', true),
        'links' => array_filter([
            'download_apk' => env('AI_CONCIERGE_DOWNLOAD_URL'),
            'contact_email' => env('AI_CONCIERGE_CONTACT_EMAIL'),
            'contact_phone' => env('AI_CONCIERGE_CONTACT_PHONE'),
            'contact_whatsapp' => env('AI_CONCIERGE_CONTACT_WHATSAPP'),
        ], static fn ($value): bool => is_string($value) && trim($value) !== ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Voice input & voice answers (capability #23)
    |--------------------------------------------------------------------------
    | The provider is 'platform': speech recognition and speech synthesis happen
    | on the visitor's own device (browser Web Speech API / SpeechSynthesis, or
    | the phone's built-in engine). That means:
    |   * there is NO speech API key anywhere - server or client;
    |   * no audio is recorded by us, sent to our server or retained;
    |   * language coverage is a property of the DEVICE, not of this app.
    | `*_verified_locales` lists the locales whose coverage the implementation is
    | actually built and tested for. A locale NOT listed is reported to clients
    | as `device_dependent` (never as supported), so the UI can degrade honestly
    | instead of claiming a language the device may not understand. An operator
    | who has verified more languages on their target devices can add them here.
    */
    'speech' => [
        'enabled' => (bool) env('AI_SPEECH_ENABLED', true),
        'provider' => env('AI_SPEECH_PROVIDER', 'platform'),
        'stt_verified_locales' => array_values(array_filter(array_map('trim', explode(',', (string) env('AI_SPEECH_STT_LOCALES', 'sw,en'))))),
        'tts_verified_locales' => array_values(array_filter(array_map('trim', explode(',', (string) env('AI_SPEECH_TTS_LOCALES', 'sw,en,fr,hi,es,ur,de,zh'))))),
        // A single spoken utterance is bounded: the client stops listening after
        // this many seconds, and the text it produces is capped before use.
        'max_seconds' => (int) env('AI_SPEECH_MAX_SECONDS', 30),
        'max_chars' => (int) env('AI_SPEECH_MAX_CHARS', 600),
        // Documented and enforced by design: we never record or keep audio.
        'retain_audio' => (bool) env('AI_SPEECH_RETAIN_AUDIO', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Semantic & typo-tolerant search (capability #24)
    |--------------------------------------------------------------------------
    | The catalogue search is a RANKED HYBRID that never leaves trusted code:
    |
    |   1. exact phrase (`brake pads`)         strongest
    |   2. exact token / prefix (`premio`)     strong
    |   3. synonym + transliteration expansion (mchele <-> rice, breki <-> brake)
    |   4. bounded fuzzy pass (spelling mistakes, one edit per short token)
    |
    | Every match carries the signals that produced it, so a result is always
    | explainable. Retrieval is bounded by `max_candidates` and results by
    | `max_results`; `min_score` drops weak noise. Existing filters still apply
    | on top: public visibility (PublicCatalog), business scope and availability.
    |
    | EMBEDDINGS (optional, versioned, OFF by default):
    |   The actual Supabase/PostgreSQL setup has no `pgvector` extension and no
    |   embedding column, and this runtime has no local embedding model - so the
    |   justified default is the lexical hybrid above. When an operator enables
    |   `embeddings.enabled`, the server may embed the query (key stays
    |   server-side) and blend cosine similarity with stored vectors from
    |   `ai_product_embeddings` (see the migration); until then `mode` is
    |   `lexical` and results are identical. `version` is written with every
    |   stored vector so a model change can be backfilled without mixing spaces.
    |   No model is ever downloaded to the mobile app.
    */
    'search' => [
        'enabled' => (bool) env('AI_SEARCH_ENABLED', true),
        // Bounded retrieval: at most this many candidate rows are scored.
        'max_candidates' => (int) env('AI_SEARCH_MAX_CANDIDATES', 2000),
        'max_results' => (int) env('AI_SEARCH_MAX_RESULTS', 24),
        // Below this score a row is noise rather than a match. Calibrated
        // against the field weights in ProductSearch: an exact name match scores
        // 0.55, a category-only or description-only match 0.15/0.12, while a
        // weak fuzzy hit on one description word lands under this floor and is
        // dropped. A row that matches nothing scores 0 and never appears.
        'min_score' => (float) env('AI_SEARCH_MIN_SCORE', 0.12),
        'fuzzy_enabled' => (bool) env('AI_SEARCH_FUZZY', true),
        // Edit budget is scaled by word length: one edit up to nine characters,
        // up to this many beyond that. This is what keeps "rice" from matching
        // "race" (which would surface ball bearings for a rice search).
        'fuzzy_max_distance' => (int) env('AI_SEARCH_FUZZY_MAX_DISTANCE', 2),
        // Words shorter than this never use the fuzzy pass at all.
        'fuzzy_min_token_length' => (int) env('AI_SEARCH_FUZZY_MIN_TOKEN_LENGTH', 5),
        // The fuzzy pass is expensive, so it only runs when the cheap exact /
        // prefix / transliteration pass found fewer matches than this. A normal
        // query therefore never pays for it; a typo query does.
        'fuzzy_topup_below' => (int) env('AI_SEARCH_FUZZY_TOPUP_BELOW', 5),

        // Bounded synonym + transliteration expansion. Keys are matched as
        // phrases against the normalised query and each value is added as an
        // extra query variant, so "mchele" can find "rice" and vice versa.
        // Keep this list short and reviewed - it is the only place where
        // search vocabularies live.
        'synonyms' => [
            'mchele' => ['rice'],
            'rice' => ['mchele'],
            'unga' => ['flour'],
            'flour' => ['unga'],
            'sukari' => ['sugar'],
            'sugar' => ['sukari'],
            'mafuta' => ['oil'],
            'oil' => ['mafuta'],
            'sabuni' => ['soap'],
            'soap' => ['sabuni'],
            'saruji' => ['cement'],
            'cement' => ['saruji'],
            'viatu' => ['shoes', 'shoe'],
            'shoes' => ['viatu'],
            'shoe' => ['viatu'],
            'simu' => ['phone', 'mobile phone'],
            'phone' => ['simu'],
            'vocha' => ['airtime', 'credit'],
            'airtime' => ['vocha'],
            'breki' => ['brake pads', 'brake pad', 'brake'],
            'brake pads' => ['brake pad', 'brake', 'breki'],
            'brake pad' => ['brake pads', 'brake', 'breki'],
            'brake' => ['brake pads', 'brake pad', 'breki'],
            'premio' => ['toyota premio'],
            'malipo' => ['payment'],
            'payment' => ['malipo'],
            'dawa' => ['medicine'],
            'medicine' => ['dawa'],
        ],

        'embeddings' => [
            'enabled' => (bool) env('AI_SEARCH_EMBEDDINGS', false),
            'provider' => env('AI_SEARCH_EMBEDDINGS_PROVIDER', 'gemini'),
            'model' => env('AI_SEARCH_EMBEDDINGS_MODEL', 'text-embedding-004'),
            'dimensions' => (int) env('AI_SEARCH_EMBEDDINGS_DIMENSIONS', 768),
            // Written with every stored vector; bump it when the model changes
            // so a partial backfill can never mix two embedding spaces.
            'version' => env('AI_SEARCH_EMBEDDINGS_VERSION', 'v1'),
            'table' => 'ai_product_embeddings',
            // How much the semantic score may influence the final ranking.
            'weight' => (float) env('AI_SEARCH_EMBEDDINGS_WEIGHT', 0.35),
            'timeout' => (int) env('AI_SEARCH_EMBEDDINGS_TIMEOUT', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema-free natural-language data entry (capability #25)
    |--------------------------------------------------------------------------
    | ONE sentence in, one strict, editable preview out, and - only after the
    | user confirms - a record written through the EXISTING endpoint for that
    | type (POST /api/products, /api/sales, /api/office-expenses,
    | /api/customers). This capability owns no inventory, sales or expense
    | logic of its own; it is a front door to the workflows that already exist.
    |
    | Guard rails enforced in code, not configuration:
    |   - the record type is an allow-list (product, sale, expense, customer);
    |   - the model may only pick one of those and copy a phrase from the text,
    |     never invent a number, a table, a column or SQL;
    |   - product references are matched against the CALLER'S OWN catalogue with
    |     capability #24's ranker, and a near-tie stays `ambiguous` with no id;
    |   - a commit needs `confirm: true`, a role that may record data, a stable
    |     `idempotency_key` (recorded in `user_logs`, so a replay returns the
    |     original record instead of writing twice), and every id the client
    |     sends back is re-verified against the caller's business.
    */
    'entry' => [
        'enabled' => (bool) env('AI_ENTRY_ENABLED', true),
        // One sentence is short. Anything longer is truncated to this many
        // characters before any parsing happens.
        'max_text' => (int) env('AI_ENTRY_MAX_TEXT', 500),
        // Bounded catalogue read: the caller's own active products, capped.
        // Each PostgREST roundtrip costs hundreds of milliseconds here, so this
        // is deliberately modest - it is the whole catalogue of one business,
        // and a business larger than this is better served by the dedicated
        // product screens than by a single sentence.
        'max_catalogue' => (int) env('AI_ENTRY_MAX_CATALOGUE', 2000),
        // A ranked candidate below this score is not a match at all, so a
        // product reference that matches nothing is reported as not_found
        // rather than being forced onto the nearest product.
        'match_min_score' => (float) env('AI_ENTRY_MATCH_MIN_SCORE', 0.2),
        // How close the runner-up may be before the match is called ambiguous.
        'match_margin' => (float) env('AI_ENTRY_MATCH_MARGIN', 0.85),
        // How many products a preview may show as candidates for a reference.
        'max_candidates' => (int) env('AI_ENTRY_MAX_CANDIDATES', 5),
        // The model is only asked which of the four types a sentence is when the
        // deterministic classifier is not confident, and it may only answer
        // with one of them. Measured ~13 s on the live provider, so this can be
        // switched off: when it is, an unrecognised sentence still gets the
        // localized explanation and the examples instead of a guess.
        'model_assist' => (bool) env('AI_ENTRY_MODEL_ASSIST', true),
        // Note: there is no separate write budget - the commit calls the same
        // existing endpoints a seller could call directly, so the existing
        // rules (stock, ownership, duplicate names) are the only limits, and
        // the per-attempt idempotency key bounds accidental double writes.
    ],
];
