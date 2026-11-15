# Dukamkononi — Unique QR Scanner, Stock Receiving, Selling, Fridge Transfers and Order Scanning

This document describes the physical-item (unique QR) tracking system added to
Dukamkononi (Laravel + Supabase backend, Blade web portal, Expo mobile app).
It is the reference for the architecture, the workflows, the database objects,
the security rules, testing and deployment.

> **One sentence:** every physical unit can carry its own QR token; only the
> PostgreSQL functions decide whether a scan is legal, and Laravel never writes
> `products.stock` or `fridge_stock` directly.

---

## 1. Architecture that already existed (and is preserved)

| Layer | Existing pieces reused |
| --- | --- |
| Stock ledger | `products.stock` (business stock) + `fridge_stock` (per-fridge rows) + `stock_movements` |
| Receiving | `POST /api/products/{id}/stock` → atomic ledger function (admin-only, `source`, `notes`) |
| Selling | `POST /api/sales` (`SaleController@store`), allocation modal that splits a line into outside/fridge sources |
| Fridges | `fridge_transfer_in` / `fridge_transfer_out` RPCs, `/api/fridges/*` endpoints, `fridge` feature switch |
| Orders | `POST /api/orders` (public), seller order list/detail/status (`/api/seller/orders*`). Orders **never reserve** inventory; stock leaves through the sale/fulfilment path |
| Product identity | `products.id` + optional product-level `barcode` (kept for compatibility, still used by the old lookup flows) |
| Feature switches | central `business_settings` (`fridge`, `scanner`, `services`, `product_management`) exposed by `BusinessSettings` (web `data-feature="scanner"`, mobile `useFeatures().isEnabled('scanner')`) |

**Invariant kept everywhere:**
`products.stock = outside-fridge stock + SUM(fridge_stock.quantity)`.
Receiving raises it, sales lower it, transfers only move units between
locations.

---

## 2. Unique QR identification design

* One row in `product_items` = one physical unit of a `products` row.
* `product_items.qr_token` is the scanned value. A **unique index on
  `lower(btrim(qr_token))`** makes it impossible to register one code against two
  units, in any business.
* The product itself is registered **once** (`products`); scanning creates item
  rows only, never new products.
* Accepted code shapes (normalised server-side by `QrScanner::normalizeToken`):
  * plain token, e.g. `A001`, `Coke-9f3a1c`
  * Duka Mkononi prefixed: `dmk1:`, `dmk:`, `dukamkononi:`
  * a URL whose last path segment is the token (`https://…/items/A001`)
  * minimum 4 characters, maximum 200; whitespace removed.
* **No business, customer, price or branch data is encoded in the code.** The
  code is only a lookup token — all relationships live in the database. A leaked
  label reveals nothing beyond an opaque string.
* Label generation/printing is intentionally **not** part of this change: any
  printer or third-party generator can produce unique labels; the system only
  enforces uniqueness and lifecycle. (See "Outstanding risks".)
* Product-level barcodes keep working exactly as before
  (`/api/products/lookup`); the new workflows use `qr_token` and cannot be
  satisfied by a product barcode, so a product can never be counted twice.

### Item states

`status`: `pending` → `in_stock` → `reserved` → `sold` (or `void`)
`location`: `outside` (outside-fridge stock) or `fridge` (with `fridge_id`)

Full lifecycle:

```
pending ──complete receiving──▶ in_stock/outside ──fridge in──▶ in_stock/fridge
   │                                   ▲                              │
   │ cancel (rows deleted)             └────────fridge out────────────┘
   │
   └──sale scan──▶ reserved ──sale committed──▶ sold
                     │
                     └──release / 30-min TTL──▶ in_stock (same location)
```

Every transition is validated against the previous state inside a
`security definer` PL/pgSQL function that locks the item row
(`SELECT … FOR UPDATE`) before changing anything.

### Database objects added

