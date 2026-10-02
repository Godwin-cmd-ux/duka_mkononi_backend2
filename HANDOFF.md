# HANDOFF — Duka Mkononi master implementation

Status snapshot for a cloud agent to continue. Covers the master prompt in
`todays_task.txt` (website redesign, shopping, orders, inquiry module, full
Blade responsive pass, multilingual).

**Branch:** `main` · **Upstream:** `backend2/main` · **DB:** live Supabase
(PostgREST; Laravel uses the `service_role` key).

---

## 1. What is DONE and verified

### 1.1 Supabase migrations (RUN on the live DB — tables confirmed to exist)
Files in `supabase/migrations/` (run in filename order; all idempotent):

| File | Purpose |
| --- | --- |
| `20261002120000_create_inquiries.sql` | `inquiries` table (Contact Us) |
| `20261002120100_create_customer_reviews.sql` | `customer_reviews` (public, FK `inquiry_id` unique) |
| `20261002120200_create_orders.sql` | `orders` (reference, status, phone-normalized, `client_order_key`) |
| `20261002120300_create_order_items.sql` | `order_items` (price snapshots) |
| `20261002120400_create_order_status_history.sql` | `order_status_history` |
| `20261002120500_add_business_certification.sql` | `businesses`: `is_certified`, `certified_at`, `certification_note`, `is_public`, `public_slug` |
| `20261002120600_add_updated_at_triggers.sql` | `updated_at` trigger for the 3 new tables |

Live columns were inspected and match the code. `businesses.id`, `users.id` are
UUID; the new tables use `text` for `business_id`/`seller_id` to match
`products.seller_id` (no cross FKs).

### 1.2 Backend (Laravel) — implemented and tested end-to-end
Models: `Inquiry`, `CustomerReview`, `Order`, `OrderItem`, `OrderStatusHistory`
(all extend `SupabaseModel`).

Services:
- `app/Services/PhoneNumber.php` — `normalize()` (TZ 255 canonical), `isValid()`.
- `app/Services/PublicCatalog.php` — public reads; only `is_public` businesses,
  `is_active` products **with a selling price** (`expected_selling_price`, NOT
  `price` which is the buying price), `is_certified` for the certified page,
  `is_published` reviews. Bulk-loads users/products once, cached 90s in the
  **file** cache store (`dm_public_member_map`, `dm_public_sellable_products`).
- `app/Services/OrderService.php` — server-side pricing (`priceItems`), order
  creation, UUID guard (`isUuid`), status transitions map, `recordStatus`,
  `itemsFor`, `reference()` (`DM-YYYYMMDD-XXXXXX`).

Controllers (`app/Http/Controllers/Api/`):
- `ShopController` — `businesses`, `certifiedBusinesses`, `products`
  (`?business_id=&search=&page=`), `product`, `advertisements`, `reviews`.
- `OrderController` — public `store` (idempotent via `client_order_key`),
  public `track` (phone), seller `sellerIndex/sellerShow/sellerUpdateStatus/
  sellerAddNote`. Business-scoped server side; `system_admin` sees all.
- `InquiryController` — public `store`; Super Admin `adminIndex/adminShow/
  markReviewed/publish/unpublish`. Publishing copies only public content into
  `customer_reviews` (never email/phone), unique `inquiry_id` blocks duplicates.
- `SystemAdminController` — `businesses`, `certify`.

Routes added in `routes/api.php`:
```
GET  api/shop/businesses | api/shop/businesses/certified | api/shop/products
GET  api/shop/products/{id} | api/shop/advertisements | api/shop/reviews
POST api/inquiries                              (throttle:10,1)
POST api/orders                                 (throttle:20,1)
GET  api/orders/track                           (throttle:20,1)
GET  api/seller/orders | api/seller/orders/{id}
PUT  api/seller/orders/{id}/status
POST api/seller/orders/{id}/notes
GET  api/system-admin/inquiries | api/system-admin/inquiries/{id}
PUT  api/system-admin/inquiries/{id}/reviewed
POST api/system-admin/inquiries/{id}/publish | .../unpublish
GET  api/system-admin/businesses
PUT  api/system-admin/businesses/{id}/certify
```

**Tests actually run (all passed, live against Supabase, scratch data deleted):**
- `GET /api/shop/businesses` 200 (product counts); `/api/shop/products` 1857
  total, correct `selling_price`; `/reviews` `[]`; `/advertisements` 200.
- Order create → server priced 2×2000 = 4000, reference generated; idempotent
  retry returned the same order; `track` returned items; seller list scoped to
  the business; `pending→confirmed` persisted; `confirmed→pending` rejected 422;
  internal note NOT exposed by `track`; customer-visible note WAS.
- Inquiry valid POST 201; invalid POST 422 with per-field errors.
- Admin: list → `reviewed` → `publish` → public review shown with **no** email/
  phone → `unpublish` → public list empty.
- Auth gates: no token 401; seller token on `/api/system-admin/inquiries` 403.
- Non-UUID product id fixed to 422/404 (was a 500 UUID DB error).
- Cleanup confirmed: scratch order/inquiry/review removed (counts back to 0).
- `php artisan route:list`, `php -l` on all new PHP, `php artisan view:cache`
  (all Blade compiles) — clean.

### 1.3 Website (Blade) — implemented, HTTP 200 verified
New shared partials: `partials/site-head.blade.php` (design system CSS),
`partials/site-navbar.blade.php` (8 nav items + mobile drawer, real APK URL),
`partials/site-footer.blade.php` (info@dukamkononi.com, 0757071967, WhatsApp).

