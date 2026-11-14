# Admin Settings Module — Implementation and Migration

Dukamkononi centralized administrative settings for the web portal and the Expo app.

---

## 1. Architecture decisions

| Concern | Decision | Why |
|---|---|---|
| Settings storage | One new table, `public.business_settings`, keyed by `business_id` | The existing schema had no settings/feature-flag store. Business-level options belong to the business, not to the `users` table or to `localStorage`. |
| Missing row | Means "all defaults" | Existing businesses need no backfill. A failed settings read falls back to today's behaviour instead of taking the dashboard down. |
| Profile / business writes | Reuse `PUT /api/user/profile` | That endpoint already owns the rename-rekey logic, ownership checks and audit trail. A second writer would be a duplicate system. |
| Branch management | Extend `BranchController` with create / update / deactivate | Branches previously existed only at signup. `branch_id` is stable; deactivation is soft. |
| Feature control | `App\Services\BusinessSettings` | One cached answer (`fridge`, `scanner`, `services`) used by controllers, web JS and mobile context. No scattered conditions. |
| Web visibility | `public/js/dm-features.js`, loaded from the universal locale partial | Every portal page already includes that partial, so navigation updates centrally without touching 13 page copies of the sidebar. |
| Mobile visibility | `Duka_mkononi/context/FeatureContext.tsx` | Caches the last known switches for offline launch and refreshes on foreground. |
| Security boundary | Backend `requireFeature()` guard | Hiding a button is presentation. The API returns `403 FEATURE_DISABLED` even for a hand-written request. |
| User preferences | `users.language` and `users.preferred_theme` only | Reuses the existing language and theme systems. No second preference store. |

### Defaults (chosen to preserve live behaviour)

| Column | Default | Reason |
|---|---|---|
| `fridge_enabled` | `true` | The Fridge/Freezer module is already live and in use. Defaulting to `false` would hide it from existing businesses. |
| `services_enabled` | `true` | Service recipes and service sales already exist. |
| `scanner_enabled` | `false` | Opt-in barcode receiving/selection. When off, scan controls are hidden and scanner API operations are refused. |
| `notify_low_stock` | `true` | Low-stock alerts are already shown. |
| `notify_fridge_stock` | `true` | Fridge low-stock alerts are already shown. |

Disabling a switch never deletes rows: `fridges`, `fridge_stock`, `fridge_stock_movements`, `fridge_stock_alerts`, `services`, `service_sales` and all history remain untouched. Re-enabling restores access to the same data.

---

## 2. Tables and columns introduced

`public.business_settings`

| Column | Type | Null | Default |
|---|---|---|---|
| `business_id` | uuid, PK | no | — |
| `fridge_enabled` | boolean | no | `true` |
| `scanner_enabled` | boolean | no | `false` (opt-in) |
| `services_enabled` | boolean | no | `true` |
| `notify_low_stock` | boolean | no | `true` |
| `notify_fridge_stock` | boolean | no | `true` |
| `updated_by` | uuid | yes | — |
| `created_at` | timestamptz | no | `now()` |
| `updated_at` | timestamptz | no | `now()` |

No existing column is altered or dropped. Branch phone/email/description/logo are **not** added because the current branch schema does not have them; the Settings UI only edits columns that already exist (`branch_name`, `location`, `latitude`, `longitude`, `status`).

---

## 3. Purpose of every migration

| File | Purpose | Safe to re-run |
|---|---|---|
| `supabase/migrations/20261114000002_create_business_settings.sql` | Creates the table, completes columns from a partial earlier run, and adds an `updated_at` trigger. | Yes (`if not exists`, `create or replace`) |
| `database/migrations/2026_11_14_000002_create_business_settings.php` | Local/CI mirror of the same table so `RefreshDatabase` matches production shape. | Yes (`Schema::hasTable`) |

The authoritative DDL for production is the PostgreSQL file. The Laravel migration exists for the local test database only (application data is read through Supabase/PostgREST).

---

## 4. Migration execution order

1. `20261110000001_create_branches_and_business_structure.sql` (already applied/required — settings reference `business_id` logically, and branch management needs `branches`).
2. `20261114000002_create_business_settings.sql`.

The settings table has no hard foreign key to `businesses` on purpose: older deployments in this project already tolerate business rows created outside a strict FK graph, and a failed FK would block the whole migration. The API only ever writes the verified JWT `business_id`.

---

## 5. Existing data preservation strategy