Migration: `supabase/migrations/20261115000001_create_product_items_and_scanner.sql`
(SQLite mirror for tests: `database/migrations/2026_11_15_000001_create_product_items_and_scanner.php`).

Tables (new, existing tables untouched):

| Table | Purpose |
| --- | --- |
| `product_items` | one row per physical unit: `qr_token`, `product_id`, `business_id`, `branch_id`, `status`, `location`, `fridge_id`, `receiving_session_id`, `sale_id`, timestamps |
| `product_item_events` | append-only audit trail (`received`, `moved_in`, `moved_out`, `reserved`, `released`, `sold`, `void`) with actor, business, branch and metadata |
| `product_item_reservations` | temporary sale claims: `item_id`, `kind` (`sale`), `client_ref`, `user_id`, `expires_at` |
| `scanner_receiving_sessions` | open/completed/cancelled receiving sessions (`product_id`, `branch_id`, `scanned_count`, `user_id`) |

Functions (all `security definer`, granted to `service_role`):

| Function | Role |
| --- | --- |
| `qr_open_receiving_session` | open or resume the admin's session for one product |
| `qr_receive_item` | register one `pending` unit for a unique token inside a session |
| `qr_complete_receiving` | commit N pending units through the existing receiving ledger **once** |
| `qr_cancel_receiving` | delete pending units, leave no stock and no orphan rows |
| `qr_reserve_item` | 30-minute sale claim; checks product/business/branch/status/location |
| `qr_release_sale_items` | release claims (cart line removed, sale cancelled) |
| `qr_commit_sale` | inside the sale transaction: verify quantity == claimed units, derive per-product outside/fridge split from the items' real locations, mark items sold, deduct the exact locations |
| `qr_move_item_to_fridge` / `qr_move_item_out_of_fridge` | move one unit via the existing fridge RPCs (qty 1), update `product_items` |
| `qr_item_stock_summary` / `qr_verify_stock` | reconciliation reads (tracked units vs ledger quantities) |
| `qr_expire_reservations` | TTL cleanup, idempotent |
| `qr_log_item_event` | audit helper used by the functions |

No `migrate:fresh`, no `db:wipe`, no production migration was executed by the
implementation.

---

## 3. Administrator receiving (web + mobile)

* Page: `resources/views/msimamizi/bidhaa-mpya.blade.php`, mobile
  `Duka_mkononi/app/msimamizi/bidhaa-mpya.tsx`.
* Two mutually exclusive quantity modes under the existing quantity field:
  **"Kiasi kwa mkono"** (unchanged manual ledger receiving) and
  **"Skani moja moja"**.
* Selecting scan mode disables the quantity input, hides the normal submit
  button and shows **"Anza Kuskani"**. Heading: `Scanning Product Stock – <name>`.
* New product: the product is registered **once** with `stock = 0` (only when the
  name does not already exist), then a receiving session starts. Scanning never
  creates a second product row.
* Each accepted scan → one `pending` unit, counter +1, still-open camera.
  The quantity only moves after the API confirms (`qr_receive_item`).
* **"Maliza na Hifadhi"** calls `qr_complete_receiving`, which receives exactly
  `scanned_count` units through the existing admin-only ledger endpoint and
  returns `quantity` + `stock_after`.
* Closing without finishing calls `qr_cancel_receiving`: pending rows are
  deleted, stock is unchanged, no orphan records remain.
* A token already registered elsewhere is rejected (`QR_ALREADY_REGISTERED`) and
  never counted again.
* Sellers are refused by the API (`STOCK_RECEIVING_FORBIDDEN`, 403).

Endpoints: `POST /api/scanner/receiving/{start|scan|complete|cancel}`,
`GET /api/scanner/receiving/{id}`.

---

## 4. Seller sales scanning (web + mobile)

* Pages: `resources/views/muuzaji/uza.blade.php`,
  `Duka_mkononi/app/muuzaji/uza.tsx`.
