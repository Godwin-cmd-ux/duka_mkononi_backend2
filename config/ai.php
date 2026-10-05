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
];
