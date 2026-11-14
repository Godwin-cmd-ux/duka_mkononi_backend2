# Branches — Implementation and Operations Guide

How multi-branch businesses work in Dukamkononi across PostgreSQL/Supabase, Laravel, Blade and Expo.

This guide describes the code that exists, not an intended design. It covers where branches come from, how one is added or changed, how a user is placed in a branch, how every request is scoped, and what happens when a branch is deactivated or the business changes structure.

**Contents**

1. [The mental model](#1-the-mental-model)
2. [Data model](#2-data-model)
3. [How a branch is created](#3-how-a-branch-is-created)
4. [How branch context is issued and verified](#4-how-branch-context-is-issued-and-verified)
5. [How requests are scoped](#5-how-requests-are-scoped)
6. [Managing branches](#6-managing-branches)
7. [UI and API surface by platform](#7-ui-and-api-surface-by-platform)
8. [API reference](#8-api-reference)
9. [Error codes](#9-error-codes)
10. [Security invariants](#10-security-invariants)
11. [Audit trail](#11-audit-trail)
12. [Deployment and migrations](#12-deployment-and-migrations)
13. [Test coverage](#13-test-coverage)
14. [Operational recipes](#14-operational-recipes)
15. [Known limitations and deliberate choices](#15-known-limitations-and-deliberate-choices)
16. [Quick reference](#16-quick-reference)

---

## 1. The mental model

There are two different identifiers, and they are never interchangeable:

| Identifier | Meaning | Scope |
|---|---|---|
| `business_id` | The whole business — the tenant | Everything owned by the business |
| `branch_id` | One shop/location inside that business | Only the operational rows that belong to that location |

A branch is **not** a separate tenant. It has no independent owner, subscription, login or product catalogue. It is an operational subdivision of one business. That is why branch edits never change the business row and why a seller can be moved between branches without recreating their account.

Three facts drive the whole implementation:

1. **Structure is explicit.** `businesses.business_structure` is either `non_branch` or `branch`. It is never inferred from the number of branch rows. A branch business with one branch is still a branch business.
2. **The server decides the branch.** For an authenticated session, the only place a client may propose a branch is `POST /api/branch-context`; ownership and active status are verified there, and the result is signed into a new JWT. Normal operational requests never accept a `branch_id` parameter. (Seller registration is the separate, pre-session case where a joining seller chooses a branch; it is re-validated independently.)
3. **Scoping is enforced at the API boundary.** Web and mobile navigation hide what a user cannot use, but `BaseController::scopeBranch()` and `requireWriteBranch()` are the actual security boundary. A hand-crafted request is treated exactly like a tampered UI.

### 1.1 The four runtime contexts

`App\Services\BranchContext` defines the contexts that can appear in a token:

| Context | Structure | Branch id | Meaning |
|---|---|---|---|
| `non_branch` | `non_branch` | `null` | Legacy single-location business. All data is business-wide. |
| `unselected` | `branch` | `null` | Branch business whose administrator has not chosen a branch yet. Fail-closed. |
| `individual_branch` | `branch` | selected branch UUID | One branch. Reads and writes are scoped to it. |
| `cross_branch` | `branch` | `null` | Business-wide dashboard. Reads span all branches; writes are refused. |

`unselected` is deliberately not the same as `cross_branch`. In `unselected`, queries are forced to return nothing; in `cross_branch`, queries intentionally return every branch. Conflating the two would show a branch administrator every branch before they chose one.

---

## 2. Data model

### 2.1 `businesses`

```sql
business_structure text not null default 'non_branch'
    check (business_structure in ('non_branch', 'branch'))
```

The default and the migration backfill both set existing businesses to `non_branch`. No branch rows are invented for them. Their login, dashboard, reports and data remain exactly as before.

### 2.2 `branches`

```sql
create table public.branches (
    id          uuid primary key default gen_random_uuid(),
    business_id uuid not null references public.businesses (id) on delete cascade,
    branch_name text not null,
    location    text,
    latitude    double precision,
    longitude   double precision,
    status      text not null default 'active',
    created_at  timestamptz not null default now(),
    updated_at  timestamptz not null default now(),
    constraint branches_name_not_blank check (btrim(branch_name) <> ''),
    constraint branches_status_check check (status in ('active', 'inactive')),
    constraint branches_latitude_check check (latitude is null or (latitude between -90 and 90)),
    constraint branches_longitude_check check (longitude is null or (longitude between -180 and 180))
);

create unique index branches_business_name_unique
    on public.branches (business_id, lower(btrim(branch_name)));

create index branches_business_status_index
    on public.branches (business_id, status);
```

Key properties:

- `branches.id` **is** the `branch_id` used by every operational table. There is no separate branch code.
- Names are unique per business, case-insensitively and ignoring surrounding whitespace. The unique index applies to active and inactive branches alike, so a deactivated branch keeps its name reserved.
- `status` is only `active` or `inactive`. There is no `deleted` state because branches are never hard-deleted through the application.
- A branch always belongs to exactly one business. Deleting the business cascades to its branches.

### 2.3 `users.branch_id`

```sql
alter table public.users add column branch_id uuid;
alter table public.users
    add constraint users_branch_id_fkey
    foreign key (branch_id) references public.branches (id) on delete set null;
```

- Only **sellers** are assigned a branch. Administrators manage the whole business and choose a context at runtime instead of being pinned to one branch.
- `NULL` means either “not a branch business”, “this seller has no assignment”, or “this administrator”. The role and business structure disambiguate it.
- `ON DELETE SET NULL` protects accounts if a branch row is ever removed directly in SQL. The application itself only deactivates, so this normally never fires.

### 2.4 Branch pointers on operational tables

`branch_id uuid` exists on:

`products`, `sales`, `customers`, `services`, `service_sales`, `office_expenses`, `fridges`, `fridge_stock`, `fridge_stock_movements`, `fridge_stock_alerts`, `sale_item_fridge_allocations`.

The columns are nullable on purpose:

| Value | Meaning |
|---|---|
| A branch UUID | The row belongs to that branch. |
| `NULL` on a non-branch business | Legacy/normal business-wide row. |
| `NULL` on a branch business | A legacy row, or an intentionally shared service. It is **not** a wildcard that makes the row visible in every branch. |
| `NULL` on `services` | A business-wide service recipe, intentionally available to every branch. |

`sale_items` and `service_sale_items` intentionally do **not** carry `branch_id`. Their branch is the branch of the parent sale; duplicating it would create a second source of truth that could drift.

### 2.5 How rows are linked to a business

This matters when reading branch-scoped code:

- `users` carries both `business_id` and `branch_id`.
- `products`, `sales` and `customers` have no `business_id`. They belong to a business through `seller_id → users.id`.
- `services`, `service_sales`, `office_expenses`, fridges and fridge stock carry explicit `business_id` in addition to `branch_id`.
- Branch conversion therefore moves products/sales/customers through their seller, and moves the explicitly-owned tables directly by `business_id`.

### 2.6 Authoritative DDL

- PostgreSQL/Supabase: `supabase/migrations/20261110000001_create_branches_and_business_structure.sql`
- Local/CI mirror: `database/migrations/2026_11_10_000001_create_branches_and_business_structure.php`

The SQL file is authoritative. The Laravel mirror keeps a fresh local or CI database structurally similar for tests, but it does not reproduce every PostgreSQL constraint (for example the case-insensitive unique branch-name index). Tests that depend on those constraints fake the REST layer rather than pretending SQLite enforces PostgreSQL rules.

---

## 3. How a branch is created

There are exactly three creation paths. They all end with a row in `branches` whose `status` is `active`.

### 3.1 At administrator signup (the normal path)

The administrator chooses the business structure and, if branchful, enters one or more branches before the account exists.

#### Web

`resources/views/admin-signup.blade.php`:

- A select with `Biashara Bila Matawi` (`non_branch`) and `Biashara Yenye Matawi` (`branch`).
- Choosing `branch` reveals a repeatable branch editor: branch name, location, optional GPS pin, add/remove row.
- The payload includes:

```json
{
  "business_structure": "branch",
  "branches": [
    {
      "branch_name": "Tawi Kuu",
      "location": "Kinondoni",
      "latitude": -6.7924,
      "longitude": 39.2083
    }
  ]
}
```

#### Mobile

`Duka_mkononi/app/admin_signup.tsx` implements the same choice with a structure toggle and a repeatable branch list. It enforces that every branch has a name and location before continuing and sends the same `business_structure` / `branches` fields.

#### API flow

1. `POST /api/register/initiate`
   - Validates `business_structure` against `BranchContext::STRUCTURES`.
   - Defaults it to `non_branch` when omitted.
   - Runs `normalizeBranches()`:
     - Must be an array.
     - Every branch needs a non-empty `branch_name`.
     - Every branch needs a non-empty `location`.
     - Coordinates are optional, but must be numeric and within ±90 / ±180.
     - Missing coordinates stay `null`; they are never silently stored as `0,0`.
   - A `branch` business with zero branches is rejected with `Biashara yenye matawi inahitaji tawi moja au zaidi`.
   - The validated payload is cached with the registration OTP for 10 minutes.
2. `POST /api/register/verify-otp`
   - Re-signs the cached payload into a short-lived `registration_verified` JWT, including `business_structure` and `branches`.
3. `POST /api/register`
   - Decodes that signed token; the fields are not re-read from the request body.
   - Calls `AuthController::createBusinessWithBranches()`.
   - That calls the PostgreSQL function `register_business_with_branches(...)` with a JSON array of branches.
   - Only after the business and branches exist is the administrator user row created.

#### The atomic registration function

`register_business_with_branches(p_business_name, p_legacy_name_key, p_business_structure, p_business_type, p_business_description, p_business_location, p_branches)`:

- Runs inside one PostgreSQL transaction.
- Rejects a blank business name/key and an invalid structure.
- If the business key already exists, returns the existing business and its branches with `created = false` instead of creating a duplicate.
- For `branch`, requires at least one branch.
- For each branch, requires a name and a location, validates coordinates, and inserts it as `active`.
- Any invalid branch raises an exception and rolls back the business row too. A branch business can never be committed half-registered.

Because a branch business must be atomic, `AuthController` does **not** fall back to the non-branch `BusinessResolver` path when the RPC fails. It returns `BUSINESS_CREATE_FAILED` instead of silently creating a branchless business. The fallback exists only for `non_branch` registrations, preserving the old behavior on deployments where the RPC has not yet been installed.

> Signup requires a location for every branch. Adding a branch later does not, because the branch may not have a named area yet. This asymmetry is intentional.

### 3.2 From the administrator Settings module

Only a business whose **stored** structure is `branch` can use this path.

- Web: `Msimamizi → Mipangilio → Biashara na Matawi → Matawi → + Ongeza Tawi`.
- Mobile: `Msimamizi → Mipangilio → Matawi → + Ongeza Tawi`.
- API: `POST /api/branches`.

The UI shows the branch card only for branch businesses. A non-branch business instead sees an explanation and the structure-conversion control. That is presentation only; the API repeats the check.

Request:

```http
POST /api/branches
Authorization: Bearer <admin token>
Content-Type: application/json

{
  "branch_name": "Tawi la Mlimani",
  "location": "Mlimani City",
  "latitude": -6.7712,
  "longitude": 39.2211
}
```

Server checks, in order:

1. Caller is an administrator (`isAdmin`, which includes the platform admin roles).
2. Caller has a `business_id` from the verified JWT — never from the request.
3. `businesses.business_structure` for that business is `branch`. Otherwise `422 STRUCTURE_NOT_BRANCH`.
4. `branch_name` is 2–120 characters; location ≤255; coordinates in range.
5. No branch of this business already uses that name, case-insensitively and trimmed. Otherwise `409 BRANCH_NAME_TAKEN`.
6. Inserts with a server-generated UUID and `status = active`.
7. Writes a `BRANCH_CREATED` audit row and returns `201`.

The database unique index is the final guard against two simultaneous creates with the same name; it is authoritative even when the pre-check races.

### 3.3 By converting a branchless business

`POST /api/settings/business-structure/branchful` registers the first branch and converts the business in one database transaction. It also assigns every seller to that branch and points all existing operational rows at it. The full rules are in `BUSINESS_STRUCTURE_CONVERSION_IMPLEMENTATION_AND_MIGRATION.md`.

A later branchless → branchful conversion creates a **new** active branch. The old branch rows remain inactive history.

### 3.4 Creation paths at a glance

| Path | Endpoint | Requires existing branch structure? | Who | Atomic guarantee |
|---|---|---|---|---|
| Signup | `POST /api/register` (after OTP) | No — chooses it | New administrator | Business + all branches in one SQL transaction |
| Settings | `POST /api/branches` | Yes | Business administrator | Single row insert; DB unique index |
| Conversion | `POST /api/settings/business-structure/branchful` | No — creates it | Business administrator | Conversion RPC transaction |

---

## 4. How branch context is issued and verified

### 4.1 Login

`POST /api/login` resolves the business and calls `BranchContext::resolve()` before minting the token.

Rules:

- **Non-branch business** → `non_branch`, no branch id.
- **Branch business, seller** → the seller’s database `users.branch_id`, but only if that branch is active and belongs to the same business.
- **Branch business, admin, no selection** → `unselected`.
- **Branch business, admin with a valid selection** → `individual_branch`.
- **Branch business, admin with “all branches”** → `cross_branch`.

For sellers, the response includes `branch_id`, `branch_name`, `branch_location` and `branch_context`. A seller is never allowed to remain in an ambiguous state: if their assigned branch is missing, inactive or belongs to another business, login is refused with `403 BRANCH_UNAVAILABLE`. It does not silently fall back to another branch or to the business-wide view.

A branch administrator logs in as `unselected` and receives `needs_branch_selection: true`. The web login sends them to `/select-branch`; the mobile dashboard redirects them to `/select-branch` before loading operational data.

### 4.2 Selecting or switching a branch

For an existing authenticated session, `POST /api/branch-context` is the only endpoint that accepts a client-proposed branch:

```json
{ "branch_id": "0d1f...uuid" }
```

- `branch_id` = an active branch of the caller’s business → `individual_branch`.
- `branch_id` = `null` / missing / empty string → `cross_branch`.
- A non-branch business returns a `non_branch` token rather than an error.
- A seller receives `403 BRANCH_CONTEXT_FORBIDDEN`; sellers cannot choose their branch.
- An unknown, inactive or other-business branch returns `404 BRANCH_NOT_FOUND` and writes a `BRANCH_CONTEXT_DENIED` audit row.

The response contains a **new signed JWT** and the branch payload. The client replaces its stored token:

- Web: `localStorage.userToken`, `branchId`, `branchContext`, `branchData`.
- Mobile: `AsyncStorage` keys `userToken`, `branchId`, `branchContext`, `branchData`.

The old token is not revoked cryptographically; it remains scoped to whatever branch it carried. Clients replace it, but a copied old token could still read the old branch until it expires. This is why branch switching is a context change, not a security revocation event. Seller reassignment is different: `BranchContext::resolve()` reads the seller’s database assignment on every request, so the database assignment wins over any old token claim.

### 4.3 Every authenticated request

`AuthenticateJwt` decodes the token, loads the user from the database, resolves the business, then calls `BranchContext::resolve()` with the signed claims.

Inside `resolve()`:

1. **Stored structure wins.** It reads `businesses.business_structure`. A token minted before a structure conversion still carries the old value, so honouring the claim would leave the business in the wrong mode until every user logged in again. The claim is only a fallback when the business row cannot be read.
2. **Sellers are bound to the database.** Before the claims are considered, the user’s current `users.branch_id` is loaded. If it points at an active branch of the business, that is the context. A demoted administrator with an old `cross_branch` token is therefore reduced to their assigned branch; a cross-branch token cannot hand a seller every branch.
3. **Claimed branch ids are re-verified.** An administrator’s claimed branch must still exist, be active and belong to the same business. If not, the request becomes `unselected`, not an unscoped read.
4. **Membership is required.** A non-business role in a branch business becomes `unselected`.
5. **`unselected` fails closed.** `BaseController::scopeBranch()` applies an impossible UUID filter (`id = 00000000-0000-0000-0000-000000000000`), so the query returns no rows instead of everything.

The verified results are attached to the request as:

- `jwt_business_structure`
- `jwt_branch_context`
- `jwt_branch_id`

Controllers read these through `BaseController`. They never read a branch id from the query string or body for normal operations.

### 4.4 Context resolution table

| Stored structure | Role | Database / claim | Result |
|---|---|---|---|
| `non_branch` | Any business member | — | `non_branch`, no branch |
| `branch` | Seller | `users.branch_id` active and owned | `individual_branch` |
| `branch` | Seller | no assignment, or inactive/foreign branch | `unselected` (login itself refuses) |
| `branch` | Admin | valid claimed branch | `individual_branch` |
| `branch` | Admin | claimed `cross_branch` | `cross_branch` |
| `branch` | Admin | no valid selection | `unselected` |
| `branch` | Non-member | — | `unselected` |

---

## 5. How requests are scoped

### 5.1 Reads

`BaseController::scopeBranch($query, $request, $shared = false)`:

| Context | Query behavior |
|---|---|
| `non_branch` | No filter — legacy business-wide behavior |
| `cross_branch` | No branch filter — every branch of the business |
| `individual_branch` | `where branch_id = selected branch` |
| `unselected` | Impossible filter — zero rows |

The business filter is applied separately and always comes from the verified JWT. Branch scoping never widens the tenant boundary.

`$shared = true` is used for services: an individual branch sees rows with its `branch_id` **or** `branch_id IS NULL`, because a null service is intentionally business-wide. This exception is explicit and limited to services; it is not a general “null means all branches” rule.

Important consequences:

- A legacy product with `branch_id = NULL` is **not** visible in an individual branch. The old branchless row cannot be used to sell another branch’s stock.
- A branch’s cross-branch dashboard still sees the history of inactive branches, because cross-branch does not filter by status.
- An administrator whose currently selected branch was deactivated falls back to `unselected` and sees no rows until they choose again.

### 5.2 Writes

`BaseController::requireWriteBranch()` returns `[branch_id, error]`:

| Context | Result |
|---|---|
| `non_branch` | `[null, null]` — rows stay business-wide as before |
| `individual_branch` | `[selected branch, null]` — the row is stamped with that branch |
| `cross_branch` | `[null, 422 BRANCH_REQUIRED]` — “cross-branch is a summary context” |
| `unselected` | `[null, 409 BRANCH_SELECTION_REQUIRED]` |

This is used by products, sales, customers, expenses, services, service sales, fridge writes and AI imports.

Additional ownership checks on top of the stamp:

- **Product update/delete:** if a branch is selected, the existing product’s `branch_id` must equal it.
- **Sale creation:** every sold product must belong to the selected branch. A `NULL` product branch is rejected in individual context, even if the product is otherwise valid.
- **Customer update:** same branch-ownership check.
- **Service sale:** the service must be business-wide (`branch_id NULL`) or belong to the selected branch.
- **Fridge stock:** the fridge, product and stock row must agree on the branch; the atomic RPC receives `p_branch_id`.

`cross_branch` is deliberately read-only. An administrator who wants to record a sale must first choose the branch that made it.

### 5.3 Reports and dashboards

Branch-aware admin listings apply `scopeBranch()` after business scoping. In practice:

- **Individual branch:** sellers assigned to that branch, that branch’s products, sales, customers, expenses, services and service sales.
- **Cross-branch:** all branches of the business, including inactive branch history, plus each seller’s branch name/location.
- **Non-branch:** unchanged business-wide results.

Sales reports also pass the individual branch id into the service-sales report, so product sales and service sales in one response describe the same branch.

Not every endpoint in the application is branch-scoped. Public discovery, orders, inquiries and the legacy `GET /api/admin/payments` listing are business/platform-level features rather than branch dashboards. The branch model covers the operational dashboards, inventory, sales, customers, expenses, services, fridges and the associated reports. Do not assume an endpoint is branch-aware just because a branch is selected; check whether it calls `scopeBranch()` / passes the branch into its query.

### 5.4 What “cross-branch” is not

`cross_branch` is not a branch and has no branch row. It is a token context meaning “all branches of this business”. It cannot be assigned to a seller, cannot own inventory, and cannot be used as the branch of a write.

---

## 6. Managing branches

### 6.1 Listing

There are two different lists, intentionally:

| Surface | Endpoint | Contents |
|---|---|---|
| Branch selector / dashboard card | `GET /api/branches` | **Active** branches only, plus current context |
| Settings management | `GET /api/settings` | **All** branches, active and inactive, ordered by name |

`GET /api/branches` is readable by any authenticated business member. For a seller, the list is reduced to their own assigned branch (if any); a seller never sees another branch as a selectable option.

Response shape:

```json
{
  "success": true,
  "business": {
    "id": "b-uuid",
    "business_name": "Kinondoni Supermarket",
    "business_location": "Dar es Salaam",
    "business_logo_url": null,
    "business_structure": "branch"
  },
  "branches": [
    {
      "id": "br-uuid",
      "branch_id": "br-uuid",
      "business_id": "b-uuid",
      "branch_name": "Kinondoni",
      "location": "Kinondoni",
      "latitude": -6.79,
      "longitude": 39.2,
      "status": "active"
    }
  ],
  "context": {
    "branch_context": "individual_branch",
    "branch_id": "br-uuid"
  },
  "can_select_branch": true
}
```

`can_select_branch` is true only for an administrator of a branch business.

### 6.2 Editing

`PUT /api/branches/{id}` accepts any subset of:

```json
{
  "branch_name": "Kinondoni Mpya",
  "location": "Kinondoni",
  "latitude": -6.79,
  "longitude": 39.2,
  "status": "active"
}
```

- Administrator only, own business only.
- The branch id never changes. Sellers, products, sales, stock, fridges and reports keep pointing at the same row.
- Renaming a branch does not rename the business and does not move any data.
- Name uniqueness is re-checked case-insensitively/trimmed, excluding the branch being edited.
- An empty payload returns `422 Hakuna taarifa za kubadili`.
- A status change is subject to the last-active-branch guard below.
- The audit row records only the fields that actually changed, with `from` and `to` values.

### 6.3 Deactivating and reactivating

`DELETE /api/branches/{id}` does **not** delete anything. It sets `status = 'inactive'`.

Why deactivate instead of delete:

- Sellers keep their `users.branch_id` assignment.
- Products, sales, customers, stock, fridges, services, expenses and reports keep their original `branch_id`.
- Reactivating the same row restores access to all of it.
- Auditing and historical reporting remain intact.

Behavior:

- Already inactive → `200` “Tawi limezimwa” (idempotent).
- Deactivating the **last active branch** → `409 LAST_ACTIVE_BRANCH`. The business must always keep at least one usable branch.
- Sellers assigned to the deactivated branch can no longer log in (`BRANCH_UNAVAILABLE`). Their accounts and history are untouched.
- An administrator currently in that branch falls to `unselected` and sees no operational rows until they choose another branch.
- Cross-branch reporting still includes the inactive branch’s history.

Reactivate with:

```http
PUT /api/branches/{id}
{ "status": "active" }
```

This is recorded as a `BRANCH_UPDATED` audit row containing the status change. The same branch UUID becomes selectable again, and assigned sellers can log in again.

There is no hard-delete endpoint in the application. Deleting a branch directly in SQL is discouraged: `ON DELETE SET NULL` would strip the branch from every operational row, turning branch history into ambiguous `NULL` rows.

### 6.4 Assigning a seller to a branch

`PUT /api/admin/users/{id}/branch`

```json
{ "branch_id": "br-uuid" }
```

or, to unassign:

```json
{ "branch_id": null }
```

Rules:

1. Caller must be an administrator.
2. Target user must belong to the caller’s business.
3. Target user must have role `seller`. Administrators cannot be pinned to one branch.
4. The branch must belong to the same business and be **active**. Otherwise `404`.
5. Assigning writes `users.branch_id` and an audit row `SELLER_BRANCH_ASSIGNED`.
6. Unassigning clears `users.branch_id` and returns success. It does not currently write a separate audit row.

The same operation exists in both UIs:

- Web dashboard seller list: a branch `<select>` per seller plus `Badili Tawi`.
- Mobile dashboard seller list: a branch picker per seller.

The assignment takes effect for the seller on their next authenticated request, because the middleware resolves the seller’s branch from the database rather than from the token claim. This is also why a reassignment cannot be escaped with an old token.

On a **branchful** business, a seller with no assignment cannot log in. An administrator must assign them first. On a **non-branch** business, `branch_id` stays `NULL` and is irrelevant.

### 6.5 Choosing the branch for a dashboard session

The branch selector (`/select-branch` on web, `select-branch.tsx` on mobile) lists active branches plus:

**DASHBOARD YA MATAWI YOTE** — sends `branch_id: null`, producing `cross_branch`.

Web flow:

1. Read `userToken`.
2. `GET /api/branches`.
3. If the business is not branchful or `can_select_branch` is false, redirect to the dashboard.
4. On selection, `POST /api/branch-context`.
5. Replace the stored token, `branchId`, `branchContext`, `branchData`.
6. Navigate to `/msimamizi/index`.

Mobile flow is equivalent using `AsyncStorage`, with the same server verification.

The dashboard’s branch card uses the same `GET /api/branches` response to show either:

- the current branch and a “view all branches” action, or
- the cross-branch list and a “switch branch” action.

Sellers see their current branch but no switch action; the server would reject the attempt anyway.

### 6.6 Branch structure conversion

Branchless ↔ branchful changes are managed in the same Settings module but are a separate lifecycle operation:

- `POST /api/settings/business-structure/branchful` creates the first branch, assigns sellers and moves existing records.
- `POST /api/settings/business-structure/branchless` unifies the business and deactivates all active branches while preserving their history.

Conversion is atomic inside PostgreSQL functions. See `BUSINESS_STRUCTURE_CONVERSION_IMPLEMENTATION_AND_MIGRATION.md` for the full rules, preservation matrix and refusal conditions.

---

## 7. UI and API surface by platform

### 7.1 Web (Blade)

| File | Responsibility |
|---|---|
| `resources/views/admin-signup.blade.php` | Structure choice and branch rows during signup, including GPS |
| `resources/views/cashier-signup.blade.php` | Seller joins an eligible business and selects one active branch when required |
| `resources/views/login.blade.php` | Saves branch context; redirects branch admins needing selection to `/select-branch` |
| `resources/views/select-branch.blade.php` | Active branch list, cross-branch option, token replacement |
| `resources/views/msimamizi/index.blade.php` | Branch card, seller branch assignment, branch labels |
| `resources/views/msimamizi/mipangilio.blade.php` | Branch list (including inactive), add/edit/deactivate/reactivate, structure conversion |
| `public/js/dm-features.js` | Feature gating only; unrelated to branch scoping |

### 7.2 Mobile (Expo)

| File | Responsibility |
|---|---|
| `Duka_mkononi/app/admin_signup.tsx` | Structure choice and branch rows |
| `Duka_mkononi/app/cashier_signup.tsx` | Business/branch selection for seller registration |
| `Duka_mkononi/app/login.tsx` | Stores branch context; no client-side authorization |
| `Duka_mkononi/app/select-branch.tsx` | Branch/cross-branch selection and token replacement |
| `Duka_mkononi/app/msimamizi/index.tsx` | Redirects unselected admins, branch card, seller assignment |
| `Duka_mkononi/app/msimamizi/mipangilio.tsx` | Branch management and structure conversion |
| `Duka_mkononi/constants/session.ts` | Session/storage helpers |

### 7.3 Laravel

| File | Responsibility |
|---|---|
| `app/Http/Controllers/Api/BranchController.php` | List, create, edit, deactivate, context selection, seller assignment |
| `app/Http/Controllers/Api/AuthController.php` | Signup branch validation, atomic business+branch creation, seller assignment resolution, login context |
| `app/Http/Controllers/Api/SellerRegistrationController.php` | Public, narrow business/branch lookup for seller signup |
| `app/Services/BranchContext.php` | Structure resolution, context resolution, active branch ownership |
| `app/Http/Middleware/AuthenticateJwt.php` | Re-resolves context on every request |
| `app/Http/Controllers/Api/BaseController.php` | `scopeBranch`, `requireWriteBranch`, context helpers |
| `app/Http/Controllers/Api/SettingsController.php` | Full settings snapshot and structure conversion |
| `app/Models/Branch.php` | Supabase model for `branches` |

---

## 8. API reference

All endpoints below except the seller-registration lookups are inside the `auth.jwt` middleware group.

| Method | Path | Who | Purpose |
|---|---|---|---|
| `GET` | `/api/branches` | Any authenticated member | Active branches + current context |
| `POST` | `/api/branches` | Admin, branch business | Create a branch |
| `PUT` | `/api/branches/{id}` | Admin, own business | Edit fields or status |
| `DELETE` | `/api/branches/{id}` | Admin, own business | Deactivate (never delete) |
| `POST` | `/api/branch-context` | Admin | Select a branch or cross-branch; returns new token |
| `PUT` | `/api/admin/users/{id}/branch` | Admin | Assign/unassign a seller |
| `GET` | `/api/seller-registration/businesses` | Public | Eligible businesses for seller signup |
| `GET` | `/api/seller-registration/businesses/{businessId}/branches` | Public | Active branches of an eligible business |
| `GET` | `/api/settings` | Admin | Full settings snapshot including inactive branches |
| `POST` | `/api/settings/business-structure/branchful` | Admin | Convert branchless → branchful |
| `POST` | `/api/settings/business-structure/branchless` | Admin | Convert branchful → branchless |

### 8.1 Create branch — response

```json
{
  "success": true,
  "message": "Tawi limeongezwa",
  "branch": {
    "id": "br-uuid",
    "business_id": "b-uuid",
    "branch_name": "Tawi la Mlimani",
    "location": "Mlimani City",
    "latitude": -6.7712,
    "longitude": 39.2211,
    "status": "active"
  }
}
```

`201 Created`. Errors: `403`, `422 STRUCTURE_NOT_BRANCH`, `422` validation, `409 BRANCH_NAME_TAKEN`, `500` database failure.

### 8.2 Select context — response

Individual branch:

```json
{
  "success": true,
  "context": "individual_branch",
  "branch": {
    "id": "br-uuid",
    "branch_name": "Kinondoni",
    "location": "Kinondoni",
    "latitude": -6.79,
    "longitude": 39.2
  },
  "token": "<new JWT>"
}
```

Cross-branch:

```json
{
  "success": true,
  "context": "cross_branch",
  "branch": null,
  "token": "<new JWT>"
}
```

Non-branch:

```json
{
  "success": true,
  "context": "non_branch",
  "branch": null,
  "token": "<new JWT>"
}
```

### 8.3 Seller registration lookup

Only businesses with at least one approved administrator are eligible. The branch endpoint returns only active branches and only the fields a seller needs (`id`, `branch_name`, `location`). It deliberately does not expose owner emails, products, sales or other private business data. An ineligible business returns `404` without revealing whether it exists.

---

## 9. Error codes

| Code | Status | Where | Meaning |
|---|---|---|---|
| `STRUCTURE_NOT_BRANCH` | 422 | Create branch | Business is not registered as branchful |
| `BRANCH_NAME_TAKEN` | 409 | Create/edit/convert | Duplicate branch name in the business |
| `BRANCH_NOT_FOUND` | 404 | Edit, deactivate, assign, context | Branch missing, inactive, or belongs to another business |
| `LAST_ACTIVE_BRANCH` | 409 | Edit/deactivate | Refuses to leave the business with zero active branches |
| `BRANCH_CONTEXT_FORBIDDEN` | 403 | Context selection | Seller tried to choose a branch |
| `BRANCH_REQUIRED` | 422 | Operational write | Cross-branch context cannot write; choose a branch |
| `BRANCH_SELECTION_REQUIRED` | 409 | Operational write | Administrator has not selected a branch yet |
| `BRANCH_UNAVAILABLE` | 403 | Login | Seller’s assigned branch is missing/inactive/foreign |
| `BRANCH_REQUIRED` | 400 | Seller signup | Branch business requires the seller to choose a branch |
| `BUSINESS_NOT_FOUND` | 404 | Seller signup / conversion | Business does not exist or is not eligible |
| `BUSINESS_NO_ADMIN` | 400 | Seller signup | Business has no approved administrator yet |
| `ALREADY_BRANCHFUL` / `ALREADY_BRANCHLESS` | 409 | Conversion | Requested conversion already happened |
| `UNSAFE_DUPLICATE_PRODUCTS` | 409 | Branchful → branchless | Same product name/SKU/barcode appears in multiple branches |
| `CONVERSION_NOT_INSTALLED` | 503 | Conversion | SQL conversion function missing on the deployment |

---

## 10. Security invariants

These are the rules the implementation is designed to preserve. Any future change should keep them true.

1. **The tenant is `business_id`, not `branch_id`.** Every branch lookup is also constrained by the authenticated business. A valid branch id from another business is treated as nonexistent.
2. **The client cannot choose the branch for normal operations.** Only `POST /api/branch-context` accepts a proposed branch, and it verifies ownership and active status before signing a new token.
3. **The stored structure is authoritative.** Tokens are a transport mechanism, not the source of truth. A stale token cannot keep a converted business in its old mode.
4. **Sellers are database-bound.** `BranchContext::resolve()` loads the seller’s current assignment before considering claims. A forged or stale `cross_branch` claim cannot widen a seller’s access.
5. **Unselected fails closed.** It produces an impossible query filter, never an unscoped read.
6. **Cross-branch is read-only.** Writes require a selected branch, so an ambiguous “which shop made this sale?” state cannot be persisted.
7. **Branch pointers are verified, not just stamped.** Updating or deleting a product/customer in a selected branch also checks that the existing row belongs to that branch.
8. **Branches are deactivated, not deleted.** This preserves seller assignments, stock, sales and audit history.
9. **A business keeps at least one active branch.** Deactivation and status edits enforce this at the API layer. (It is not a database constraint.)
10. **Branch names are unique per business.** Enforced in the controller for a friendly error and by a case-insensitive unique index for correctness under concurrency.
11. **Administrative endpoints are administrator-only.** Sellers can read their own branch list but cannot create, edit, deactivate, assign or convert.

### 10.1 Threat examples

| Attempt | Result |
|---|---|
| Seller edits `branch_id` in localStorage | The server ignores the stored value for scoping and re-resolves from the database. |
| Seller calls `POST /api/branch-context` with another branch | `403 BRANCH_CONTEXT_FORBIDDEN`. |
| Admin sends another business’s branch id to `POST /api/branch-context` | `404 BRANCH_NOT_FOUND`; no token is issued. |
| Admin creates a branch for another business by adding `business_id` to the body | The field is ignored; the JWT business is used. |
| User forges a `cross_branch` claim | JWT signature fails, or the seller’s database assignment overrides it. |
| User keeps an old token after their branch is deactivated | The claim is re-validated against `status = active`; access becomes `unselected`. |
| Admin in cross-branch mode posts a sale | `422 BRANCH_REQUIRED`; no branchless sale is created. |
| Caller tries to deactivate the last branch | `409 LAST_ACTIVE_BRANCH`. |

---

## 11. Audit trail

Branch-related actions write to `user_logs` through `AuditLogger`:

| Action | Trigger |
|---|---|
| `BRANCH_CREATED` | Successful `POST /api/branches` |
| `BRANCH_UPDATED` | Successful `PUT /api/branches/{id}`, including reactivation and changed-field details |
| `BRANCH_DEACTIVATED` | Successful `DELETE /api/branches/{id}` |
| `BRANCH_CONTEXT_SELECTED` | Administrator selects an individual branch |
| `BRANCH_CONTEXT_DENIED` | Requested branch is missing/inactive/foreign |
| `SELLER_BRANCH_ASSIGNED` | Seller assigned to a branch |
| `BUSINESS_STRUCTURE_CONVERTED` | Either conversion RPC, written inside the transaction |
| `REGISTER_FAILED` / `LOGIN_FAILED` | Includes branch-related failure reasons such as invalid seller branch or unavailable branch |

The audit payload contains identifiers and names, not credentials or tokens.

---

## 12. Deployment and migrations

Apply in this order on the target Supabase/PostgreSQL database:

1. `supabase/migrations/20261110000001_create_branches_and_business_structure.sql`
   - Adds `businesses.business_structure`, `branches`, `users.branch_id`, branch columns and indexes.
   - Adds `register_business_with_branches(...)`, required for branchful signup.
2. Any later feature migrations that add branch pointers (for example the fridge inventory migration).
3. `supabase/migrations/20261114000003_add_business_structure_conversion.sql` **only if** branchless ↔ branchful conversion is enabled. Without it, the conversion endpoints return `503 CONVERSION_NOT_INSTALLED` instead of pretending to succeed.

The local Laravel mirror (`2026_11_10_000001_create_branches_and_business_structure.php`) should run in local/CI environments for tests and local development. It is not a substitute for the PostgreSQL migration.

### 12.1 Deployment checks

After applying the SQL, verify:

```sql
-- Structure column and branches table
select business_structure, count(*) from public.businesses group by 1;
select id, business_id, branch_name, status from public.branches order by created_at;

-- No cross-business branch assignments
select u.id, u.business_id, u.branch_id, b.business_id as branch_business
  from public.users u
  join public.branches b on b.id = u.branch_id
 where u.business_id is distinct from b.business_id;

-- Branchful businesses should have at least one active branch
select b.id, b.business_name
  from public.businesses b
 where b.business_structure = 'branch'
   and not exists (
     select 1 from public.branches br
      where br.business_id = b.id and br.status = 'active'
   );

-- Sellers in branch businesses should have an active assignment
select u.id, u.email, u.branch_id
  from public.users u
  join public.businesses b on b.id = u.business_id
 where b.business_structure = 'branch'
   and u.role = 'seller'
   and not exists (
     select 1 from public.branches br
      where br.id = u.branch_id
        and br.business_id = u.business_id
        and br.status = 'active'
   );
```

Run these as read-only checks first. Do not “fix” orphaned sellers or empty branch businesses with bulk updates without reviewing the affected accounts: a seller with no active branch is intentionally blocked from login until an administrator assigns one.

### 12.2 Rollback notes

- Dropping `branches` would remove the branch model and break all branch-aware code. Do not do it as a rollback.
- Setting a business back to `non_branch` without using the conversion function leaves branch rows and branch pointers in place; the API would treat the business as business-wide, and the data would no longer be visible in branch dashboards. Use the official conversion path if a business must become branchless.
- `DELETE FROM branches` is not a supported operation. Prefer `status = 'inactive'`.

---

## 13. Test coverage

### 13.1 `tests/Feature/SellerBranchAccessTest.php` — 6 tests

1. A new seller registers against an existing business and an active branch; the business id and branch id persist; after approval, login lands in that branch.
2. A seller whose assigned branch is inactive is refused at login with `BRANCH_UNAVAILABLE`.
3. A seller cannot read another branch’s products.
4. A business-wide (`branch_id NULL`) product does not bypass branch scoping for a seller.
5. Even a signed `cross_branch` token is reduced to the seller’s database assignment.
6. An existing non-branch seller is unaffected by branch rules.

### 13.2 `tests/Feature/AdminSettingsTest.php` — branch-related tests

- A branch can be created and edited only inside its own business.
- The last active branch cannot be deactivated.
- A non-branch business cannot create branches.
- Branchless → branchful conversion returns a branchful token.
- Branchful → branchless conversion returns a non-branch token.
- Sellers cannot convert the business structure.
- Conversion rejects the wrong current structure.
- `UNSAFE_DUPLICATE_PRODUCTS` is surfaced as a conflict.
- A stale token cannot keep the old business structure.

These tests use a fake PostgREST layer. They verify controller wiring, authorization, request validation, payloads and token claims. They do **not** execute the PostgreSQL functions or the case-insensitive unique index; those must be verified on a real PostgreSQL/Supabase environment.

### 13.3 Full suite

At the time this guide was written, the full Laravel suite passed:

```text
783 passed (12,075 assertions)
```

Mobile type-checking (`npx tsc --noEmit --skipLibCheck`) was clean after the branch UI changes.

---

## 14. Operational recipes

### 14.1 Add a branch to an existing branch business

1. Log in as the business administrator.
2. Open `Mipangilio → Biashara na Matawi → Matawi`.
3. Click `+ Ongeza Tawi`.
4. Enter a name; location and GPS are optional here.
5. Save. The branch is immediately active and appears in `/select-branch`.
6. Assign sellers to it from the dashboard seller list, or during seller signup.

If the branch card is not visible, the business is `non_branch`; use the structure conversion control instead of trying to force a branch insert.

### 14.2 Move a seller to another branch

1. Open the administrator dashboard seller list.
2. Pick the new branch from the seller’s branch selector.
3. Save.
4. The seller’s next request resolves the new assignment from the database. If they are currently logged in, their existing token is re-scoped by that same resolution.

### 14.3 Close a location without losing its history

1. `Mipangilio → Matawi → Zima` on the branch (or `DELETE /api/branches/{id}`).
2. Move its sellers to another active branch if they should keep working.
3. The branch disappears from the selector, but its products, sales, stock and reports remain available in cross-branch views.
4. Reactivate the same row with `Rudisha` / `PUT { "status": "active" }` to restore access with the same UUID and assignments.

### 14.4 Diagnose “seller cannot log in”

Check, in order:

1. Is the business `branch`?
2. Does `users.branch_id` point at a branch of the same business?
3. Is that branch `active`?
4. Is the seller `approved`?
5. Was the seller maybe assigned before the branch was deactivated?

`LOGIN_FAILED` with reason `Seller branch unavailable` and the `BRANCH_UNAVAILABLE` code is the expected outcome for any of the branch failures. Fix by assigning an active branch, not by disabling branch checks.

### 14.5 Diagnose “admin sees no products/sales”

The likely context is `unselected`, not an empty business:

1. Call `GET /api/branches` and inspect `context.branch_context`.
2. If it is `unselected`, choose a branch or the cross-branch dashboard.
3. If it is `individual_branch`, confirm the data has that branch id. Legacy `NULL` rows are not visible in individual context by design.
4. If the branch was deactivated, select another active branch.

### 14.6 Diagnose “cannot create a branch”

Expected causes:

- Caller is not an administrator → 403.
- Business is `non_branch` → 422 `STRUCTURE_NOT_BRANCH`. Convert the structure first.
- Duplicate name, including a deactivated branch name → 409 `BRANCH_NAME_TAKEN`. Choose another name or reactivate the existing branch.
- Name too short/long or invalid coordinates → 422 validation.

---

## 15. Known limitations and deliberate choices

- **One branch per seller.** The model supports exactly one `users.branch_id` per seller. A person who works at two locations needs two accounts or an administrator account; there is no many-to-many assignment.
- **Administrators are not branch-bound.** They always retain business-wide authority and choose a context. There is no per-admin branch restriction.
- **Cross-branch writes are refused.** This avoids recording inventory or sales without a location. It is not a bug; it is the reason `BRANCH_REQUIRED` exists.
- **Branch deletion is soft.** There is no hard-delete API, by design, because history and seller assignments depend on the UUID.
- **Branch names cannot be reused after deactivation.** The unique index covers inactive rows.
- **At least one active branch is an API invariant, not a DB constraint.** A direct SQL update can violate it. The application prevents it.
- **Services can be business-wide.** A service with `branch_id NULL` is intentionally visible in every branch. This is the only scoped read that includes null rows, and it is explicit.
- **Not every endpoint is branch-scoped.** Public discovery, orders, inquiries and the legacy payments listing are business/platform-level. Check `scopeBranch()` usage before assuming otherwise.
- **Old branch tokens remain valid for their old branch until replaced or expired.** Branch switching does not revoke JWTs. Seller reassignment, by contrast, is resolved from the database on every request.
- **Selecting a branch does not move data.** It only changes the signed request context. Branch data moves only during structure conversion.
- **No automatic branch provisioning from a business name or address.** Branches exist only through signup, explicit creation, or conversion.
- **PostgreSQL functions are not exercised by PHPUnit.** The tests verify the API contract around them, not transactional SQL behavior.

---

## 16. Quick reference

### 16.1 Environment variables / configuration

No branch-specific environment variables. Branch data lives entirely in Supabase and the signed JWT. The JWT secret is shared with the rest of authentication (`config('jwt.secret')`).

### 16.2 Important constants

```php
BranchContext::NON_BRANCH  = 'non_branch';
BranchContext::INDIVIDUAL  = 'individual_branch';
BranchContext::CROSS       = 'cross_branch';
BranchContext::UNSELECTED  = 'unselected';
BranchContext::STRUCTURES  = ['non_branch', 'branch'];
```

### 16.3 The one-sentence summary

A branch is an operational location inside one business; the business owns the tenant boundary, the branch narrows reads and stamps writes, and the server — never the client — decides which branch a request is in.