* Existing product selection and cart are untouched. For the selected product a
  **Skani** button opens `Scanning Items for Sale – <product>`.
* The cart line becomes scanner-controlled: manual `+`/`-` and manual quantity
  addition are disabled for that line (mode exclusivity), and the line shows
  `Skena × n` with a button to keep scanning.
* Every accepted scan calls `qr_reserve_item` and adds exactly one unit, only
  after the backend confirms. Rejections: wrong product (`PRODUCT_MISMATCH`),
  already sold (`ITEM_SOLD`), already claimed (`ITEM_RESERVED`), duplicate in the
  same cart (`ITEM_DUPLICATE`), foreign business/branch.
* Claims are **not** a sale: stock does not move while the cart is open.
  Removing the line calls `/api/scanner/sale/release`; stale claims expire after
  30 minutes (`qr_expire_reservations`).
* Checkout: the cart's own `client_ref` is sent as the sale's `clientSaleKey`
  (idempotency), and `item_ids` travel with each line. `SaleController@store`
  detects them and calls `storeWithScannedItems()` → `qr_commit_sale` in the same
  transaction as the existing sale, payment and stock deduction:
  * line `quantity` must equal the number of claimed units (no double count with
    manual quantities),
  * the outside/fridge split is derived from where the units physically are, so
    the allocation modal is skipped for scanned carts,
  * items become `sold`, `sale_id` is recorded, audit events written,
  * retries return the original sale (`201` + `scanned_items_sold` /
    `ai_dup_<key>` behaviour) instead of selling twice.
* The existing preparation/fulfilment views are unchanged; the recorded item
  location is the authority for where a unit must be collected
  (`GET /api/scanner/items?product_id=…`, `identify`).

---

## 5. Fridge / freezer transfers (web + mobile)

* Pages: `resources/views/muuzaji/friji.blade.php`,
  `Duka_mkononi/app/muuzaji/friji.tsx` — the existing transfer modal.
* Two exclusive modes: manual quantity (unchanged) or **"Skani moja baada ya
  nyingine"**, which disables the quantity input and hides the confirm button.
* Headings: `Entering into <Fridge> – <Product>` / `Removing from <Fridge> – <Product>`.
* Each scan calls `POST /api/scanner/fridge/{in|out}`:
  * validates product, fridge, business, branch, and that the unit really is
    outside (for `in`) or inside that exact fridge (for `out`),
  * moves exactly one unit through the existing `fridge_transfer_in` /
    `fridge_transfer_out` RPCs,
  * updates the item's `location`/`fridge_id` and writes an audit event.
* Business stock is unchanged by transfers; only location quantities move.
  Wrong-location and wrong-fridge scans are rejected (`FRIDGE_MISMATCH`,
  `PRODUCT_MISMATCH`), so no stock can be duplicated or invented.

---

## 6. Global order scanning

* Web: `resources/views/muuzaji/orders.blade.php` (scanner icon top-right of the
  page header). Mobile: `Duka_mkononi/app/muuzaji/orders.tsx` (QR icon next to
  the refresh button).
* **No product selection**: `POST /api/scanner/order/scan` receives only the
  token (plus `cart_item_ids` for duplicate detection), identifies the product
  from the physical unit, validates business/branch/product availability and
  returns `product` + `item` (location, fridge name).
* The scanned products accumulate in a small staged list on the order page
  (`Bidhaa zilizoskaniwa`) with quantities; "Endelea Kuskani" reopens the
  scanner, "Funga" closes it **without clearing** what was already scanned.
* Rejections: unknown token (`QR_UNKNOWN`), sold (`ITEM_SOLD`), pending
  (`ITEM_PENDING`), void, reserved (`ITEM_RESERVED`), duplicate in this scan
  (`ITEM_DUPLICATE`), foreign business/branch (`PRODUCT_UNAVAILABLE`).
