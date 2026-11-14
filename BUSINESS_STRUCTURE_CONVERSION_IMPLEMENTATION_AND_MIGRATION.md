# Business Structure Conversion — Implementation and Migration

Branchless ↔ branchful conversion for Dukamkononi, on Laravel + Supabase/PostgreSQL + Expo.

---

## 1. Existing architecture discovered

| Concern | Current design |
|---|---|
| Structure flag | `businesses.business_structure` (`non_branch` default, `branch`) — already explicit, **not** inferred from branches. Added by `20261110000001_create_branches_and_business_structure.sql`. |
| Branch identity | `branches.id`, owned by `businesses.id`; `users.branch_id` assigns sellers. |
| Business link | `users.business_id`. `products`, `sales` and `customers` have **no** `business_id`; they belong to a business through `seller_id → users.id`. `services`, `service_sales`, `office_expenses`, fridges and fridge stock carry explicit `business_id`. |
| Branch pointers | `branch_id` exists on products, sales, customers, services, service_sales, office_expenses, fridges, fridge_stock, fridge_stock_movements, fridge_stock_alerts, sale_item_fridge_allocations. |
| Stock model | `products.stock` = outside stock; `fridge_stock.quantity` = inside stock. The invariant is never rewritten by a structure change. |
| Context | Operational queries narrow to the selected branch only in `individual_branch` context; `non_branch` and `cross_branch` deliberately do not filter. |
| Auth | Verified JWT `business_id` + signed branch context. Structure is now read from the business row on every request (`BranchContext::resolve`), so a token issued before a conversion cannot preserve the old mode. |
| Transactions | PostgREST cannot span several tables from Laravel, so both conversions are single PostgreSQL functions. |

Existing data is unambiguous: every business already has an explicit `business_structure`, so no business is guessed to be branchless or branchful from its number of branches. A one-branch business can remain branchful.

---

## 2. Database changes

**No columns or tables are added.** The structure column and every required branch pointer already exist. The new SQL migration adds two functions:

`supabase/migrations/20261114000003_add_business_structure_conversion.sql`

1. `convert_business_to_branch(p_business_id, p_branch_name, p_location, p_latitude, p_longitude, p_actor)`
2. `convert_business_to_branchless(p_business_id, p_business_name, p_location, p_description, p_business_type, p_logo_url, p_latitude, p_longitude, p_actor)`

