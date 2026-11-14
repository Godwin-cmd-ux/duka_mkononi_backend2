# Fridge / Freezer Inventory — Implementation and Migration Guide

This document covers the Fridge/Freezer (`Friji`) inventory feature for the Seller module on both the website and the mobile app.

## Core invariant

```
Business Stock = Outside Fridge Stock + Total Inside Fridge Stock
```

- Business stock remains the single source of truth (`products.stock`).
- Inside stock is stored per fridge in `fridge_stock.quantity`.
- Outside stock is **derived**, never stored: `products.stock - sum(fridge_stock.quantity)` for the business/branch.
- Moving stock into or out of a fridge changes only `fridge_stock` and writes a `fridge_stock_movements` row. It **never** changes `products.stock`.
- Selling stock decrements `products.stock` by the full sold quantity. Any allocation to a fridge also decrements that fridge's `fridge_stock.quantity`, so the invariant continues to hold.

## Database migration

Authoritative DDL: `supabase/migrations/20261114000001_create_fridge_inventory.sql`.
Run it manually in the Supabase SQL Editor for each environment. The PHP file `database/migrations/2026_11_14_000001_create_fridge_inventory.php` is a local SQLite mirror only; it is not the deployment source of truth.

**Already ran the original migration?** Run `supabase/migrations/20261114000005_fix_fridge_transfer_in_quantity.sql` too. It replaces `fridge_transfer_in`, which previously inserted `0` and therefore left inside stock unchanged after a successful transfer. The repair is safe to re-run and does not modify existing rows; historical movements are not replayed into stock.

The migration is additive and idempotent (`create table if not exists`, `alter table ... add column if not exists`):

| Object | Purpose |
| --- | --- |
| `fridges` | Active/archived fridge definitions per business and branch. |
| `fridge_stock` | Quantity of each product inside each fridge, with per-fridge `min_stock`. |
| `fridge_stock_movements` | Append-only audit trail for transfers and sales. |
| `sale_item_fridge_allocations` | Which fridge supplied each sold unit of a sale line. |
| `fridge_stock_alerts` | Open/resolved low-stock alerts. |
| `products.min_outside_stock` | Optional low-outside-stock threshold. `0` means not configured. |

Partial unique indexes prevent duplicate active fridge names and duplicate open alerts. All new tables and RPCs revoke access from `public`, `anon`, and `authenticated`; only `service_role` may call them. Laravel reaches them through the Supabase service client.

### RPCs

- `fridge_transfer_in(fridge, product, quantity, notes)` — moves stock from outside into a fridge atomically.
- `fridge_transfer_out(fridge, product, quantity, notes)` — moves stock from a fridge back outside atomically.
- `create_pos_sale(...)` — creates a sale, decrements business stock, decrements allocated fridge stock, and records allocations atomically. Used only when a sale line contains `allocations`.
- `fridge_set_threshold(...)` — updates a per-fridge/per-product minimum.

## Backfill / existing data

Existing stock is treated as **outside stock**:

1. Run the migration.
2. Do not move existing `products.stock` into any fridge.
3. New fridges start with zero inside stock. The seller explicitly transfers stock into them.

No automatic backfill script is provided because business stock already represents all stock, and moving it into fridges is an operational decision.

## Sale behaviour

Request shape for a sale line:

```json
{
  "product_id": "uuid",
  "quantity": 5,
  "unit_price": 1000,
  "allocations": [
    { "fridge_id": null, "quantity": 3 },
    { "fridge_id": "uuid", "quantity": 2 }
  ]
}
```

Rules enforced server-side:

- If a line has a non-empty `allocations` array, the whole sale goes through `create_pos_sale` atomically.
- The sum of allocations must equal `quantity`; duplicate sources and non-positive quantities are rejected (`ALLOCATION_INVALID`).
- A `null` fridge is outside stock. It is validated against derived outside stock.
- Fridge allocations are validated against current `fridge_stock`; insufficient inside stock is rejected (`FRIDGE_STOCK_ERROR`).
- Sales without `allocations` consume **outside stock only** and are rejected when outside stock is insufficient. They never silently consume fridge stock.
- AI-imported sales follow the same outside-only rule.
- Every sale decrements `products.stock` exactly once and records fridge movements where applicable.