- Additive only: no `ALTER COLUMN`, no rewrite, no delete.
- No backfill needed: a missing `business_settings` row is equivalent to the defaults above.
- `fridge_enabled` and `services_enabled` default to `true`, so no existing business loses a module on deploy.
- Branch edits update only the selected branch row. Sellers keep `users.branch_id`; products, sales, customers, expenses and reports keep their existing `branch_id`. Deactivation only changes `branches.status`.
- Disabling scanner hides barcode controls and makes `/api/products/lookup` and `source=scanner` receiving answer `403 FEATURE_DISABLED`; manual selling and manual receiving stay available.
- Notification switches affect presentation of open alerts only; `FridgeAlertService::sync()` still computes and records/resolves every alert.
- Profile editing never writes `role`, `status`, `business_id`, `branch_id` or `email`; unknown JSON keys are ignored.
- Business renames keep the same `businesses.id`; the existing `legacy_name_key` logic prevents a rename from creating a second business row.

---

## 6. Local migration instructions

```bash
# from the Laravel project root
php artisan migrate

# verify the local mirror exists
php artisan tinker --execute="dump(Schema::hasTable('business_settings'));"
```

Run the feature tests:

```bash
php artisan test tests/Feature/AdminSettingsTest.php
php artisan test
```

Mobile type-check:

```bash
cd Duka_mkononi
npx tsc --noEmit --skipLibCheck
```

---

## 7. Production provider migration instructions (Supabase)

**Do not run this automatically.** Execute only after explicit authorization.

1. Open the Supabase project → SQL Editor.
2. Paste `supabase/migrations/20261114000002_create_business_settings.sql`.
3. Run it once. It is idempotent.
4. Verify:

```sql
select column_name, data_type, column_default, is_nullable
from information_schema.columns
where table_schema = 'public' and table_name = 'business_settings'
order by ordinal_position;

select count(*) from public.business_settings;  -- 0 is correct and safe
```

5. Smoke-test with a demo business (non-production if possible):

```bash
# as an authenticated admin
curl -s -H "Authorization: Bearer $TOKEN" https://<host>/api/settings
curl -s -X PUT -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"fridge_enabled":false}' https://<host>/api/settings/features
curl -s -H "Authorization: Bearer $SELLER_TOKEN" https://<host>/api/fridges   # expect 403 FEATURE_DISABLED
curl -s -X PUT -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"fridge_enabled":true}' https://<host>/api/settings/features
curl -s -H "Authorization: Bearer $SELLER_TOKEN" https://<host>/api/fridges   # expect 200 and existing fridges
```

6. Deploy the application code after the migration (the API tolerates a missing table by using defaults, but writes need it).

### Deployment order

1. Apply the SQL migration (Supabase SQL Editor).
2. Deploy the Laravel/Blade build.
3. Publish the Expo build (or OTA update).
4. Verify the smoke tests above.

No `migrate:fresh`, `db:wipe`, destructive SQL, or data reset is part of this change.

---

## 8. Verification procedures

- Backend: `php artisan test tests/Feature/AdminSettingsTest.php` — 12 tests covering defaults, authorization, persistence/audit, cache invalidation, API enforcement, branch isolation and protected profile fields.
- Full regression: `php artisan test` — must be all green.
- Blade compilation: `php artisan view:cache`.
- Mobile: `npx tsc --noEmit --skipLibCheck`.
- Manual web: dashboard gear → `/msimamizi/mipangilio`; toggle Fridge off; reload `/muuzaji/friji` (must show the disabled panel); direct API call must return 403; toggle on again and the same fridges reappear.

---

## 9. Rollback procedures

Application rollback (safe, no data loss):

1. Redeploy the previous application build.
2. The previous build ignores `business_settings`, so features revert to their pre-settings behaviour (Fridge and Services always on, scanner off).

Schema rollback (only if the module must be removed entirely):

```sql
-- Supabase SQL Editor, explicit authorization required
begin;
drop trigger if exists business_settings_touch_updated_at on public.business_settings;
drop function if exists public.touch_business_settings_updated_at();
drop table if exists public.business_settings;
commit;
```

Local:

```bash
php artisan migrate:rollback --step=1
```

### Known rollback limitations

- Dropping `business_settings` discards the administrators' feature/notification choices. It does **not** delete or change fridges, stock, movements, alerts, services, service sales, sales, products, users or branches.
- There is no automatic "restore previous switches" because the previous application version had no switches.
- Branch deactivation is reversible from Settings (Rudisha) or `PUT /api/branches/{id}` with `{"status":"active"}`; a dropped branch row is not part of this migration.

---

## 10. Centralized feature service

`App\Services\BusinessSettings`

```php
BusinessSettings::forBusiness($businessId);          // merged settings + defaults
BusinessSettings::enabled('fridge', $businessId);    // bool
BusinessSettings::features($businessId);             // ['fridge'=>bool,'scanner'=>bool,'services'=>bool]
BusinessSettings::update($businessId, $changes, $userId); // persists + clears cache
```