* **Nothing is reserved and nothing is sold by scanning.** Existing orders in
  Dukamkononi do not reserve inventory (stock leaves on the sale/fulfilment
  path), so this workflow deliberately performs validation + identification
  only; an item becomes `sold` solely through `qr_commit_sale`.

---

## 7. Additional scanner integrations

These reuse the same item lifecycle and are read-only/validation-only, so they
cannot change stock:

| Endpoint | Purpose |
| --- | --- |
| `GET /api/scanner/identify?qr_token=…` | product + item + location lookup (returns desk, stocktaking, "where is this unit?") |
| `GET /api/scanner/items?product_id=…&status=…&location=…` | list individual units/QR references for order preparation and picking |
| `GET /api/scanner/verify` | reconciliation: tracked units per product vs outside/fridge/ledger quantities, reports mismatches |
| `GET /api/scanner/receiving/{id}` | inspect a receiving session (scanned count, pending units) |

Web preparation/fulfilment screens and stocktaking can call these without any
new permission model — they are scoped to the caller's business/branch.

---

## 8. Manual/scanner mode exclusivity

Enforced in each UI and in the API contract:

* Manual mode: quantity controls enabled, scanner closed.
* Scanner mode: the operation's quantity input is disabled, the manual submit is
  hidden, the scanner owns the quantity, each accepted unit adds exactly 1, and
  the displayed number changes only after a backend response.
* Switching back to manual keeps the already-counted quantity; scanned cart
  lines keep their manual controls disabled until the line is removed (which
  releases the claims), so `10` typed + `10` scanned is impossible.
* Multi-product carts keep mode and quantity per line.
* A receiving session and a fridge transfer each operate on one product at a
  time, so there is one mode per operation.

---

## 9. Security and authorization

* Laravel never writes inventory or item rows directly; all mutations are
  PostgreSQL functions granted to `service_role` only, called through
  `Supabase::callFunction` (`QrScanner`).
* Server-side checks in `ScannerController`: feature switch, authenticated user,
  business, write branch, role (admin-only receiving), product/fridge ownership.
* Every function re-checks business, branch, product, status and location and
  locks the item row, so a forged request or a stale UI cannot move another
  business's unit, skip a state, or sell an item twice.
* Negative stock is refused by the existing ledger/fridge functions that
  `qr_commit_sale` calls inside its transaction.
* `business_settings.scanner = false` → UI hides every scanner control
  (`data-feature="scanner"`, `isEnabled('scanner')`) **and** the API returns
  `403 FEATURE_DISABLED`. Manual workflows keep working. Re-enabling restores all
  existing tokens and history unchanged (no second switch exists).
* Audit: `product_item_events` plus the existing monitoring log
  (`SCANNER_*` actions).

### Idempotency / duplicate protection

| Case | Protection |
| --- | --- |
| Camera fires many times per second | client debounce (1.5 s) + server state rejection |
| Same token registered twice | unique index + `QR_ALREADY_REGISTERED` |
| Same item scanned twice in one cart | `qr_reserve_item` + `cart_item_ids` check |
| Double-tapped sale / retried request | shared `clientSaleKey` → original sale returned |
| Sale quantity vs scanned units | `QUANTITY_MISMATCH` (nothing sold) |
| Repeated fridge scan | item location re-checked; second call rejects |
| Cancel receiving | pending rows deleted (no stock, no orphans) |
| Abandoned cart | claims released on line removal, else expire after 30 min |

---

## 10. Web and mobile files

**Backend**
* `supabase/migrations/20261115000001_create_product_items_and_scanner.sql` (new)
* `database/migrations/2026_11_15_000001_create_product_items_and_scanner.php` (new, SQLite test mirror)
* `app/Models/ProductItem.php`, `ProductItemEvent.php`, `ProductItemReservation.php`, `ScannerReceivingSession.php` (new)
* `app/Services/QrScanner.php` (new)
* `app/Http/Controllers/Api/ScannerController.php` (new)
* `routes/api.php` (scanner routes)
* `app/Http/Controllers/Api/SaleController.php` (scanned-lines commit)
* `tests/Feature/QrScannerTest.php` (new)