Both are `SECURITY DEFINER`, `SET search_path = public`, and execute permission is revoked from `public` and granted only to `service_role` (the Laravel backend's key). Browser/anon keys cannot call them.

No Laravel schema migration is required for the local SQLite test database because conversion state lives entirely in Supabase columns/functions. Tests fake the RPC calls; they do not pretend to exercise PostgreSQL transactions.

---

## 3. Conversion logic

### A. Branchless → branchful

Endpoint: `POST /api/settings/business-structure/branchful`

1. Controller verifies `isAdmin` and derives `business_id` from the JWT. Sellers receive 403.
2. Validates `branch_name` (2–120), optional location and coordinates.
3. Rejects a duplicate branch name for the same business (field-level 409).
4. Calls the RPC. Inside one transaction:
   - `SELECT ... FROM businesses WHERE id = ... FOR UPDATE` locks the business, so a concurrent conversion waits and then sees `ALREADY_BRANCHFUL`.
   - Re-validates structure and branch name.
   - Inserts the first active branch.
   - Sets every **seller** of the business to that branch (administrators stay unassigned and choose cross-branch/individual).
   - Moves products, sales and customers belonging to the business's members to the branch, and services, service_sales, office_expenses, fridges, fridge stock, movements, alerts and sale allocations by `business_id`.
   - Sets `business_structure = 'branch'`.
   - Writes an audit row (`user_logs`) with business id, actor, from/to and branch id.
   - Returns the branch.
5. Controller returns a fresh JWT with `business_structure=branch`, `branch_context=cross_branch` and no branch id. The old token would otherwise still say `non_branch`.

Quantities are never touched: only `branch_id` pointers move. Sales, payments and invoice numbers are unchanged.

### B. Branchful → branchless

Endpoint: `POST /api/settings/business-structure/branchless`

1. Same admin/ownership checks.
2. Validates the unified business name (2–150), optional location/description/type/logo/coordinates.
3. Calls the RPC. Inside one transaction:
   - Locks the business row.
   - Refuses `ALREADY_BRANCHLESS`.
   - Refuses `BUSINESS_NAME_TAKEN` if the new name/key is used by another business.
   - **Safety check:** refuses `UNSAFE_DUPLICATE_PRODUCTS` when the same product name, SKU or barcode exists in more than one branch (including NULL vs branch). Merging those rows would either double-count stock or discard it.
   - Updates the business identity in place (same `businesses.id`) and sets `business_structure = 'non_branch'`.
   - Clears `users.branch_id` for all members, so login/operations no longer require branch selection. Roles are unchanged.
   - Sets the business's active branches to `inactive`; branch rows and all their history are preserved.
   - Leaves `branch_id` on every operational row untouched: branchless scope ignores it, so all stock, sales, services, expenses and reports remain visible and auditable at their original branch.
   - Writes the audit row.
4. Returns a fresh non-branch JWT.

Deliberately **not** done: merging product rows, summing stock into another row, deleting branches, rewriting sales, or detaching users. If those would be required, the RPC stops.

---

## 4. Record preservation

| Record | Branchless → branchful | Branchful → branchless |
|---|---|---|
| Business row | Same `id`, name unchanged | Same `id`, identity fields updated |
| Users | Same ids/roles; sellers assigned to first branch | Same ids/roles; `branch_id` cleared |
| Products | `branch_id` set to first branch; stock/price/history unchanged | `branch_id` left as historical; visible business-wide |
| Sales / sale_items / payments | `branch_id` set; amounts/invoices untouched | untouched; visible business-wide |
| Customers | `branch_id` set | untouched |
| Services / service sales | `branch_id` set | untouched; still readable |
| Office expenses | `branch_id` set | untouched |
| Fridges / stock / movements / alerts / allocations | `branch_id` set | untouched; fridge feature continues if enabled |
| Branches | Existing inactive history plus the new active branch | Kept, set inactive |

No `DELETE` runs in either function.

---

## 5. Concurrency, retry and failure behavior

- Business row lock serializes conversions per business.
- Unique branch-name check is repeated under the lock; the existing index is the final guard.
- Any raised exception aborts the whole PostgreSQL transaction: the business stays in its previous structure and no pointer is moved.
- Repeating the same request is safe: it returns `ALREADY_BRANCHFUL` / `ALREADY_BRANCHLESS` instead of duplicating a branch.
- The controller maps stable SQL codes to HTTP: `ALREADY_*` 409, name conflicts 409, validation 422, business missing 404, unsafe consolidation 409, missing function 503 `CONVERSION_NOT_INSTALLED`, otherwise 500.
- Network failure after commit cannot half-convert; the client can reload and read `GET /api/settings` to see the committed structure.

---

## 6. Web and mobile

- **Web Settings → Mipangilio Mingine → Muundo wa Biashara** shows the current structure and one action. Modals collect the first branch or the unified business identity and repeat the consequences before submitting.
- **Mobile Settings → Mipangilio tab → Muundo wa Biashara** mirrors both flows with native confirmation dialogs.
- Both store the returned token and reload/refresh, so the dashboard, tab bars and branch selectors immediately use the new structure.
- Web/mobile never call the database functions directly.
- Sellers see neither action (server rejects them regardless).

---

## 7. Migration order

1. `20261110000001_create_branches_and_business_structure.sql` (must already be applied).
2. `20261114000002_create_business_settings.sql` (settings module).
3. `20261114000003_add_business_structure_conversion.sql` (this feature).

Apply 3 after 2; it has no dependency on the settings table, only on `businesses`, `branches` and the operational tables.

---

## 8. Local testing

```bash
php artisan test tests/Feature/AdminSettingsTest.php
php artisan test
cd Duka_mkononi && npx tsc --noEmit --skipLibCheck
```

The tests cover admin-only access, both conversion payloads, fresh tokens, wrong-structure rejection, the unsafe-consolidation response, and stale-token structure resolution. PostgreSQL functions cannot run in the SQLite test database; that limitation is reported, not hidden.

Manual staging check (after applying SQL):

1. Create a branchless test business with a product and a sale.
2. Settings → convert to branchful with one branch; verify product/sale totals unchanged and seller login lands in the branch.
3. Add a second branch, move/copy a product there.
4. Attempt conversion to branchless while a duplicate product name exists in both branches → expect the 409 explanation.
5. Rename/SKU the duplicate apart, convert again → products, sales, expenses and reports remain; login no longer asks for a branch.

---

## 9. Production deployment

**Do not execute without explicit authorization.**

```text
1. Back up the affected schema/tables (Supabase dashboard backup or pg_dump).
2. SQL Editor: run 20261114000003_add_business_structure_conversion.sql.
3. Verify:
   select proname from pg_proc
    where proname in ('convert_business_to_branch','convert_business_to_branchless');
4. Deploy the Laravel/Blade build and the Expo update.
5. Smoke-test on a staging business; then confirm a real business only with the owner present.
```

Do not use `migrate:fresh`, `db:wipe`, or destructive SQL.

---

## 10. Verification procedures

```sql
-- No half-converted business: every branchful business has an active branch.
select b.id from businesses b
 where b.business_structure = 'branch'
   and not exists (select 1 from branches br where br.business_id = b.id and br.status = 'active');

-- No branchless business still holding seller assignments.
select u.id from users u join businesses b on b.id = u.business_id
 where b.business_structure = 'non_branch' and u.branch_id is not null;

-- Audit trail.
select created_at, user_id, action, details from user_logs
 where action = 'BUSINESS_STRUCTURE_CONVERTED' order by created_at desc;

-- Unsafe duplicates that would block a consolidation.
select lower(btrim(p.name)) as name, count(distinct coalesce(p.branch_id, '00000000-0000-0000-0000-000000000000'::uuid)) as branches
  from products p join users u on u.id::text = p.seller_id
 group by 1 having count(distinct coalesce(p.branch_id, '00000000-0000-0000-0000-000000000000'::uuid)) > 1;
```

---

## 11. Rollback and recovery

- Application rollback: redeploy the previous build. Structure values and data are untouched.
- Conversion rollback: there is **no automatic inverse** because the historical choice of branches cannot be reconstructed after consolidation. Recovery options:
  - Branchful → branchless was committed: re-convert to branchful (Settings) and assign the appropriate rows to the desired branches manually or via SQL; all rows still exist.
  - Branchless → branchful was committed: convert back and update the unified business details; the original branch row stays inactive for history.
- Schema rollback (only to remove the feature):

```sql
drop function if exists public.convert_business_to_branch(uuid, text, text, double precision, double precision, uuid);
drop function if exists public.convert_business_to_branchless(uuid, text, text, text, text, text, double precision, double precision, uuid);
```

Dropping the functions does not change any business data. It does disable structure conversion until redeployed.

### Known limitations / risks

- **Duplicate-product consolidation is refused, not performed.** Multi-branch businesses with the same product name/SKU/barcode across branches must normalize those records before converting. This is intentional protection against stock loss/double-count.
- The conversion updates the historical `branch_id` pointer on products/sales/customers when going branchless → branchful so the unified records are visible in the first branch. Amounts, dates, invoices and items are unchanged.
- Notification delivery has no push/email stock pipeline to gate; this feature does not create one.
- The SQL functions are not executed by the automated suite (no PostgreSQL in CI). They must be verified on staging before production use.
- Branch phone/email/logo fields do not exist in the schema and are not part of either form.