Pages:
- `index.blade.php` — **redesigned**: hero (real stats), Shop by Business
  (links `/shop?business_id=<id>`), Our Story, Available Accounts, Customer
  Reviews (**hidden unless published reviews exist**), Contact Us form
  (validates + saves inquiry). No emoji UI icons (inline SVG + Font Awesome).
- `shop.blade.php` — two-column (business sidebar + products), server-side
  `business_id` filter in the URL, product cards, quantity/total, cart
  (localStorage, single-business enforced), POST `/api/orders`.
- `track-orders.blade.php` — phone + optional reference, items, timeline,
  latest customer-visible note.
- `advertisements.blade.php`, `businesses.blade.php` (certified only).
- `muuzaji/orders.blade.php` — seller Orders module (list/table+cards, filters,
  detail, status update, notes, history).
- `system_admin/inquiries.blade.php` — Super Admin Inquiry module + publish/
  unpublish.

Routes (`routes/web.php`): `/shop`, `/track-orders`, `/advertisements`,
`/businesses`, `/muuzaji/orders`, `/system_admin/inquiries`.

**Verified:** all 7 pages HTTP 200, `data-dm-locale` correct, language widget
present, **0 leaked `@verbatim/@include`**, locale session inheritance works
(set `/language/fr` → pages render `fr`).

### 1.4 Multilingual
`locales.json` grew from 27 → **36 sections × 8 languages** (`sw,en,fr,hi,es,ur,de,zh`).
New sections: `site_nav, site_common, site_home, site_shop, site_track, site_ads,
site_businesses, muuzaji_orders, system_admin_inquiries`.
Audit: **all 193 distinct translation keys referenced by the new/changed pages
exist in all 8 languages** with no empty values. Brand `DukaMkononi`/`Dukamkononi`
never translated.

**msimamizi preview days-of-week FIXED** (the user's specific request):
`resources/views/msimamizi/preview.blade.php` now has `DATE_LOCALES` +
`dateLocale()`, and the two `toLocaleDateString` calls + the printed-at
timestamp use it instead of hard-coded `'sw-TZ'`. The existing `DM.onChange`
hook re-renders, so weekday names now follow the active language.

---

## 2. IMPORTANT DECISION — Super Admin authorization
Production roles are only `admin`, `seller`, `customer` (**no `system_admin`
row exists**), and the mobile `system_admin/dashboard.tsx` itself gates on
`role === 'admin'`. `InquiryController`/`SystemAdminController` therefore accept
**`system_admin` OR `admin`** as Super Admin (documented in-code). This matches
the platform's existing security model (`AdminController` is admin-only), but it
means any business `admin` can read platform inquiries. **Recommended hardening:
introduce a dedicated `system_admin` account/role and restrict to it.**

---

## 3. What is NOT finished (next steps)

1. **Mobile app screens (highest priority)** — none of the mobile changes are
   written yet. Need:
   - `Duka_mkononi/app/muuzaji/orders.tsx` + register in `app/muuzaji/_layout.tsx`
     (add `orders` tab, add `'/muuzaji/orders'` to `MUUZAJI_TABS`), using
     `GET /api/seller/orders`, `.../{id}`, PUT `.../{id}/status`,
     POST `.../{id}/notes`.
   - `Duka_mkononi/app/system_admin/inquiries.tsx` + add to
     `app/system_admin/_layout.tsx` top tabs and `SYSTEM_ADMIN_TABS`, using the
     `/api/system-admin/inquiries*` endpoints.
   - Add mobile translation keys to `Duka_mkononi/locales/*.json` (8 files):
     `tabs_seller.orders`, `system_admin.inquiries`, plus screen strings.
     Mobile uses `constants/api.ts` (`API_BASE_URL`), `useLang().t`,
     `AsyncStorage.getItem('userToken')`, `lib/network.fetchWithTimeout`.
   - Preserve login, language selector, existing tabs/navigation — no redesign.
2. **Blade responsive audit (Part 17)** — the new/changed pages are mobile-first
   and responsive, but the **existing** portal blades were NOT individually
   re-audited this session. Do the one-by-one pass + final re-scan.
3. **Certification management UI** — backend `PUT /api/system-admin/businesses/{id}/certify`
   exists but no UI to toggle certification yet (no business is certified, so the
   public certified page is legitimately empty).
4. **Order notes on the website Track page** already show; done.
5. Confirm `advertisements`/`businesses` public pages against real data as
   certifications/ads are added.
6. Optional: real DB transactions — Supabase REST has none; `OrderController`
   uses compensating deletes for multi-business failures. Consider a Postgres
   RPC if strict atomicity is required.

---

## 4. Local test harness notes
- `php artisan serve --port=8123` was used; all public endpoints tested with curl.
- Scratch data was created and **deleted**; nothing left in production data.
- Helpful scripts left on disk (git-ignored): `storage/app/merge_locales.js`
  (the locales merge), `storage/app/locales.backup.json`.
- Rate limiting uses Laravel `throttle:` middleware (file cache).

## 5. Deploy notes
- Run the `supabase/migrations/*.sql` in order (already applied).
- Deploy normal Laravel: `composer install`, `php artisan optimize`, ensure the
  file cache store is writable (used by `PublicCatalog`).
- No `sales`/`sale_items` behaviour was touched (POS preserved).