**Web**
* `public/js/dm-scanner.js` (new reusable component)
* `resources/views/partials/dm-locale.blade.php` (include)
* `resources/views/msimamizi/bidhaa-mpya.blade.php` (receiving)
* `resources/views/muuzaji/uza.blade.php` (sale cart)
* `resources/views/muuzaji/friji.blade.php` (fridge in/out)
* `resources/views/muuzaji/orders.blade.php` (global order scanner)

**Mobile**
* `Duka_mkononi/components/QrScannerModal.tsx` (new reusable component)
* `Duka_mkononi/app.json` (`expo-camera` plugin + permission text)
* `Duka_mkononi/package.json`, `yarn.lock` (`expo-camera@17.0.10`)
* `Duka_mkononi/app/msimamizi/bidhaa-mpya.tsx`, `app/muuzaji/uza.tsx`,
  `app/muuzaji/friji.tsx`, `app/muuzaji/orders.tsx`

---

## 11. Automated tests and actual results

`tests/Feature/QrScannerTest.php` runs a stateful PostgREST/RPC fake that mirrors
the SQL rules (uniqueness, status, location, reservation TTL, quantity check) so
the HTTP layer and `SaleController` are exercised end to end.

Covered (33 tests): manual receiving still works · one scan = one unit · many
scans = N units · re-registering a token rejected · failed scan does not increase
stock · cancel leaves no stock/orphans · manual cart quantity · scanner disables
manual quantity (API refuses mismatch) · valid scan adds one unit · duplicate
scan rejected · wrong product rejected · already-sold rejected · cancelled sale
deducts nothing · successful sale commits status + stock exactly once · manual
transfer · scan in moves one unit · scan out moves one unit · wrong location
rejected · business stock unchanged by transfers · multiple fridges · order scan
identifies product without prior selection · two products land in the right
lines · duplicate/unknown/sold/reserved/foreign rejected · feature off blocks
everything · sellers cannot receive stock · branch and business isolation ·
`verify` reconciles items with the ledger.

Actual runs (this environment):

```
php artisan test --filter=QrScannerTest  →  33 passed (188 assertions)
php artisan test                         →  827 passed (12317 assertions)
npx tsc --noEmit (Duka_mkononi)          →  clean
node --check public/js/dm-scanner.js      →  clean
php artisan view:cache                   →  all Blade templates compile
```

---

## 12. Device / browser compatibility — what was actually verified

Verified here: server-side behaviour (all tests above), Blade compilation,
JavaScript syntax (Node), and TypeScript type-checking of the Expo app.

**Not verified in this environment** (no camera hardware, no simulator, no
device): real camera permission prompts, autofocus/scan latency, iOS/Android
camera behaviour, USB/Bluetooth wedge scanners, and cross-browser camera
support. The implementation targets:

* Web: `html5-qrcode` 2.3.8 from CDN with `facingMode: environment`; camera
  works where `getUserMedia` is available over HTTPS (Chrome/Edge/Firefox/Safari
  on current desktop and Android/iOS versions). A keyboard-wedge input is always
  present, so USB/Bluetooth scanners work even when the camera does not.
* Mobile: `expo-camera` `CameraView` with `barcodeScannerSettings: ['qr']`,
  permission requested on first open, manual entry fallback.

Graceful handling implemented: permission denied/blocked (explicit message +
manual input), camera library offline (message + manual input), network failure
(status message, counter unchanged), repeated detections (1.5 s debounce),
shutdown (camera stopped and reader cleared on close).

---

## 13. Inventory integrity

* Business stock only moves in `qr_complete_receiving` (through the existing
  receiving ledger) and in `qr_commit_sale` (through the existing sale
  deduction). Transfers and order scanning never change it.
* Each accepted scan produces exactly one operation; a rejected scan produces
  none and never touches a counter.
