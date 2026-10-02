# Duka Mkononi — Supabase migrations

These SQL files add the database structures required by the website redesign,
customer shopping, order management and Inquiry module.

Run them **once, in filename order**, in
`Supabase Dashboard → SQL Editor → New query` (paste and run each file), or with
the Supabase CLI if this project is ever linked.

```
20261002120000_create_inquiries.sql
20261002120100_create_customer_reviews.sql
20261002120200_create_orders.sql
20261002120300_create_order_items.sql
20261002120400_create_order_status_history.sql
20261002120500_add_business_certification.sql
20261002120600_add_updated_at_triggers.sql
```

Every file is **idempotent and non-destructive**: it only uses
`create table if not exists`, `add column if not exists`, guarded index/trigger
creation, and never drops or rewrites existing data. Re-running is safe.

## Why new tables instead of reusing `sales` / `sale_items`

`sales` + `sale_items` are the **seller POS** (walk-in sales dock, AI-imported
sales, stock deduction, receipts). They are tightly coupled to the seller
screens and mobile app. Customer **online orders** are a different lifecycle
(customer places → seller confirms → tracking by phone), so they live in their
own `orders` / `order_items` / `order_status_history` tables. This avoids
touching any existing POS behaviour, which is a hard requirement of the task.

## Existing tables reused as-is

| Need | Reused structure |
| --- | --- |
| Business identity | `businesses` (`id`, `business_name`, `legacy_name_key`, …) |
| User → business link | `users.business_id` |
| Products for sale | `products` (`seller_id`, `is_active`, `stock`, `price`/`expected_selling_price`) |
| Advertisements page | `matangazo` (`is_active`, `payment_status`, `starts_at`, `expires_at`) |
| User contact/phone | `users.phone` |

No migration is needed for the Advertisements page — the existing eligibility
columns are enough.

## ID types

The existing business-scoped tables use **text** id columns
(`products.seller_id text`, `sales.seller_id text`, `customers.seller_id text`)
and the app resolves ids as strings. To stay compatible, the new
`orders.business_id` and `orders.seller_id` columns are `text` (indexed, not
hard FKs). Foreign keys are added only between the new tables, where both sides
are UUIDs and the relationship is fully owned by this migration.

## Row Level Security

RLS is intentionally **not** enabled here. The Laravel backend talks to
PostgREST with the **service_role** key (see `app/Services/Supabase.php`),
which bypasses RLS, and enabling it on new tables without matching policies
would break access. If the project later moves to anon-key access with
policies, add RLS policies for these tables at that time.