- Cached per business for 30 minutes under `business_settings:v1:{business_id}`.
- Every successful write calls `Cache::forget`, so a toggle applies on the next request.
- Read failures (table not yet migrated) return defaults.
- `BaseController::requireFeature()` is the reusable API gate.

---

## 11. API surface

| Method | Path | Who | Behaviour |
|---|---|---|---|
| `GET` | `/api/settings` | business admin | One snapshot: user, business, all branches (active + inactive), effective settings, options. |
| `GET` | `/api/settings/features` | any business member | The three switches only. |
| `PUT` | `/api/settings/features` | business admin | Partial update of known keys; audited old/new; invalid values → 422. |
| `PUT` | `/api/user/profile` | authenticated member | Existing writer, now validated; protected fields ignored. |
| `PUT` | `/api/user/language` | authenticated member | Existing language store/allow-list. |
| `PUT` | `/api/user/theme` | authenticated member | Existing theme store/allow-list. |
| `PUT` | `/api/user/password` | authenticated member | Existing secure password change. |
| `POST` | `/api/branches` | business admin | Create a branch of a branch-structured business. |
| `PUT` | `/api/branches/{id}` | business admin | Edit name/location/coords/status within the caller's business. |
| `DELETE` | `/api/branches/{id}` | business admin | Deactivate (never delete). Last active branch → 409. |
| `POST` | `/api/settings/business-structure/branchful` | business admin | Atomic conversion with the first branch. See the conversion document. |
| `POST` | `/api/settings/business-structure/branchless` | business admin | Atomic consolidation with unified business details. |

Authorization uses the verified JWT `business_id`; the client cannot submit another business id.

---

## 12. Web and mobile synchronization

- Both surfaces call the same endpoints; the backend is authoritative.
- Web caches nothing beyond the browser session; `dm-features.js` refreshes on page load.
- Mobile caches the feature map in AsyncStorage and refreshes when the app returns to the foreground; a stale cache cannot enable a disabled module because the API refuses it.
- Enabling/disabling Fridge changes: web seller sidebar link, mobile seller tab, direct-visit blocking, and API access. Service creation/selling entry points follow the Services switch; service history remains readable.
- Scanner is opt-in: the switch hides its web/mobile controls and gates scanner lookup/receiving at the API.

---

## 13. Testing

`tests/Feature/AdminSettingsTest.php` (20 tests, 115 assertions):

1. Consolidated admin snapshot (branch list + defaults).
2. Seller can read only the feature map; settings module and writes are 403.
3. Feature switches persist, cache is invalidated, audit contains old/new.
4. Missing settings row keeps Fridge/Services enabled and Scanner disabled until an admin opts in.
5. Fridge API is 403 when off; re-enabling returns the same stored fridge.
6. Service writes are 403 when Services is off.
7. Branch create/edit works; another business's branch is 404; foreign branch is unchanged.
8. Last active branch cannot be deactivated; deactivation preserves branch rows and seller assignment.
9. Profile update ignores `role`, `status`, `business_id`, `branch_id`, `email`.
10. Invalid profile data is 422.
11. Non-branch business cannot create branches (422).
12. Non-boolean feature value is 422 and persists nothing.
13. Notification preference hides alerts while monitoring still records them.
14. Language and theme controls persist and reject invalid values.
15–20. Structure conversion: both directions, fresh tokens, seller denial, wrong-structure and unsafe-consolidation errors, and stale-token resolution.

Run results are reported in the delivery message; do not deploy on unverified changes.

---

## 14. Known limitations / outstanding items

- **Branch phone/email/description/logo**: not supported by the current `branches` table, so the UI does not pretend to edit them. Adding them is a separate additive migration + UI change.
- **User avatar / email change**: `users` has no avatar column, and there is no email-change verification flow. Email is displayed read-only in Settings. Password changes use the existing endpoint.
- **Scanner**: opt-in barcode receiving for admins and barcode selection for seller sales/transfers; it never creates stock by itself.
- **Business-level notification delivery**: the two stock-alert switches control whether open alerts are presented in the Fridge response; the admin "send notification" endpoint is platform-wide and not affected. No email/push stock-notification pipeline exists to gate.
- **Theme and language options**: both are real controls in Other Settings. Language writes `users.language`; theme writes `users.preferred_theme` and applies `data-theme` immediately through the existing theme system.
- **Branch creation for non-branch businesses**: intentionally blocked. Converting a single-location business into a branch business remains an explicit, separate decision.
- Production SQL has **not** been executed by this change.