* Item status/location and the ledger are updated in the same transaction, and
  `qr_verify_stock` reports any product whose tracked units do not reconcile
  (`in_stock` outside/fridge counts vs `products.stock`/`fridge_stock`).
* Products that are not individually tracked keep working exactly as before;
  tracking is opt-in per received unit, never forced by migration.

---

## 14. Outstanding risks and deliberate limits

1. **No label generator/printer.** Tokens come from whatever labels the business
   uses; the system enforces uniqueness only. A future step can generate
   `dmk1:<random>` labels and a printable sheet.
2. **Order page has no order-creation cart** in this codebase (orders are created
   by `POST /api/orders` from the storefront). The scanner therefore fills a
   staged identification list on the order page and never invents an order; the
   existing order flow remains the only way to create one. If a seller-side cart
   is added later, it can post `item_id`s to the same endpoint.
3. **Orders do not reserve items.** Preserved on purpose (existing behaviour);
   two orders can reference the same unit until one is sold. The sale path still
   refuses the second sale because the item is `sold`.
4. **Reservation TTL is 30 minutes.** A seller who leaves a cart open longer must
   rescan the unit (it returns to the shelf).
5. **Reconciliation is a report, not an automatic repair.** Ledger drift from
   legacy manual edits is surfaced by `qr_verify_stock`, not silently fixed.
6. **SQLite mirror** exists for the test suite; production rules live in the
   Supabase SQL migration and must be applied there.
7. Mixed carts (one line scanned, another manual) are allowed; the scanned line
   must have `quantity == scanned units`, the manual line behaves as before.

---

## 15. Local testing

```bash
# PHP (SQLite test database, no Supabase/Postgres needed)
php artisan test --filter=QrScannerTest
php artisan test

# Blade + JS sanity
php artisan view:cache && php artisan view:clear
node --check public/js/dm-scanner.js

# Mobile
cd Duka_mkononi && npx tsc --noEmit
```

The scanner needs `business_settings.scanner = true`; in tests this is seeded and
`Cache::flush()` is called, because settings are cached.

---

## 16. Production deployment (manual, never automatic)

1. **Back up** the Supabase database (snapshot/`pg_dump`) and note the current
   migration head.
2. Apply the SQL migration **manually** in the Supabase SQL editor:
   `supabase/migrations/20261115000001_create_product_items_and_scanner.sql`.
   It only creates new tables/functions/indexes and grants them to
   `service_role`; no existing table is altered or dropped.
3. Verify: the four new tables exist, `select proname from pg_proc where
   proname like 'qr_%'` lists the functions, and the unique index on
   `product_items` is present.
4. Deploy the Laravel code (controllers, service, models, routes, views, JS) and
   the mobile build (`expo-camera` native module requires a new build — it is
   **not** available through an OTA update).
5. Do **not** run `migrate:fresh` or `db:wipe`. The Laravel SQLite migration is
   for the test suite only.
6. Turn on **Settings → Scanner** for the businesses that should use it. Until
   then every scanner control stays hidden and every scanner endpoint answers
   `403 FEATURE_DISABLED`; manual receiving/selling/transfers keep working.
7. Smoke test on one product: receive 2 units by scanning, sell 1 by scanning,
   move 1 into a fridge by scanning, then run
   `GET /api/scanner/verify?product_id=…` and confirm the counts match.

### Verification and rollback limits

* Verify by scanning a physical unit in each workflow and checking
  `product_items` (status/location), `stock_movements`, `fridge_stock`, and the
  sale record.
* Rolling back the code is safe; rolling back the SQL is **only** safe before any
  item is registered. Dropping `product_items` deletes the link between labels
  and units (the stock ledger itself stays correct), and already-printed codes
  could then be registered against different units. Prefer disabling the Scanner
  setting (history preserved, controls hidden) over dropping tables.
* A completed receiving session and a completed sale are immutable; correcting
  them requires the existing stock-adjustment/sale-return flows, not row edits.