## Alerts

Three independent alert families, each with its own threshold:

| Alert | Threshold column | Scope |
| --- | --- | --- |
| Business stock low | `products.min_stock_level` | Product / branch |
| Outside stock low | `products.min_outside_stock` | Product / branch |
| Fridge stock low | `fridge_stock.min_stock` | Product / fridge |

A threshold of `0` means **not configured** and never raises an alert. `FridgeAlertService` persists open alerts, resolves alerts that no longer apply, and deduplicates by `type|branch|fridge|product`. Archived fridges are ignored.

## API endpoints

All routes require authentication and are scoped to the authenticated seller's business and permitted branches.

| Method | Path | Description |
| --- | --- | --- |
| GET | `/api/fridges` | List active/archived fridges with summary. |
| POST | `/api/fridges` | Create a fridge. |
| GET | `/api/fridges/{id}` | Fridge detail with stock and thresholds. |
| PUT | `/api/fridges/{id}` | Rename a fridge. |
| DELETE | `/api/fridges/{id}` | Archive a fridge; only when its total stock is zero. |
| GET | `/api/fridges/{id}/movements` | Paginated movement history. |
| POST | `/api/fridges/{id}/stock` | Transfer stock in. |
| POST | `/api/fridges/{id}/stock/remove` | Transfer stock out. |
| PUT | `/api/fridges/{id}/stock/{productId}/threshold` | Set per-fridge product threshold. |
| PUT | `/api/fridges/outside-threshold/{productId}` | Set outside-stock threshold. |
| GET | `/api/fridges/availability?product_ids=...` | Outside/inside availability for cart allocation. |

## Web

- Page: `resources/views/muuzaji/friji.blade.php`, served at `/muuzaji/friji` (and `/friji` redirects there).
- Navigation: `Friji` link is present in every seller sidebar, including `resources/views/muuzaji/partials/sidebar.blade.php`.
- Sell page: `resources/views/muuzaji/uza.blade.php` fetches availability and opens an allocation modal when the cart contains fridge stock. The modal defaults to outside stock first and lets the cashier split each line across fridges.

## Mobile

- Screen: `Duka_mkononi/app/muuzaji/friji.tsx`, registered as a tab in `Duka_mkononi/app/muuzaji/_layout.tsx`.
- Sell page: `Duka_mkononi/app/muuzaji/uza.tsx` uses the same availability endpoint and allocation modal.
- Locale keys: `muuzaji_friji.*` and `tabs_seller.fridge` exist in all eight `Duka_mkononi/locales/{sw,en,fr,hi,es,ur,de,zh}.json` files. The website similarly has `muuzaji_friji.*` and `muuzaji_sidebar.nav_fridge` in `locales.json`.

## Deployment checklist

1. Back up the database.
2. Run `supabase/migrations/20261114000001_create_fridge_inventory.sql` in the Supabase SQL Editor.
2b. If the original migration was applied before this fix, also run `supabase/migrations/20261114000005_fix_fridge_transfer_in_quantity.sql` (or re-run the updated original, which recreates the function).
3. Verify the new columns, tables, indexes, and RPC grants.
4. Deploy the Laravel API and web assets.
5. Deploy the mobile build.
6. Confirm existing stock appears as outside stock and all fridges start empty.
7. Create one fridge, transfer stock in and out, and confirm business stock is unchanged.
8. Complete one plain sale and one allocated sale; confirm outside/inside balances and movement rows.

## Notes and known limitations

- Fridge deletion is archival (soft delete) and is allowed only when total fridge stock is zero; history is retained.
- `fridge_alerts_open_unique` uses a `coalesce(...)` expression index. `FridgeAlertService` also deduplicates in PHP; a concurrent duplicate insert is swallowed rather than retried.
- The local SQLite mirror is for tests only. Do not run migrations against production from Laravel.
- `SellerBranchAccessTest` is a known unrelated failure caused by the PostgREST `Http::fake` layer not serving rows; it is not caused by this feature.
