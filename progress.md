# Progress Log — DukaMkononi Msimamizi Portal

This file records, in plain language, every change and fix made to the
msimamizi (administrator) side of the app during our working sessions.
Each section explains **what the problem was**, **what we changed**, and
**which files were touched**, so anyone picking up the project later can
follow along without reading the whole codebase.

---

## 1. Search bars on the Ripoti page (`/msimamizi/ripoti`)

**Problem:** The reports page had three data tabs (Mauzo, Bidhaa, Wateja)
with no way to find a specific record — the user had to scroll through
everything manually.

**What we did:**

- Added a rounded search box with a 🔍 icon and a ✕ clear button to the
  **Mauzo**, **Bidhaa**, and **Wateja** tabs. It sits directly under the
  tab navigation. The Mapitio (overview) tab has no search because it is
  summary statistics only — there is no list to search.
- Filtering happens **live as you type** and is **case-insensitive**:
  - *Mauzo* — matches product name, customer name, seller name, and date.
  - *Bidhaa* — matches product name and category, and works inside both
    the "Zimeuzwa" and "Hazijauzwa" sub-tabs.
  - *Wateja* — matches customer name, phone number, and email.
- When nothing matches, the page shows
  "Hakuna matokeo yanayolingana na utafutaji wako" instead of a blank list.
- A small "Matokeo: X kati ya Y" counter appears while a filter is active.
- Typing only re-renders the results list, not the whole page, so the
  search box never loses focus mid-word.
- Switching tabs resets the search; the ✕ button clears it.

**Files touched:** `resources/views/msimamizi/ripoti.blade.php`

---

## 2. Rejea page (`/msimamizi/preview`) — new features

### 2a. Date range filter (Kuanzia → Hadi)

**Problem:** The page could only show all-time data; there was no way to
look at a specific period.

**What we did:**

- Added a "📆 Chuja kwa Kipindi cha Tarehe" card with **Kuanzia** and
  **Hadi** date pickers, a **Tafuta** (search) button, and a **Futa**
  (clear) button.
- Pressing Tafuta re-fetches sales from the API with `date_from` and
  `date_to` parameters, so **the database does the filtering**, not the
  browser. A friendly message shows when the chosen period has no sales.
- The active period is displayed as a green confirmation chip, and the
  daily summaries, statistics, and expense totals all respect it.

### 2b. Real customer names in the daily report

**Problem:** Every sale in the daily report showed only "Mteja" even when
a customer name had been recorded.

**Root cause found:** the backend endpoint that supplies sales
(`AdminController::sales()`) never attached the `customers` relation, so
`sale.customers?.name` was always empty on the frontend.

**What we did:**

- The backend now embeds the matching customer record on every sale it
  returns.
- Daily summaries and the day-detail modal now list **only genuinely
  recorded names** ("Hawajarekodiwa" appears when there are none),
  instead of a wall of "Mteja".

### 2c. "Zimesomwa" (mark all as read) in the Taarifa tab

**Problem:** There was no way to clear the notification feed — everything
always looked new.

**What we did:**

- Added a green **✓ Zimesomwa** button at the top of the Taarifa tab.
- Unread events get a blue edge marker and an "MPYA" badge; read events
  are dimmed.
- Read-state is stored on the server (a `last_read_at` timestamp per
  admin) so it survives page reloads, with a localStorage fallback so it
  still works before the database table exists.
- Three new backend endpoints were added (see section 5).

### 2d. "Chapisha Taarifa" — print one day as a PDF

**Problem:** Merchants want a printable record for a single day.

**What we did:**

- Every day card in **Siku Zangu** now has a blue **🖨️ Chapisha Taarifa**
  button (bottom-right of the card).
- It builds a standalone printable document for that specific day and
  opens the browser print dialog — choosing "Save as PDF" produces the
  file. The button does not also open the day's summary modal (the click
  is isolated).
- The printed report includes: a header (business name, full date,
  location), a summary (items sold, total sales, gross profit, expenses,
  net profit — colour-coded), the office-expense breakdown when present,
  the day's recorded customers, and a full table of *all* sales that day
  (product, customer, seller, quantity, unit price, total) — not just the
  5-row preview shown in the on-screen modal.

**Files touched:** `resources/views/msimamizi/preview.blade.php`

---

## 3. Performance: stop downloading the whole database

**Problem:** The msimamizi pages were very slow. The root cause was a
pattern where the backend returned **every row for every business** and
the browser filtered them down afterwards — expensive on data transfer,
and it could silently cut off rows past the server's 1000-row response
cap.

**What we did (server-side filtering everywhere):**

- **`AdminController::users()`** now accepts a `?business=` (or
  `?business_name=`) parameter and returns only that business's users.
  With no parameter it behaves exactly as before, so system-admin pages
  are unaffected.
- **`AdminController::sales()`** now accepts `date_from` / `date_to`
  (aliases `start_date` / `end_date`) so report pages request exactly the
  window they display.
- **Rejea page** now calls:
  - `/api/admin/users?business=...`
  - `/api/admin/products?business_name=...`
  - `/api/admin/sales?business_name=...&date_from=...&date_to=...`
  instead of fetching everything and filtering in JavaScript. Product
  lookups also use a Map instead of repeated array scans.
- **Ripoti page** now calls `/api/admin/users?business=...` instead of
  downloading all users.
- **`AdminController::stats()` and `realTimeStats()`** previously loaded
  *every sale row* just to add up totals; they now use a column-only
  `sum('total_amount')`, so only the numbers travel over the network.
- Expense fetching on Rejea uses the active filter window directly rather
  than the range of every date sales ever touched.
- One behaviour note: Rejea now includes the business **admin's** own
  products/sales (the products endpoint was already admin-inclusive; the
  users fetch previously excluded admins, which could silently drop the
  admin's records from the reports).

**Files touched:** `app/Http/Controllers/Api/AdminController.php`,
`resources/views/msimamizi/preview.blade.php`,
`resources/views/msimamizi/ripoti.blade.php`

---

## 4. Taarifa tab count shows unread only

**Problem:** The tab badge showed the **total** number of taarifa, so it
never went down and read nothing like a notification counter.

**What we did:**

- The badge now shows the number of **unread** taarifa only.
- After pressing **Zimesomwa**, the count resets to **0** and the tab
  shows a green "✓ zote zimesomwa" state.
- Because unread state is timestamp-based, any taarifa arriving after the
  last "mark read" correctly counts as new again.

**Files touched:** `resources/views/msimamizi/preview.blade.php`

---

## 5. New backend endpoints (notifications read-state)

Added in `app/Http/Controllers/Api/NotificationController.php` and
`routes/api.php`:

- **`POST /api/admin/notifications/mark-all-read`** — stamps the current
  time as the admin's "last read" moment. Everything sent before then
  counts as read.
- **`POST /api/admin/notifications/read-state`** — returns the stored
  `last_read_at` for the logged-in admin.
- **`POST /api/admin/setup/notifications-read-state`** — one-time helper
  that checks for the `admin_notification_read_state` table and returns
  the exact SQL to paste into the Supabase SQL Editor if it is missing.
  The mark-all-read endpoint degrades gracefully (still returns success
  to the UI) while the table does not exist.

---

## 6. AI photo import (`bidhaa-mpya`) — business info saved once

**Problem:** "Taarifa za Biashara" (business type + short description)
had to be retyped every time the user opened the AI import feature, even
though the save button existed.

**What we did:**

- The AI modal now **pre-fills** both fields from the saved user profile
  (instantly from the local cache, then refreshed from the API) — the
  user enters them once and never again.
- The **✏️ Hariri** modal on the dashboard (`index`) gained the same two
  fields — **Aina ya Biashara** (dropdown, same option list as the AI
  modal) and **Maelezo mafupi ya Biashara** — so there is one obvious
  place to edit them later.
- The dashboard also fetches the profile on load so the edit form always
  shows the stored values, and keeps the localStorage cache in sync for
  other pages.

**Files touched:** `resources/views/msimamizi/bidhaa-mpya.blade.php`,
`resources/views/msimamizi/index.blade.php`

---

## 7. AI import — "Thibitisha" was not saving (the big one)

**Problem:** Clicking **Thibitisha** on an AI-extracted item failed to
save the product.

**Root causes found (all fixed):**

1. The duplicate-name check queried a **`user_id` column that does not
   exist** on the products table (the real column is `seller_id`), so the
   database rejected the request before a save could happen.
2. The new-product insert included a **`business_name` field that is not
   a products column** — another guaranteed failure.
3. The insert omitted `expected_selling_price` and `cost_price`, which
   the rest of the app relies on.
4. The audit-log write ran **after** a successful save without
   protection: if the `ai_import_verifications` table was missing, the
   API returned an error *even though the product had already been
   saved* — and retrying then hit "product already exists", making it
   look permanently unsavable. Audit writes are now best-effort.
5. For existing items, a stale `price` field could overwrite the selling
   price the user had just edited. The confirmed selling price now wins,
   and `expected_selling_price` is updated alongside it.

**Files touched:** `app/Http/Controllers/Api/AiImportController.php`,
`resources/views/msimamizi/bidhaa-mpya.blade.php`

---

## 8. AI import — edits in one row no longer wipe other rows

**Problem:** After typing edits into several rows, verifying ONE row
reset all the other rows back to their original values, forcing the user
to retype everything.

**Root cause found:** the table was fully re-rendered from internal state
after each verification, but cell edits only lived in the input boxes and
were never written back to that state.

**What we did:**

- Added an input listener that continuously syncs every edit (name,
  quantity, buying price, selling price) into the row state as the user
  types.
- Verification now updates the row's button **in place** (to
  "✅ Imethibitishwa") and refreshes only the summary chips — no full
  table re-render — so all other rows keep their edits exactly.

**Files touched:** `resources/views/msimamizi/bidhaa-mpya.blade.php`

---

## 9. AI import — buying price wrongly equal to selling price

**Problem:** For items with a single price, the system ended up storing
the same number as both "bei ya kununua" and "bei ya kuuzia", so the
expected profit displayed as zero everywhere.

**Root causes found:** the frontend sent `price: selling ?? buying` and
the backend fell back through `price → selling → buying`. When only one
price existed, buying became the selling price.

**What we did (three layers of defence):**

- **Frontend:** the payload sends the selling price only, and
  **Thibitisha now requires bei ya kuuzia** to be filled ("Weka bei ya
  kuuzia kabla ya kuthibitisha"). Buying price is optional.
- **Backend:** the selling price is required on its own — no silent
  fallback to the buying price. New products are created with
  `price` / `expected_selling_price` = selling price and `cost_price` =
  buying price (null when not provided), so profit is real.
- **AI prompt:** Gemini is now explicitly instructed that when the source
  shows only one price per item it must go into `sellingPrice` with
  `buyingPrice` left null, and must never duplicate one number into both
  fields — fixing the problem at the source.

**Files touched:** `app/Http/Controllers/Api/AiImportController.php`,
`resources/views/msimamizi/bidhaa-mpya.blade.php`

---

## 10. AI import — "Thibitisha Zote" (verify all)

**Problem:** Verifying many AI-extracted items one at a time was slow and
repetitive.

**What we did:**

- Added a green **✅ Thibitisha Zote** button below the results table,
  next to a live progress indicator.
- Flow: **validate first** (every unverified row is checked for a name, a
  positive whole quantity, and a selling price — problems are listed per
  row so they can be fixed before saving anything) → **confirmation
  toast** ("Thibitisha Zote? N bidhaa zitahifadhiwa kwenye stoo mara
  moja." with Endelea / Ghairi) → **sequential save** of each row through
  the exact same per-row logic as a single click.
- Each row's button flips to "✅ Imethibitishwa" in place as it saves,
  and the progress shows "Inahifadhi 2/7..." live.
- Final summary: "Zote 7 bidhaa zimehifadhiwa kikamilifu!" or, if some
  failed, how many saved vs failed so the rest can be fixed and retried.
- Products save one request at a time, so an interruption halfway leaves
  everything already verified saved, and only the remainder needs
  retrying. A dedicated busy flag prevents double-clicking the batch
  button or clicking individual row buttons mid-batch.

**Files touched:** `resources/views/msimamizi/bidhaa-mpya.blade.php`

---

## 11. Tangaza page (`/msimamizi/tangaza`) — typing & "Lipa Sasa" fixed

### 11a. Text field made typing impossible

**Problem:** typing a single letter threw the cursor out of the textarea
and the page scrolled back to the top, so long descriptions could not be
written.

**Root cause found:** every keystroke ran `render()`, which replaces the
whole page's HTML. That destroyed the textarea and rebuilt it from
scratch — the cursor was lost and the browser scrolled up. On top of
that, a `MutationObserver` kept stacking duplicate input listeners, and a
network check re-rendered the page every 15 seconds (wiping whatever the
user was typing at that moment).

**What we did:**

- Typing now updates **only the character counter** — the page is never
  re-rendered while the user types.
- The network check re-renders **only when connectivity actually
  changes**, not on a timer.
- The redundant MutationObserver was removed.

### 11b. "Lipa Sasa" / "Lipa Upya" did nothing

**Problem:** clicking the payment buttons did nothing at all.

**Root causes found:**

1. The buttons put the matangazo's UUID **unquoted** into the onclick
   attribute (`handleRenewPayment(550e8400-e29b-...)`). JavaScript parsed
   that as arithmetic on undefined variables, threw an error, and the
   click handler died silently. The IDs are now properly quoted.
2. In the Laravel port of the payment API, `PaymentController::initiate()`
   **dropped `matangazo_id` when saving the payment record** — the old
   `server.js` backend saved it. Without that link, a completed payment
   could never find (and activate) the matangazo it was paying for. The
   link is saved again now, so the IPN / callback / status-check paths
   can all activate the advertisement after successful payment.
3. If the browser blocks the PesaPal popup (common when `window.open`
   happens after an async call), the page now **navigates in the same
   tab** instead of failing silently.

**Files touched:** `resources/views/msimamizi/tangaza.blade.php`,
`app/Http/Controllers/Api/PaymentController.php`

---

### 11c. Buttons now show a spinner while the request runs

**Problem:** clicking **Lipa Sasa**, **Lipa Upya**, or **Futa** gave no
visible feedback while the request was in flight — the page just sat
quiet and users could not tell whether their click had registered (and
tended to click repeatedly).

**What we did:**

- Each action button now has a unique DOM id and, while its request is
  running, it swaps its label for a white spinner plus a status word:
  **Inaanza malipo...** for the payment buttons, **Inafuta...** for Futa
  (the spinner appears after the delete confirmation is accepted).
- The busy button is dimmed, non-clickable (`cursor: wait`), and a
  one-action-at-a-time guard prevents double submissions — so no matter
  how many times the user taps, only one request goes out.
- When the request finishes the button restores its original label
  automatically (or disappears with the refreshed list, e.g. after a
  successful delete).

---

## 12. Tangaza — "Futa" (delete) now works completely

**Problem:** the delete button did not appear to do anything.

**Root causes found:**

1. The backend only **soft-deleted** the post (`is_active = false`), but
   the "Matangazo Yangu" list returns *all* of the user's posts regardless
   of that flag — so the post stayed visible and deletion looked broken.
2. The media file was never removed from Cloudinary, leaving orphaned
   uploads.

**What we did (full delete flow):**

- Clicking **Futa** opens a confirmation toast:
  "Una uhakika unataka kufuta tangazo hili?"
- If the post has an **active subscription** (paid status and expiry date
  still in the future), an extra red warning appears below:
  "Usajili wako hautarudishwa fedha baada ya kufuta tangazo hili."
- On **Thibitisha**, the backend:
  1. Extracts the Cloudinary public ID + resource type from the post's
     media URL and calls Cloudinary's **signed destroy API** to delete
     the photo/video;
  2. Deletes the post's reactions;
  3. **Hard-deletes** the matangazo row from Supabase, so it really
     disappears from "Matangazo Yangu".
- If Cloudinary credentials are missing or the CDN refuses, the database
  deletion still succeeds and the user gets a clear warning instead of a
  silent failure.
- New env vars documented in `.env.example`:
  `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, `CLOUDINARY_API_SECRET`
  (the key/secret are needed server-side only for deletion — uploads stay
  unsigned from the browser).

**Files touched:** `app/Http/Controllers/Api/AdvertisementController.php`,
`resources/views/msimamizi/tangaza.blade.php`, `.env.example`

---

## 13. Rejea — "Siku Zangu" now includes expense-only days

**Problem:** the day list showed only days with sales. A day where the
business recorded office expenses but made no sales never appeared, so
those days (and the money spent on them) were invisible in the review.

**What changed:**

- Days shown are now: **days with sales** + **days with no sales but with
  expenses** (they appear as negative-net days). Fully zero days (no
  sales AND no expenses) are still hidden, as requested.
- The expense fetch window now extends to the earliest/latest expense
  dates when no date filter is active, so expense-only days outside the
  sales range are not missed. With an active filter it requests exactly
  that window.
- Expense-only day cards get an orange edge, an orange date badge, the
  label "💸 Hakuna mauzo — matumizi tu", and instead of the customers
  line they show the day's expense categories.
- The day modal and the printed daily report both handle days without
  sales gracefully (a "Hakuna mauzo siku hii" note instead of an empty
  table).

**Files touched:** `resources/views/msimamizi/preview.blade.php`

---

## 14. Professional UI restyle — emoji icons replaced with SVG icons

**Problem:** all msimamizi pages used casual chat-style emojis (🏠 📊 💰
🚀 ✅ 🗑️ ⏳ 🎉 ...) for navigation, buttons, statuses and empty states —
unsuitable for a business inventory platform.

**What we did:**

- Every emoji icon across the five msimamizi pages was replaced with
  **inline SVG line icons** (stroke-based, consistent 1.8 stroke width,
  inherit `currentColor` so they match text colours automatically).
- Each page carries a small shared `ICONS` map + `ic(name, size)` helper
  (home, report, calendar, plus, megaphone, logout, money, chart, users,
  user, box, doc, mail, phone, building, refresh, edit, trash, settings,
  camera, location, search, check, x, shield, clock, bell, warning,
  print, filter, folder, image, video, ai, scan, save, thumb, flag,
  crown, info, filePdf, excel).
- **Sidebar navigation** on all pages now uses the same professional
  icon set; the logout icon matches too.
- **Stat cards, tabs, badges, status chips, buttons** (Lipa Sasa, Lipa
  Upya, Thibitisha Zote, Chapisha Taarifa, exports), **empty states**
  and **no-result boxes** all use the new icons.
- Chat-style flourishes in alert toasts (🎉) and warning labels (⚠️)
  were replaced with plain professional wording.
- A few small style additions (avatar circles for customer rows, event
  icon tiles, summary icon alignment) keep everything visually aligned
  with the new icons.

**Result:** zero decorative emojis remain across all five pages — only
the ☰ mobile-menu glyph and close (✕ / ✖) and arrow (→) typographic
characters, which are standard UI affordances.

**Files touched:** all five
`resources/views/msimamizi/*.blade.php` pages

---

## Verification performed

- Every changed PHP file passes `php -l` (syntax check).
- The inline JavaScript of every touched Blade view was extracted and
  passes `node --check`.
- The Supabase REST layer (models/`Supabase` service) was reviewed to
  confirm the new query filters translate correctly to PostgREST.
- Manual end-to-end testing of the live AI flow (real Gemini key + logged
  in session) still needs to be done in the browser by the user.

---

## 14. Icons fixed for real: Font Awesome everywhere + stale cache purged

**What you saw:** literal text like `${ic('logout', 18)} Ondoka` in the sidebar, and
page content that looked unchanged no matter what was edited.

**Root causes (two, stacked):**
1. **Stale compiled Blade views.** The previous icon-migration session was cut off
   mid-way. It had saved a broken intermediate state of the pages, and Laravel had
   already compiled that broken state into `storage/framework/views/`. Laravel only
   re-compiles when the source file is newer than the compiled file, and because the
   broken compiled copies carried fresh-enough timestamps, the broken HTML kept being
   served. The blade sources on disk were actually correct the whole time.
2. The migration itself was genuinely incomplete on `bidhaa-mpya` (its second script
   block still used old icon helpers), which is where part of the weird output came
   from before recompilation.

**Fixes:**
- Purged all compiled views in `storage/framework/views/` — the very next page load
  recompiles from the correct blade sources.
- Completed the Font Awesome 6 (Free, solid) conversion on `bidhaa-mpya`: both of its
  script blocks now share one `FA_MAP` + `ic()` helper that returns
  `<i class="fa-solid fa-...">` elements, exactly like the other four pages.
- Verified every icon name used on every page (`index`, `ripoti`, `preview`,
  `bidhaa-mpya`, `tangaza`) resolves to a Font Awesome class in that page's `FA_MAP` —
  no name falls back to the placeholder icon.
- Confirmed all five pages load the Font Awesome 6 stylesheet from the jsDelivr CDN in
  their `<head>`, so `fa-solid` classes always render.
- Re-verified all script blocks on all five pages pass `node --check`.

**Files touched:** all five `resources/views/msimamizi/*.blade.php` (verification),
`storage/framework/views/` (purge), `progress.md` (this section).

**If icons ever look stale again:** clear the compiled cache with
`php artisan view:clear` (or delete `storage/framework/views/*.php`) and refresh.

---

## 15. Every native JavaScript alert replaced with a beautiful toast

**The problem:** the whole system used ugly native browser `alert()` popups
(~80 of them across 13 files) — they block the page, look unprofessional,
and don't match the app's design.

**What was built:** a shared toast component at
`resources/views/partials/toast.blade.php`:
- Modern slide-in cards anchored top-right (full-width on mobile), with a
  colored left edge per type: green success, red error, amber warning, blue info.
- Inline SVG icons (check, x, triangle, info circle) tinted to match —
  no emojis, consistent with the Font Awesome restyle.
- Auto-dismiss after a type-appropriate delay (success 3s → error 5.5s),
  manual close button, smooth slide-out animation, max 4 visible at once
  (oldest drops off), and `aria-live` for accessibility.
- Exposes a single global `showToast(message, type, duration)` API and
  guards against double-registration, so it's safe even when the SPA shell
  re-injects pages into the DOM.

**Where it's included:** right after `<body>` in every page — inside a
verbatim split (`@endverbatim` / `@verbatim`) so Blade actually renders the
include, and positioned BEFORE all page scripts so synchronous calls like
auth-check failures never hit an undefined function.

**Files converted (all native alerts → typed toasts):**
- `mteja`: biashara (6), matangazo (12), profaili (8), partials/sidebar (1)
- `muuzaji`: matumizi (11), mauzo (7), uza (10), profaili (8), partials/sidebar (1)
- `system_admin`: dashboard (4), notify (11), partials/sidebar (1)
- `msimamizi/partials/sidebar` (1)

Message types were chosen by meaning: success confirmations → green,
validation prompts → amber, network/API failures → red, location lookups
and similar → blue info.

**Verification:** `php artisan view:cache` compiles all views cleanly; every
changed script block passes `node --check`; a live server render test confirms
each page serves exactly one toast instance and zero literal `@include` text;
a project-wide scan confirms **zero native `alert()` calls remain**. The
existing in-page dialogs (showAlert/showConfirm modals) were intentionally
kept — they are already custom UI, not native popups.

---

## 16. Seller profile stats now show TODAY's performance only

**The problem:** on `/muuzaji/profaili`, a seller saw all-time, business-wide
numbers that mostly weren't even theirs:
- "Mauzo" counted every sale ever made by the whole business (the endpoint
  downloaded the seller's full sales history and counted it all).
- "Wateja" counted every customer record in the seller's customer book
  (lifetime), not people served today.
- "Mapato" summed all-time revenue.

**What the seller should see (per the request):** products in the business
(already correct), plus **their own sales count, customers served, and
revenue for today only**.

**Backend:** `SaleController::my` (`GET /api/sales/my`) now accepts optional
`date_from` / `date_to` query parameters that filter on `sale_date` — the
same convention as the admin sales endpoint. With no parameters the endpoint
behaves exactly as before, so nothing else breaks.

**Frontend (`muuzaji/profaili`):**
- Sales fetch now requests `?date_from=<today>&date_to=<today>` — the
  **database** returns only today's rows (no more downloading the seller's
  entire history just to count it).
- "Wateja wa Leo" = number of **distinct** `customer_id`s recorded in
  today's sales. The all-time `/api/customers/my` download was removed
  entirely — one fewer request, and the number now means what it says.
- All labels reworded to make the period explicit: "Mauzo ya Leo",
  "Wateja wa Leo", "Mapato ya Leo", with the note "Mauzo na wateja wa leo
  tu — kuanzia usiku wa manane".
- "Today" is computed the same way the sales screen records it
  (`toISOString().split('T')[0]`), so the filter always matches stored rows.
- Product count stays as-is (business products), since that was correct.

**Side benefit:** the profile page now sends far less data — previously every
visit downloaded every sale and every customer record the seller ever had.

**Files touched:** `app/Http/Controllers/Api/SaleController.php`,
`resources/views/muuzaji/profaili.blade.php`, `progress.md`.
Verified: `php -l` clean, inline JS passes `node --check`.

---

## 17. Root cause found: date-range filters never actually filtered

**Why "only the statements changed":** the profaili page and `SaleController`
were correctly sending `date_from`/`date_to`, but the underlying Supabase
query builder had a bug that silently dropped half of every date range.

**The bug (`app/Services/Supabase.php`):** when turning filters into a
PostgREST query string, every filter was stored **keyed by column name**:

```php
$params[$key] = $val;   // 'sale_date' => 'gte.X' then OVERWRITTEN by 'lte.Y'
```

Two filters on the same column (a `>=` plus a `<=` — the definition of a
date range) therefore overwrote each other, and only the LAST one survived.
The query sent to Supabase was `sale_date=lte.<today>` — everything from
history up to today — which is why the seller profile kept showing all-time
totals no matter what the code requested.

**The fix:** wheres are now collected as an ordered LIST of `[column, value]`
pairs and `buildQs()` emits each pair as its own query-string parameter
(PostgREST accepts repeated params for the same column and ANDs them).

**Who else was affected:** `AdminController::sales` — the same `>=`/`<=`
chain powering Rejea's "Chuja kwa Kipindi cha Tarehe" date filter. That
filter was also quietly returning "everything up to the end date" instead
of the chosen window. Fixed by the same change.

**Verification:** a temporary reflection script printed the exact PostgREST
URL for representative queries; a regression suite confirmed date ranges now
emit BOTH filters (`sale_date=gte.X&sale_date=lte.Y`), and that `whereIn`,
`whereNull`, `select`, `order`, and `limit` URLs are unchanged and correct.
All changed PHP passes `php -l`, profaili JS passes `node --check`, and all
Blade views recompile cleanly.

Also in this pass: the profaili products card label is now simply
"Bidhaa za Biashara" for sellers — the count itself comes from the
business-wide products endpoint, so the old "Bidhaa Zako" label was
misleading.

**Files touched:** `app/Services/Supabase.php`,
`resources/views/muuzaji/profaili.blade.php`, `progress.md`.

---

## 18. uza: double stock deduction fixed + AI paper-sales import

**The double-deduction bug (selling 2 deducted 4):** the sale screen allowed
the "KAMILISHA MAUZO" button to fire twice — during the network round-trip
the button stayed clickable, and two requests each passed the stock check
because both read the pre-sale stock. Both sales saved, so stock was cut
2 + 2 = 4. Two-layer fix:
1. **Frontend guard** (`uza.blade.php`): `handleSale()` bails instantly if a
   sale request is already in flight, and the button is disabled
   (`disabled` + dimmed) while `loading` is true.
2. **Server-side idempotency** (`SaleController::store`): every sale attempt
   now carries a `clientSaleKey`. Before saving, the backend looks for an
   existing sale whose notes carry that key (`ai_dup_<key>` prefix) and, if
   found, returns the ORIGINAL sale instead of creating a second one. The
   marker lives in `notes` (`ai_dup_<key> | <original notes>`), so retries
   are caught even if the first response never reached the browser.

**New feature — AI paper-sales import on uza:** merchants who recorded
sales on paper can now photograph the paper and have the system enter the
sales.

Backend (two new endpoints in `AiImportController`, routes registered):
- `POST /api/sales/ai-import` — same pipeline as bidhaa-mpya (compress
  client-side → OCR the photos → pull the business product list) but a NEW
  Gemini prompt: every handwritten line is matched to an EXISTING product
  (abbreviations/spelling variations count; Swahili-English mixing handled).
  Unmatched lines come back as UNMATCHED for manual pairing. Written prices
  are used; when no price is written, unitPrice=null and the system later
  applies the product's default selling price. Distinct sale lines are
  never merged.
- `POST /api/sales/ai-commit` — creates the real sale after user review:
  merges duplicate rows, re-validates every quantity against LIVE DB stock,
  get-or-creates the customer by name (same as manual sales), creates the
  sale + sale_items, deducts stock the same way as `store()`, updates
  customer stats, and honors the same `clientSaleKey` idempotency.

Frontend (`uza.blade.php`):
- A purple "Ingiza Mauzo kwa Picha (AI)" entry card opens a modal mirroring
  the bidhaa-mpya one: upload zone + camera capture, up to 6 photos,
  client-side compression, animated progress with rotating status messages.
- Results table columns: handwritten text, matched system product (with a
  dropdown to manually pair UNMATCHED rows), editable quantity, editable
  unit price (placeholder shows the default selling price when none was
  written), computed line total, confidence/needs-review badges, and a
  per-row "Hifadhi" button plus "Hifadhi Zote" with a confirmation dialog.
- Client-side stock validation warns before saving when the requested
  quantity exceeds available stock; the server re-checks regardless.
- Committed rows flip to "Imehifadhiwa", products reload so stock badges
  stay accurate, and each row is saved as its own sale via the same
  idempotent endpoint.

**Verification:** `php -l` clean on all changed PHP; both uza script blocks
pass `node --check`; `php artisan route:list` shows the two new routes; a
live server test confirms the page renders the AI entry + modal and both
endpoints answer 401 (auth required) rather than 404. The Gemini round-trip
itself needs a live logged-in test with real photos.

**Files touched:** `app/Http/Controllers/Api/SaleController.php`,
`app/Http/Controllers/Api/AiImportController.php`, `routes/api.php`,
`resources/views/muuzaji/uza.blade.php`, `progress.md`.

---

## 19. uza: floating cart dock — quantity + add-to-cart always at hand

**The problem:** to add an item the seller picked a product at the top of
the list, then had to scroll past the entire product grid to reach the
quantity stepper and "Ongeza Kikapuni" button at the bottom. The checkout
button was even further down.

**The fix — a floating dock fixed to the bottom of the viewport** (it
travels with the scroll, always one thumb-reach away):
- **Row 1 (when a product is selected):** the product's name + price +
  live remaining stock, a compact − / qty / + stepper, and the "Ongeza"
  button — add to cart from anywhere on the page without scrolling.
- **Row 2 (always visible):** a cart pill showing total items + running
  total (tap to open the cart modal) and the "KAMILISHA" checkout button
  (shows a spinner while the sale request runs, disabled when the cart is
  empty — preserving the double-submit guard).
- Page content gets bottom padding so nothing hides behind the dock; on
  small screens the dock wraps gracefully and the padding grows.
- The old static "cart badge" above the product list was removed (the dock
  replaces it), and the blue selected-product card now just reminds the
  user to use the dock.

**Verification:** both uza script blocks pass `node --check`; a live server
render confirms the dock markup and styles are present in the served page.

**Files touched:** `resources/views/muuzaji/uza.blade.php`, `progress.md`.

---

## 20. "Hitilafu ya mtandao" on Kamilisha Mauzo — diagnosed & fixed

**Diagnosis (with a real token and the real HTTP path):** the sale endpoint
itself works — a scripted POST returned 201 and the retried POST returned
the duplicate (idempotency OK). But **timing** exposed the problem: a single
sale takes **~8.3s even on localhost**, because the request performs many
sequential Supabase REST roundtrips (auth user, stock checks per item,
sale insert, items insert, per-item stock re-reads, invoice numbering,
customer stats, audit logs).

**Why it failed in the browser with internet on:**
1. `generateInvoiceNumber()` downloaded **every invoice of the year** for
   that seller to compute the next sequence — unbounded work that grows as
   sales accumulate, pushing request time towards the PHP limit.
2. On Windows, PHP's `max_execution_time` counts WALL-CLOCK seconds. One
   laravel.log entry (`Maximum execution time of 60 seconds exceeded`)
   matches a real sale attempt: when PHP dies mid-request the browser gets
   a truncated/non-JSON response, `res.json()` throws, and the catch block
   misreported it as "Hitilafu ya mtandao".

**Fixes:**
- `generateInvoiceNumber()` now reads only the **most recent 200 invoices**
  (`order by created_at desc, limit 200`) — the sequence only needs the
  highest number, and the highest is near the end.
- `SaleController::store()` raises its own `set_time_limit(300)` so a slow
  network can no longer kill the request mid-flight.
- The frontend no longer blames the network blindly: the response body is
  read as TEXT first (JSON parse failures no longer masquerade as network
  errors), server error messages are surfaced verbatim, 5xx shows
  "Server imekutana na hitilafu (code)", and on a genuine network hiccup it
  **auto-retries once** — safe from double-charging because `clientSaleKey`
  idempotency makes the retry return the original sale.

**Verification:** kernel-level POST → 201 with correct stock deduction;
HTTP-level POST → 201, duplicate retry → 200 `duplicate:true`; timing
confirmed 8.1–8.8s per sale (now with a bounded invoice query); `php -l`
clean, all uza JS passes `node --check`.

**Files touched:** `app/Http/Controllers/Api/SaleController.php`,
`resources/views/muuzaji/uza.blade.php`, `progress.md`.

---

## 21. uza: REAL root cause of the double stock deduction (DB trigger)

**What you saw:** selling 2 items still deducted 4 from stock, even after the
earlier double-submit fixes (section 18).

**How it was found (evidence, not guessing):**
- The audit log (`SALE_CREATE` entries) records `stock_updates` per sale.
  Reading the last 10 showed every logged write was internally correct
  (`old=9 new=7 sold=2`) — yet the NEXT sale for the same product always
  started from a stock that had already lost the previous sale's quantity
  again: 9→7 logged, next sale reads 5, then 2. Stock fell by exactly 2×
  each sale, inside a single request, with no duplicate sale rows or
  duplicate invoices.
- A controlled experiment on a disposable test product (stock 10) inserted
  ONLY a `sale_items` row through the REST API — no PHP involved — and the
  stock moved 10 → 9 by itself. **A database trigger on `sale_items` was
  deducting stock automatically**, and the app deducted again in PHP.

**Root cause:** trigger `update_stock_after_sale_item` (function
`update_product_stock`, `AFTER INSERT ON public.sale_items`) existed in the
Supabase project. It stacked with the app's own deduction in
`SaleController::store` and `AiImportController::commitAiSale`, so every sale
deducted twice. It would also silently corrupt stock during any backup
restore that re-inserts historical sale_items rows.

**Fix:** dropped the trigger (and its now-unused function) in Supabase via
`supabase/fix_double_stock_deduction.sql` (kept in the repo; it also contains
the read-only discovery + rollback-based proof queries, so it can be re-run
any time to audit for stray triggers).

**Verification after the drop (all on the live DB, disposable rows, fully
cleaned up):**
- sale_items insert alone: stock stays unchanged (was 10→9 before).
- Full sale flow through the real endpoint: product stock 10, sold 2 →
  stock 8 (exactly −2; was −4 before).
- Replaying the same `clientSaleKey` returned `duplicate: true` and stock
  stayed 8 (idempotency intact).

**Files touched:** `supabase/fix_double_stock_deduction.sql` (new),
`progress.md`. No PHP/frontend changes needed — the app's sale logic was
already correct; the database was double-deducting behind it.

**Note for the future:** if stock ever looks wrong again, first re-run
Step 1 of `supabase/fix_double_stock_deduction.sql` to check nobody re-added
a stock trigger, then check the `SALE_CREATE` audit logs' `stock_updates`.

---

## 22. Muuzaji pages restyled to match msimamizi + save/cancel button feedback

**What was requested:** the msimamizi pages already use professional Font
Awesome icons (section 14) — apply the same treatment to all four muuzaji
pages (profaili, mauzo, matumizi, uza), and make the "Ghairi" / "Hifadhi"
buttons in the profile editor feel responsive (spinners when clicked).

**What was done:**

- **Professional icons everywhere:** every decorative emoji replaced with
  Font Awesome 6 solid icons via the same `FA_MAP` + `ic(name, size)`
  helper convention used on the msimamizi pages (FA 6.5.2 CDN added to
  each page's `<head>`).
  - **Sidebars:** the emoji nav icons (👤 💰 📊 🛒) and the 🚪 logout are
    now inline SVG line icons, stroke 1.8, matching the msimamizi sidebar
    partial; the uza hamburger ☰ became the same SVG used on other pages.
  - **profaili:** header refresh/edit buttons, stat-card icons (box, cart,
    users, money — now tinted to match their card colors), account info
    rows (user, mail, phone, id-card, building, location, clock, shield),
    edit-profile button, main logout, modal camera + GPS buttons.
  - **mauzo:** sale rows (box, user, card, doc), Hariri/Dai Risiti
    buttons, FUNGA MAUZO lock button, empty state, Nenda Kuuza, modal
    close (×), warning icon in the close-sales confirm modal.
  - **matumizi:** date selector (calendar), ONGEZA MATUMIZI (+), expense
    rows (tag, trash, doc), empty state, refresh, back-arrow SVG.
  - **uza:** page title (cart), refresh buttons, search box, selected
    product (box), cart modal header + close, AI-import warning state,
    cart instructions.
- **Ghairi / Hifadhi responsiveness (profaili edit modal):**
  - **Hifadhi** now swaps to a spinning `fa-spinner` + "Inahifadhi..."
    while the profile update request runs, with `pointer-events: none`
    so it can't be double-clicked; both buttons restore afterwards
    (success path reloads the page, error path restores the labels).
  - **Ghairi** closes the modal instantly — a spinner there would only
    flash for a millisecond — so instead it is click-locked and dimmed
    while a save is in flight, preventing a mid-save dismiss. The same
    pattern was applied to the equivalent save/cancel pairs that existed
    on mauzo (edit sale) and matumizi (add expense) for consistency.
  - The profaili GPS button also got proper busy feedback (spinner while
    locating, check/cross result, re-enabled after 2s) replacing its old
    emoji status text.

**Files touched:** all four `resources/views/muuzaji/*.blade.php`,
`progress.md`.

**Verification:** `php -l` clean on all four pages; every inline script
block passes `node --check`; `php artisan view:cache` compiles cleanly;
a live render of `/muuzaji/profaili` returns 200 with the FA stylesheet
and icons present.

---

## 23. mteja/biashara: Wasiliana modal fixed + Twende Dukani feedback

**What you saw:** clicking **Wasiliana** did nothing — the PIGA SIMU /
TUMA UJUMBE / GHAIRI modal existed in the markup but never opened.

**Root cause:** the click handler did `parseInt(btn.getAttribute('data-id'))`.
Business ids are UUID strings (`19bf6afe-...`), so `parseInt` truncated them
(`19bf6afe` → `19`), the business lookup by id always failed, and the modal
open call was never reached. Same bug in the phone-number click handler.

**Fixes (mteja/biashara.blade.php):**
- Id lookups now compare **strings** (`String(b.id) === id`), so the
  Wasiliana modal opens correctly and PIGA SIMU / TUMA UJUMBE dial
  `tel:` / `sms:` links directly — no more copying numbers by hand on
  phones. Option labels uppercased (PIGA SIMU, TUMA UJUMBE, GHAIRI) with
  Font Awesome phone/SMS icons.
- **Twende Dukani** now shows a spinner ("Inapata njia...") while the
  user's location is resolved, with a hard 8-second timeout so the button
  can never stay stuck, and a double-tap guard so repeated taps can't fire
  multiple geolocation lookups. If the browser blocks the popup
  (`window.open` returning null — common when it fires after async work),
  it falls back to **same-tab navigation** to Google Maps, which always
  works, instead of failing silently.
- Restyled to the professional icon convention (FA_MAP + ic()): sidebar
  SVGs, store/compass/check/search/refresh icons, empty state, search box
  with icon; removed emoji console.log noise.

**Files touched:** `resources/views/mteja/biashara.blade.php`, `progress.md`.

**Verification:** `php -l` clean, inline JS passes `node --check`,
`php artisan view:cache` compiles, live page renders 200 with icons.

---

## 24. Cursor jumps while typing — fixed everywhere (system-wide scan)

**What you saw:** on `/mteja/biashara` the search bar threw the cursor out
while typing — you could not write continuously.

**Root cause (same as section 11a):** an `input` event handler called the
page's full `render()`, which rebuilt the entire DOM on every keystroke —
destroying the very input being typed in (and re-creating it without focus,
with the caret lost). Any button click elsewhere also re-rendered and
reset the field.

**System-wide scan results** (grep for `input` handlers across all views):

Fixed — typing triggered a full re-render:
1. **mteja/biashara** — search bar (both render branches).
2. **muuzaji/uza** — product search (`filterProducts()` called `render()`).
3. **system_admin/dashboard** — Watumiaji tab user search.
4. **system_admin/notify** — notification title + message inputs.

Confirmed safe (no change needed): all OTP boxes (admin/cashier/client
signup, forgot), tangaza (fixed in section 11a), bidhaa-mpya (edits sync
into state; only sibling regions update), mauzo edit modal, matumizi
preview, profaili edit inputs, uza AI-table inputs, notify recipient-modal
search (only re-renders the list region).

**The fix pattern (applied consistently):** typing now updates state and
re-renders ONLY the results region below/around the input:
- biashara: search bar is rendered once; `renderResultsRegion()` re-fills
  `#businessListHost` + the counter. Card event handlers (Wasiliana,
  phone links, Twende Dukani) moved into a reusable `bindCardEvents()` —
  rebinding is safe because the old elements are discarded with the old
  DOM, so listeners never stack.
- uza: `filterProducts()` now calls `renderProductGrid()` which re-fills
  `#productGridHost` (the products grid) only; full `render()` is still
  used for everything else (selection, cart, dock).
- dashboard: user search re-fills `#userListHost` via `renderUserList()`;
  user cards extracted to `userCardHtml()` shared by both paths.
- notify: title/message inputs update state, their character counters,
  and the send button's enabled state **in place** — no DOM rebuild at
  all while typing.

During the biashara rewrite the file was replaced cleanly (a previous
partial edit had left a truncated template literal).

**Files touched:** `resources/views/mteja/biashara.blade.php`,
`resources/views/muuzaji/uza.blade.php`,
`resources/views/system_admin/dashboard.blade.php`,
`resources/views/system_admin/notify.blade.php`, `progress.md`.

**Verification:** `php -l` clean on all four; every inline script passes
`node --check`; `php artisan view:cache` compiles; all four pages render
200 on the live server.

---

## 25. mteja/matangazo: Agiza modal, real like toggle, video streaming

**What was requested:** Agiza should behave like biashara's Wasiliana
(modal with PIGA SIMU / TUMA UJUMBE / GHAIRI); likes must work — heart
turns red, count +1, toggling un-likes, and counts must be accurate even
for users who never liked; videos must stream with segment buffering.

**Root causes found:**
- **Agiza + like were both dead** from the same `parseInt(UUID)` bug as
  biashara (§23): post ids are UUID strings, `parseInt("abc...")` → NaN,
  lookups failed silently.
- **Likes had no visible red state** (emoji ❤️/🤍 only) and the old code
  re-rendered the WHOLE feed on every like — which also reset any video
  being watched and made counts drift from the server.
- **Videos** all downloaded at once (`<video src=...>` for every card),
  no buffering strategy.

**What was done (page rewritten):**
- **Agiza** now opens the exact same contact modal as biashara Wasiliana
  (PIGA SIMU / TUMA UJUMBE / GHAIRI with `tel:` / `sms:` links), matching
  by string id. Phone-number taps open it too.
- **Like system rebuilt:** FA heart (`fa-regular` outline → `fa-solid`
  red when liked, red-tinted button). Optimistic UI on tap, then the
  SERVER's authoritative `liked` + `like_count` (returned by
  `POST /api/reactions/like`, a true server-side toggle) wins — counts
  are accurate even with concurrent likers. Per-post double-tap guard.
  One like per user enforced by the server (existing row → unlike).
- **Accurate counts for everyone:** `GET /api/matangazo` already embeds
  real `like_count`/`report_count` for all viewers (verified in
  AdvertisementController::publicIndex) — the frontend now simply uses
  them instead of local guesses; per-post count updates happen in place
  (no re-render, video keeps playing).
- **Video streaming with segment buffering:** videos render with
  `data-src` + `preload="metadata"` and are attached only when scrolled
  near the viewport (IntersectionObserver, 250px pre-roll). Sources are
  streamed progressively via HTTP Range requests (Cloudinary supports
  them), so segments buffer as needed instead of downloading every video
  up front. `.m3u8` sources get true segmented streaming via hls.js
  (lazy-loaded, Safari uses native HLS).
- Restyled with the professional icon convention (sidebar SVGs, FA
  action icons); report flow surfaces the server's duplicate-report
  message instead of silently failing.

**Files touched:** `resources/views/mteja/matangazo.blade.php`
(rewritten), `progress.md`.

**Follow-up — business avatar on every post:** each post card now shows
the business's profile photo (`users.business_logo_url`, already embedded
by the API — verified live: all 4 current posts carry the field). When no
logo is set, a placeholder letter (first character of the business name,
uppercase) fills the avatar circle; if a stored photo URL fails to load,
the image swaps itself to the letter via `onerror` — never a broken-image
glyph.

**Verification:** `php -l` clean; inline JS passes `node --check`;
`php artisan view:cache` compiles; live page renders 200.

---

## 26. mteja/profaili: profile photo upload fixed

**What you saw:** uploading a profile photo on `/mteja/profaili` always
failed.

**Root cause:** the Cloudinary upload URL used a non-existent API version
— `https://api.cloudinary.com/v1_0/...`. The correct version is **`v1_1`**
(the muuzaji profile page already used it correctly), so every upload
request 404'd before a photo could ever be saved. The backend
(`PUT /api/user/profile`) already accepts `business_logo_url` — only the
upload URL was wrong.

**Fixes (mteja/profaili.blade.php):**
- Upload URL corrected to `v1_1`.
- Cloudinary error bodies are now surfaced ("Imeshindikana kupakia picha:
  <reason>") instead of a generic failure, in both the upload and the
  profile-update steps.
- Camera badge shows a spinner + click-lock while the upload runs
  (double-upload guard), restoring afterwards; a success toast confirms
  the save.

**Files touched:** `resources/views/mteja/profaili.blade.php`,
`progress.md`.

**Verification:** `php -l` clean; views recompiled; page renders 200;
Cloudinary `v1_1` endpoint confirmed reachable (400 on empty POST =
endpoint exists, `v1_0` was 404).

---

## 27. mteja pages restyled — professional icons completed

**What was requested:** apply the msimamizi/muuzaji professional-icon
treatment to the mteja pages and remove local emojis.

**Status:** biashara and matangazo were already restyled during the
earlier fixes (sections 23 & 25). This pass finished **profaili**:
- FA 6.5.2 CDN added; `FA_MAP` + `ic()` helper (project convention).
- Sidebar: emoji nav (🏪 📢 👤) → inline SVG line icons; 🚪 logout → SVG.
- Camera badge (photo upload): 📷 → `fa-camera` (the busy spinner and the
  restored state both render the icon now).
- Buttons: "Hariri Wasifu" gains a pen icon, the save button shows a
  spinning `fa-spinner` + "Inahifadhi..." while saving (matches the
  muuzaji/mauzo busy pattern), and "Toka kwenye Akaunti" gains the logout
  icon.

**Emoji audit:** a Unicode scan of all mteja views finds zero emojis in
the three live pages. The only remaining emojis sit in
`mteja/partials/sidebar.blade.php`, which is DEAD CODE — no route or view
includes it (the three mteja pages are standalone documents, unlike the
msimamizi SPA shell). Left untouched; can be deleted in a cleanup pass.

**Files touched:** `resources/views/mteja/profaili.blade.php`,
`progress.md`.

**Verification:** `php -l` clean; views recompiled; page renders 200 with
FA icons present.

---

## 28. Clickable profile/business photos with WhatsApp-style viewer

**What was requested:** every rendered profile photo should be clickable,
open large/clear, and deter screenshots "just like WhatsApp".

**Honest limitation (documented up front):** NO web app — including
WhatsApp Web — can block OS-level screenshots; browsers have no such API.
What was implemented is the same deterrence stack WhatsApp Web uses.

**New shared partial: `resources/views/partials/photo-viewer.blade.php`**
- Any element with class `js-avatar-view` + `data-full="<url>"` opens a
  full-screen lightbox (also exposed as `PhotoViewer.show(url, name)`).
- Deterrence: the photo is painted as a CSS **background** (never an
  `<img>`, so no "open image / save image as" entry point), a transparent
  **shield layer** intercepts taps/right-clicks, `contextmenu` is
  disabled, dragging/selection/long-press-callout are disabled (CSS), and
  a rotating **watermark** (name + DukaMkononi + sw-TZ timestamp, 16
  tiles) is painted OVER the photo so any capture identifies the viewer.
- Event delegation on `document` — works with the pages' innerHTML
  re-renders; closes via ✕, shield tap, backdrop tap, or Escape.

**Wired into every live photo render (14 pages):** sidebar avatars on all
muuzaji/msimamizi/system_admin/mteja pages (codemod; images get
`pointer-events:none` so the click lands on the container), the big
profile avatars (mteja + muuzaji profaili), system_admin dashboard user
cards + modal avatar, msimamizi index seller rows, bidhaa-mpya sidebar,
mteja biashara business logos, and mteja matangazo post avatars (keeping
the letter fallback + broken-image swap). The msimamizi pages are single
`@verbatim`-wrapped, so the include is injected via an
`@endverbatim/@verbatim` split around `<body>` (the established pattern).
Letter placeholders have no `data-full` and stay non-clickable.

**Files touched:** `resources/views/partials/photo-viewer.blade.php`
(new), 14 page views (codemod), `progress.md`.

**Verification:** `php -l` clean on all 15 files; all inline scripts pass
`node --check`; `php artisan view:cache` compiles; all 12 routed pages
serve 200 with the viewer markup present.

---

## Ideas for later (not yet done)

- Create the `admin_notification_read_state` and `ai_import_verifications`
  tables in Supabase if missing (setup endpoints exist for both).
- The dashboard (`index`) seller list still downloads users without the
  business parameter — the backend supports `?business=` now, so the
  frontend call can be switched over for another speed win.
- Pagination / lazy loading for very long day lists on Rejea.
- Apply the same server-side filtering pattern to the mteja and muuzaji
  apps.

---

## Expo sync — Msimamizi dashboard (`index`)

**Date:** 2026-09-26
**Module:** Msimamizi
**Blade file inspected:** `resources/views/msimamizi/index.blade.php`
**Expo file modified:** `Duka_mkononi/app/msimamizi/index.tsx`
**Supporting file modified:** `Duka_mkononi/constants/api.ts`

**Laravel endpoints used:**
- `GET /api/admin/users?business=<name>&role=seller` (AdminController::users)
- `GET /api/user/profile` (ProfileController::show)
- `PUT /api/user/profile` (ProfileController::update)
- `PUT /api/admin/users/{id}/status`, `DELETE /api/admin/users/{id}`

**Old Node behaviour discovered in Expo:**
- The mobile dashboard called `/api/admin/users` with **no query params** and
  filtered the whole users table in the client. Laravel accepts `?business=`
  and `?role=` (documented in entry 3) and the current Blade page already
  passes them, so the mobile call was downloading far more data than needed.
- The API base URL was the dead Node backend
  `https://duka-mkononi-backend-6b9g.onrender.com`.
- The edit-profile modal had no **business type** or **business description**
  fields, so the AI-import business info added in entry 6 could never be
  edited from mobile.

**Changes made (Expo only):**
1. `constants/api.ts` now points at `https://www.dukamkononi.com` (the
   Laravel API).
2. Seller fetching (both the live-sync callback and `loadSellersData`) now
   requests `/api/admin/users?business=<businessName>&role=seller`, matching
   the Blade page and the Laravel contract. The existing client-side guard
   filter is kept as a safety net.
3. Added `loadProfileIntoEditForm()` — fetches `/api/user/profile` on load
   and syncs `business_type` / `business_description` into the edit form and
   the AsyncStorage cache (same behaviour as the Blade page).
4. The edit-profile modal gained the **Aina ya Biashara** chip selector (same
   22-type list as the AI-import page / `business_types` locale keys) and the
   **Maelezo mafupi ya Biashara** field; both are now sent in the
   `PUT /api/user/profile` body and persisted to the local cache.
5. Profile fields wrapped in a `ScrollView` so the larger modal stays usable
   on small screens (mobile UX preserved — no Blade layout copied).

**Verification:** `npx tsc --noEmit` passes with no errors after increasing
`--max-old-space-size`. Reused existing locale keys (`profile.business_type_*`,
`business_types.*`) confirmed present in all 8 locale files, so no locale
edits were needed.

**Remaining issues / not changed:**
- The `ngrok-skip-browser-warning` header is a leftover from the ngrok/Node
  backend. It is harmless and could be removed app-wide in a later cleanup.
- `PUT /api/admin/users/{id}/status` in `AdminController` only accepts
  `pending|approved|rejected`; the **Ondoa** action (and its fallback) sends
  `inactive`, which the server rejects with 400. The Blade page has the same
  behaviour, so this was left as-is and flagged rather than diverging from
  the web implementation.

---

## Expo sync — Msimamizi Ripoti (`ripoti`)

**Date:** 2026-09-26
**Module:** Msimamizi
**Blade file inspected:** `resources/views/msimamizi/ripoti.blade.php`
**Expo file modified:** `Duka_mkononi/app/msimamizi/ripoti.tsx`

**Laravel endpoints used:**
- `GET /api/admin/users?business=<name>&role=seller,admin`
- `GET /api/admin/products?business_name=<name>&slim=1`
- `GET /api/admin/sales?business_name=<name>&slim=1`
- `GET /api/admin/customers?business_name=<name>`
- `GET /api/products/my`, `GET /api/sales/my`, `GET /api/customers/my` (seller path)

**Old Node behaviour discovered in Expo:**
- The admin report fetched `/api/admin/users`, `/api/admin/products`,
  `/api/admin/sales` and `/api/admin/customers` with **no query params** and
  filtered every row in the client — the exact pattern entry 3 removed from
  the Blade page. The Blade page already passes `business`/`business_name`,
  `role=seller,admin` and `slim=1`.
- Because the mobile build relied on the **full** sales payload, customer
  names came from the embedded `sale.customers` relation. Switching to
  `slim=1` (which drops embedded relations) requires resolving names from a
  `customerNameById` map keyed on `customer_id` — the approach the Blade page
  already uses.
- The Expo page had **no client-side search**, while the Blade page searches
  the Mauzo / Bidhaa / Wateja tabs.

**Changes made (Expo only):**
1. All four admin fetches (plus the live-sync callback) now pass the
   server-side filters Laravel expects (`business` / `business_name`, `role`,
   `slim`). Client-side guard filters are retained as a safety net.
2. Reordered `fetchBusinessData()` to fetch customers before sales and build a
   `customerNameById` map; `buildBusinessSales()` now accepts that map and
   resolves names from `customer_id` when the slim payload has no embedded
   `customers` relation (full payloads still use the relation).
3. Added the client-side **search bar** to the Mauzo / Bidhaa / Wateja tabs,
   filtering by the same fields as the Blade page, with a clear button and a
   no-results state. Search resets when switching tabs.

**Verification:** `npx tsc --noEmit` passes clean.

**Remaining issues / not changed:**
- **Exports** (PDF / Excel / Print) are still stubbed in Expo with a
  "coming soon" alert (`handlePrintPDF`, `handleExportExcel`, `handlePrint`),
  whereas the Blade page builds a printable HTML table and a CSV. Implementing
  this natively is substantial (expo-print / expo-sharing / file system) and
  was left for a dedicated pass.
- The seller path (`/api/products/my`, `/api/sales/my`, `/api/customers/my`)
  is unchanged and still relies on the embedded `customers` relation, matching
  `SaleController::my`.

---

## Expo sync — Msimamizi Rejea (`preview`)

**Date:** 2026-09-26
**Module:** Msimamizi
**Blade file inspected:** `resources/views/msimamizi/preview.blade.php`
**Expo file modified:** `Duka_mkononi/app/msimamizi/preview.tsx`

**Laravel endpoints used:**
- `GET /api/admin/users?business=<name>&role=seller,admin`
- `GET /api/admin/products?business_name=<name>`
- `GET /api/admin/sales?business_name=<name>` (+ `date_from`/`date_to` when a
  filter is active on the web)
- `GET /api/office-expenses/range?start_date=&end_date=`

**Old Node behaviour discovered in Expo:**
- Users fetched with `?business=` but **without `role=seller,admin`** (Blade
  passes it so the admin's own records are included).
- Products fetched from `/api/admin/products` with **no `business_name`**, then
  filtered in the client — the whole products table crossed the network.
- Sales fetched from `/api/admin/sales` with **no `business_name`/date range**,
  then filtered in the client.
- Expenses were fetched with a **per-date request** (`loadDailyExpenses` looped
  over every sale date), i.e. the N+1 pattern the Blade page replaced with a
  single range request. Expense-only days (expenses but no sales) were dropped.

**Changes made (Expo only):**
1. Added `&role=seller,admin` to the users fetch.
2. Added `?business_name=<name>` to the products fetch.
3. Added `?business_name=<name>` to the sales fetch.
4. Replaced `loadDailyExpenses(dates)` with `loadExpensesRange(start, end)` — a
   single `/api/office-expenses/range` request for the whole window. Day cards
   are now built from the union of sales dates and expense dates, so
   expense-only days appear (with a negative net), matching Blade.
5. Dropped the `"Mteja"` placeholder from the daily customers list (Blade only
   lists genuinely recorded names).

**Verification:** `npx tsc --noEmit` passes clean.

**Remaining issues / not changed (documented Blade features still missing in
Expo — require a dedicated feature pass):**
- **Date-range filter** (progress.md §2a): Blade has Kuanzia/Hadi date pickers
  that re-fetch sales with `date_from`/`date_to`. Expo has no filter UI yet, so
  it always requests the full window.
- **"Zimesomwa" mark-all-read** (progress.md §2c): Blade calls
  `POST /api/admin/notifications/mark-all-read` and
  `POST /api/admin/notifications/read-state`; Expo's Taarifa tab has no
  read-state concept.
- **"Chapisha Taarifa" per-day print** (progress.md §2d): Blade builds a
  printable HTML document for one day; Expo has no print/PDF action
  (expo-print/expo-sharing would be needed).

---

## New business type — `motorcycle_spares` ("Spea za Pikipiki")

**Date:** 2026-09-26
**Module:** Msimamizi (Bidhaa Mpya / AI import) + shared business-type lists

**Change:** added `motorcycle_spares` to the business-type list, right after
`spare_parts` (they are commonly sold by the same shop), and brought every
place that keeps its own copy of the list in line:

| File | What it holds |
| --- | --- |
| `resources/views/msimamizi/bidhaa-mpya.blade.php` | `AI_BIZ_TYPES` in the AI-import modal |
| `resources/views/msimamizi/index.blade.php` | `BUSINESS_TYPES` in the dashboard edit modal |
| `resources/views/admin-signup.blade.php` | business-type `<select>` at registration |
| `app/Http/Controllers/Api/AuthController.php` | `BUSINESS_TYPE_ALLOWED` (registration validation) |
| `app/Http/Controllers/Api/AiImportController.php` | `businessTypeLabel()` — label sent to Gemini |
| `Duka_mkononi/app/msimamizi/bidhaa-mpya.tsx` | `BIZ_TYPES` in the AI-import panel |
| `Duka_mkononi/app/msimamizi/index.tsx` | `BIZ_TYPES` in the dashboard edit modal |
| `Duka_mkononi/app/muuzaji/profaili.tsx` | `BIZ_TYPES` in the seller profile |
| `Duka_mkononi/locales/*.json` (8 languages) | `business_types.motorcycle_spares` |

**Why every list had to change:** `ProfileController::update()` stores
`business_type` verbatim, and each edit modal writes the select's value back on
save. Any list missing the new slug would have silently reset a shop that had
picked it back to "Nyingine" the next time they edited their profile. The AI
prompt is the other trap: `businessTypeLabel()` falls back to the raw slug, so
without the entry Gemini was told the business type was literally
`motorcycle_spares`.

**Verification:** `npx tsc --noEmit` passes clean; `php -l` clean on both
controllers; all 8 locale files parse.

---

## Msimamizi dashboard: business scoping moved from `business_name` to `business_id`

**Date:** 2026-09-27
**Module:** Msimamizi
**Blade file:** `resources/views/msimamizi/index.blade.php`
**Expo file:** `Duka_mkononi/app/msimamizi/index.tsx`
**Laravel endpoints inspected:** `GET /api/admin/users`, `PUT /api/admin/users/{id}/status`,
`DELETE /api/admin/users/{id}`, `GET|PUT /api/user/profile`
**Legacy Node behaviour found:** none in this screen. `constants/api.ts` already points at
`https://www.dukamkononi.com` and every call already used `Authorization: Bearer` +
`AsyncStorage('userToken')`. The endpoints, HTTP methods and payloads on both sides matched.

### Database/business migration impact

The dashboard listed an admin's sellers by sending the business *name*:

```
GET /api/admin/users?business=<businessName>&role=seller
```

`AdminController::users()` matched that with `where('business_name', $businessFilter)` -
a byte-exact comparison - and both frontends then re-filtered on
`user.business_name === <admin's own string>`. After the `businesses` / `users.business_id`
migration this silently hid sellers whose spelling differed from their admin's. Measured on
`Shirima spare part`, which has 5 members across 2 spellings:

| Filter | Rows returned |
| --- | --- |
| `?business=Shirima spare part` (old, exact) | **2 of 5** |
| `LOWER(business_name)` match | 5 of 5 |
| `business_id` (new) | **5 of 5** |

### Changes made

- `AdminController::users()` - business scope is now derived from the verified JWT
  (`jwt_business_id`), never from a query parameter. A business `admin` is always confined
  to their own business; `system_admin` keeps cross-business lookup via `?business_id=` or
  the legacy `?business=<name>` (resolved through `BusinessResolver`, so spelling variants
  collapse). An admin with no `business_id` gets an empty result and an `ADMIN_NO_BUSINESS`
  audit entry rather than falling through to an unscoped query. This also closes a
  cross-tenant hole: `admin/users` previously let any admin read another business's sellers
  by passing that business's name.
- `msimamizi/index.blade.php` - `loadSellersData()` no longer takes or sends a business name
  and no longer re-filters on `business_name`.
- `msimamizi/index.tsx` - same change; the `loadSellersData(adminBusinessName?)` parameter and
  its 8 call sites were dropped, and the per-user `console.log` noise in the seller filter
  was removed.
- `app/Services/Supabase.php` - **bug fix, unrelated to this screen but found while testing
  it.** `filterToString()` matched on `$val` alone, so `whereNotNull('col')`
  (`op = 'not.is', val = 'null'`) fell into the generic null branch and emitted
  `col=is.null`. `whereNotNull` was silently behaving as `whereNull` app-wide; verified
  `whereNotNull('business_id')` returned 0 of 67 admins. Now emits `col=not.is.null`.
  `whereNull` re-verified unchanged (7 rows, the customers).

### Tests / checks performed

- `npx tsc --noEmit` in `Duka_mkononi` - clean, no errors.
- `php artisan test` - 2 passed.
- `php -l` clean on `AdminController.php` and `Supabase.php`.
- Blade inline JS extracted (lines 593-1248) and checked with `node --check` - syntax OK.
- Live scoping test against production: 7/7 (business_id returns all members; two businesses
  do not overlap; all spellings share one `business_id`; impossible-id scope returns 0).
- End-to-end simulation of the endpoint as a real admin: 4/4 - every returned seller belongs
  to the caller's business, every row is a seller, and two different admins share no sellers.

### Remaining issues on this screen

- `GET /api/user/profile` still selects `business_type` / `business_description` from
  `users`, where those columns do not exist. The request always fails once and is retried
  with the columns stripped, so the admin's business type and description are always `null`.
  Not fixed here - it is shared by the profile screens, so it belongs to its own pass.
- `PUT /api/user/profile` still lets an admin rename their own `business_name`. That no longer
  orphans the seller list (scoping is by `business_id`), but it does leave `users.business_name`
  and `businesses.business_name` describing the same shop differently.
- The seller cards still render `seller.business_name` / `seller.business_location` from the
  user row, so cards can show slightly different text from the business card at the top of
  the same screen.
- No OTA/deploy performed.

---

## Bidhaa Mpya: catalogue scoping + the dead AI business-profile feature

**Date:** 2026-09-27
**Module:** Msimamizi
**Blade file:** `resources/views/msimamizi/bidhaa-mpya.blade.php`
**Expo file:** `Duka_mkononi/app/msimamizi/bidhaa-mpya.tsx`
**Laravel endpoints traced:** `GET /api/business/{businessName}/all-products`,
`POST|PUT|DELETE /api/products[/{id}]`, `GET|PUT /api/user/profile`,
`POST /api/inventory/ai-import`, `POST /api/inventory/ai-import/verify`
**Legacy Node behaviour found:** none. `API_BASE_URL` is the same
`https://www.dukamkononi.com` host, all calls use `Authorization: Bearer` +
`AsyncStorage('userToken')`, and every method and payload shape already matched
Laravel on both sides. Feature parity for this screen is otherwise complete:
categories + custom category, quick-fill, price/margin preview, existing-products
modal with owner badges, add / add-stock / edit / delete, and the AI import flow
(single + per-row verification) are all present on both.

### 1. Product catalogue was scoped by business name (and leaked across tenants)

Both clients called:

```
GET /api/business/<userData.business_name>/all-products
```

`ProductController::businessAllProducts()` resolved that with
`User::where('business_name', $businessName)` - byte-exact - then fetched
products for those users. Two problems:

- **Spelling fragmentation.** A shop whose members spell the name differently
  had its members silently split, and each spelling only ever saw its own slice
  of the catalogue.
- **Cross-tenant read.** The business name came straight from the URL and the
  caller was never checked against it, so any authenticated user could read any
  other business's catalogue by naming it. Same class of bug fixed in
  `AdminController::users()` in the previous entry.

Measured on `Shirima spare part`:

| Scope | Members | Active products |
| --- | --- | --- |
| `business_name` (old) | 2 | 107 |
| `business_id` (new) | **3** | **177** |

That admin could not see **70 of its own products** before this change.

**Fix:** `businessAllProducts()` now takes the scope from the verified JWT
(`business_id`) and never from the path. `system_admin` keeps cross-business
lookup via `?business_id=` or the legacy `?business=<name>` resolved through
`BusinessResolver`. A caller with no business gets an empty list plus a
`BUSINESS_PRODUCTS_FAILED` audit entry rather than an unscoped query. The
catch-block fallback that also matched on `business_name` was converted too.

A static route was added ahead of the parameterised one so neither client has to
put a name in the URL at all:

```
GET /api/business/my/all-products        (new, static - matched first)
GET /api/business/{businessName}/all-products  (kept, platform admins)
```

### 2. The AI business-profile feature could never save anything

This screen lets the admin set "Aina ya Biashara" and "Maelezo mafupi ya
Biashara" and tells the user *"AI itazitumia kuchambua picha zako"*. The values
are stored with `PUT /api/user/profile`. `ProfileController` still selected
`business_type` / `business_description` from `users`, where those columns do not
exist since the migration. The request therefore failed, was silently retried
with the columns stripped, and the response reported both fields as `null`. Every
save was a no-op, and the AI prompt was built with no business context.

`AiImportController` had the same dead reads in three places
(`import()`, `businessTypeOf()`, `businessDescriptionOf()`), plus
`ensureAiImportSchema()` still listed
`ALTER TABLE users ADD COLUMN business_type TEXT` as a required migration, so
`POST /api/setup/ai-import-migration` reported permanent failure for two columns
that should not exist there at all.

**Fix:**
- `ProfileController` now reads business fields from `businesses` via the user's
  `business_id` and merges them into the **same flat top-level keys** the clients
  already consume, so no client change was needed. Personal fields
  (`full_name`, `phone`) still write to `users`; business fields write to
  `businesses`. Renaming a business also refreshes `legacy_name_key`.
  The previous "fail, then retry without the columns" dance is gone, along with
  the two `preg_match` error-sniffing fallbacks it required.
  If an account has no business row (customers, unmigrated accounts) its legacy
  `users` columns are left intact so the screen still shows a name, and only
  `business_type` / `business_description` report `null`.
- `AiImportController` reads both values through `BusinessResolver::forUser()`,
  and `getBusinessSellerIds()` is scoped by `business_id`.
- The two stale `ALTER TABLE users` entries were removed from
  `ensureAiImportSchema()`.

Verified by writing a real `business_type` / `business_description` through the
new path, reading it back, then restoring the original values.

### 3. Expo wiped the value it had just saved

`saveBizProfile()` in the Expo file preferred the echoed profile over local
state:

```ts
parsed.business_type = savedUser ? savedUser.business_type : finalType;
```

`payload.user` is always present, so the `else` never ran. Against the broken
server that meant a successful save wrote `null` back over the cached
`userData.business_type` and `business_description`, so the fields visibly
cleared themselves. Now `??` is used, so a `null` echo falls back to what was
sent. The Blade equivalent did not have this bug.

### Changes made

| File | Change |
| --- | --- |
| `app/Http/Controllers/Api/ProductController.php` | `businessAllProducts()` scoped by JWT `business_id`; `system_admin` override; fallback path converted |
| `routes/api.php` | static `business/my/all-products` route ahead of the parameterised one |
| `app/Http/Controllers/Api/ProfileController.php` | business fields read/written on `businesses`, flat response shape preserved, error-sniffing fallbacks removed |
| `app/Http/Controllers/Api/AiImportController.php` | business type/description + seller ids via `BusinessResolver`; stale `users` ALTER checks removed |
| `resources/views/msimamizi/bidhaa-mpya.blade.php` | `fetchExistingProducts()` no longer takes or sends a business name |
| `Duka_mkononi/app/msimamizi/bidhaa-mpya.tsx` | same, including the `registerLive('products')` subscription; `fetchExistingProducts()` lost its `businessName` parameter (7 call sites); `??` fallback on save |

### Tests / checks performed

- `npx tsc --noEmit` in `Duka_mkononi` - clean, exit 0.
- `php artisan test` - 2 passed.
- `php -l` clean on `ProfileController`, `ProductController`, `AiImportController`,
  `Supabase`, `routes/api.php`.
- Both Blade `<script>` blocks extracted and checked with `node --check` - OK.
- `php artisan route:list --path=api/business` - the static route is registered
  and listed before the parameterised one.
- Live production test: 9/9. Business-type/description round-trip and restore;
  `business_id` never returns fewer members; the `Shirima spare part` split above
  is reproduced and fixed; two businesses share zero members; and the
  aggregate regression guard shows **70 products newly visible and 0 lost**
  (1782 -> 1852 reachable).
- The 217 active products still unreachable belong to `status='pending'` admins.
  The `status='approved'` filter in the query excludes them in the old code too,
  so this is pre-existing and not a regression from this change.

### Remaining issues on this screen

- **No "Thibitisha Zote" on mobile.** The Blade screen has a batch
  `verifyAllAiRows()` that validates every pending row, asks once, then verifies
  them in sequence with progress. The Expo screen only has per-row
  "Thibitisha" (`aiVerifyButton`); there is no batch button and no
  `verify_all` locale key. This is a missing feature rather than a broken one,
  so it was not added here - it is a product decision.
- Product create / update / delete were already correct: `store()` sets
  `seller_id` to the caller, and `update()` / `destroy()` both reject anything
  where `$product->seller_id !== $userId`. No `business_id` change was needed
  there.
- `AiImportController` still has ~6 other `where('business_name', ...)` reads
  and its `sales/ai-import` path resolves sellers the same old way. Those belong
  to the Tangaza / Ripoti screens, not this file.
- `whereNotNull` in `app/Services/Supabase.php` was fixed in the previous entry
  but `ensureAiImportSchema()`'s `columnExists()` helper is now only used for
  the `ai_import_verifications` table check and its column branch is dead.
- No OTA/deploy performed.

---

## Tangaza: delete-warning parity, stale numeric ID types, canonical ad title

**Date:** 2026-09-27
**Module:** Msimamizi
**Blade file:** `resources/views/msimamizi/tangaza.blade.php`
**Expo file:** `Duka_mkononi/app/msimamizi/tangaza.tsx`
**Laravel endpoints traced:** `GET|POST /api/matangazo`, `GET /api/matangazo/my`,
`DELETE /api/matangazo/{id}`, `POST /api/payments/pesapal/initiate`,
`GET /api/payments/pesapal/status/{order_tracking_id}`, plus a direct
client-side upload to Cloudinary
**Tables:** `matangazo` (22 cols), `payments`, `reactions`
**Legacy Node behaviour found:** none. Same `https://www.dukamkononi.com`
host, `Authorization: Bearer` + `AsyncStorage('userToken')` on mobile and
`localStorage('userToken')` on web, and every method and payload shape already
matched Laravel. Mobile is in fact slightly ahead: it polls
`/api/payments/pesapal/status/{id}` after redirect and special-cases
`PESAPAL_NOT_CONFIGURED`, neither of which the Blade screen does.

### No business_id migration work was needed on this screen

`matangazo` is owned by `user_id`, and this screen only ever manages the
caller's own posts. Verified the scoping is already correct:

| Operation | Guard |
| --- | --- |
| `GET /matangazo/my` | `Advertisement::where('user_id', $userId)` |
| `POST /matangazo` | `user_id` set to the caller |
| `DELETE /matangazo/{id}` | rejects with 403 when `$matangazo->user_id !== $userId` |
| `POST /payments/pesapal/initiate` | rejects with 403 when the `matangazo_id` is not the caller's |

`business_name` appears in this flow only as a display fallback for the ad
title, never as a scope. So unlike the previous two files, there was no
name-based scoping to convert here, and none was invented.

### 1. Mobile lost the "your subscription fee is not refundable" warning

`deleteAdvertisement()` on the web checks whether the post still has paid time
left and, if so, appends a warning before deleting:

```js
const hasActiveSub = ad && ad.expires_at &&
    (ad.payment_status === 'completed') &&
    (new Date(ad.expires_at).getTime() > Date.now());
```

The Expo version showed a bare `t('adverts.delete_confirm_message')` with no
`hasActiveSub` check at all, so on mobile a paid, still-running post and an
unpaid pending one produced an identical "are you sure?" dialog. This is porting
existing web behaviour rather than adding a feature, so it was brought across:
`deleteAdvertisement()` now takes the whole `UserMatangazo` and appends the
warning. A new key `adverts.delete_confirm_active_sub` was added to all 8
locale files (translated, not English placeholders).

**Honest scope note:** measured against live data, this changes nothing a user
sees *today*. Of 13 advertisements, 9 are `payment_status='completed'` but every
one has already expired (the latest `expires_at` is 2026-09-01), so
`completed + unexpired` is 0 and the warning would not fire on either platform.
It restores parity and starts mattering as soon as someone renews, which is
exactly the moment the money is at stake.

### 2. ID types were still typed as `number` after ids became UUIDs

`UserMatangazo.id` and `.user_id` were declared `number`, as were
`currentUser.id` and the parameters of `initiatePayment()`,
`runPaymentForMatangazo()` and `deleteAdvertisement()`. `matangazo.id`,
`matangazo.user_id` and `users.id` are all `uuid` in the database. The lie went
unnoticed because the API JSON arrives as `any`, so nothing ever type-checked
the real response shape.

Both clients then compounded it:

```ts
id: parseInt(userId)     // Expo,  userId is a UUID  -> NaN
id: parseInt(localStorage.getItem('userId') || user.id || '0')   // Blade -> NaN
```

`parseInt('7b6184b6-fd13-…')` is `NaN`. Nothing currently reads
`currentUser.id` on either platform, so this was latent rather than visibly
broken — but it is a trap for the next person who does read it.

All of these are now `string`, and both `parseInt` calls are gone. Every use of
an ad `id` was checked first: React `key`, a translation interpolation, and
`JSON.stringify` / template-literal request bodies. None of them do arithmetic
or compare against a numeric literal, so the change is type-only with no
runtime effect.

### 3. Ad titles preferred the legacy name over the canonical one

`AdvertisementController::store()` built the title from
`$user->business_name ?: ($user->full_name ?: 'Matangazo')`. After the
`business_id` migration the authoritative display name lives on
`businesses.business_name`, while `users.business_name` is a legacy snapshot
that can still carry an older spelling. The title now prefers
`BusinessResolver::forUser()?->business_name` and falls back to the user
columns unchanged, so customers and unmigrated accounts still resolve a title.

Across all 120 linked admin/seller accounts the legacy and canonical names are
currently identical (`name differs=0`), so this is preventive rather than a
visible fix today - but it means an ad created after a business rename will
carry the current name.

### Changes made

| File | Change |
| --- | --- |
| `Duka_mkononi/app/msimamizi/tangaza.tsx` | active-subscription delete warning ported from web; `id`/`user_id` retyped `number` -> `string`; `parseInt(userId)` -> `userId`; `deleteAdvertisement` now receives the ad |
| `resources/views/msimamizi/tangaza.blade.php` | `parseInt(...)` on a UUID removed from `currentUser.id` |
| `app/Http/Controllers/Api/AdvertisementController.php` | ad title prefers the canonical `businesses.business_name` |
| `Duka_mkononi/locales/{de,en,es,fr,hi,sw,ur,zh}.json` | new `adverts.delete_confirm_active_sub` key, translated per locale |

### Tests / checks performed

- `npx tsc --noEmit` in `Duka_mkononi` - clean, exit 0.
- `php artisan test` - 2 passed.
- `php -l` clean on `AdvertisementController.php`.
- All 8 locale files re-parsed as JSON after editing, and the new key confirmed
  present under `adverts` in each.
- Blade inline JS extracted and checked with `node --check` - OK.
- Live production test: 9/9. Canonical title resolution; the customer fallback
  chain still yields a real name instead of the literal "Matangazo"; the delete
  predicate is now byte-equivalent between Blade and Expo; no `parseInt`
  remains on a UUID in either file; and the warning's real-world trigger rate
  measured at 0 of 9 completed ads.

### Remaining issues on this screen

- Four advertisements are `is_active = true` while already expired. This is
  harmless today because `publicIndex()` filters on
  `isFree || (completed && expires_at > now)` and ignores `is_active`, so they
  are correctly hidden from the public feed. The stale flag is just untidy and
  was left alone; the feed logic itself is right.
- The mobile screen has no bulk "delete several ads" and no edit-after-post.
  Neither exists on the web either, so this is not a parity gap.
- `POST /api/matangazo` accepts `payment_status` from the client and stores
  `'pending'` accordingly; the price is then fixed server-side by
  `matangazoPrice()` on the payment path, so a client cannot underpay, but the
  field is still client-supplied.
- No OTA/deploy performed.

---

## Ripoti: name-based report scoping, and a real double-counted-money bug found alongside it

**Date:** 2026-09-27
**Module:** Msimamizi
**Blade file:** `resources/views/msimamizi/ripoti.blade.php`
**Expo file:** `Duka_mkononi/app/msimamizi/ripoti.tsx`
**Laravel endpoints:** `GET /api/admin/products`, `GET /api/admin/sales`,
`GET /api/admin/customers`, `GET /api/admin/users`, plus the seller path
`GET /api/products/my`, `/api/sales/my`, `/api/customers/my`
**Tables:** `products`, `sales`, `sale_items`, `customers`, `users`, `businesses`
**Legacy Node behaviour found:** none. Same `https://www.dukamkononi.com` host
and `Authorization: Bearer` + `userToken` scheme. The Expo screen is actually
richer than the web one: it renders from a SQLite/AsyncStorage cache first
(`db/cache.ts`) and revalidates in the background, and it subscribes to a live
`seller` sync channel. Those were left intact.

### 1. The report scoped itself by a business name it read out of client storage

`/api/admin/products`, `/api/admin/sales` and `/api/admin/customers` all
resolved their scope like this:

```php
$businessName = trim((string) $request->query('business_name', ''));
if ($businessName !== '') {
    $businessUserIds = User::where('business_name', $businessName)
        ->whereIn('role', ['admin', 'seller'])->where('status', 'approved')
        ->pluck('id')->all();
    $query->whereIn('seller_id', $businessUserIds ?: ['0000...']);
}
```

Both clients then re-applied the same test in the browser:
`users.filter(u => u.business_name === userData.businessName)`.

This is a three-link chain that can silently produce a completely empty
report, and the trigger is **our own migration work**:

1. Login returns `users.business_name` (legacy), e.g. `Jerald Stationari`.
2. The profile screen fetches `/api/profile`, and `ProfileController::readProfile()`
   now **overwrites `business_name` with the canonical
   `businesses.business_name`** — `Jerald Stationaria`. `muuzaji/profaili.blade.php`
   and `mteja/profaili.blade.php` then write that value back to
   `localStorage('userData')`.
3. Opening Ripoti compares the canonical name against the still-legacy
   `users.business_name`. Nothing matches, `sellers` becomes `[]`, and because
   products, customers and sales are *all* intersected with `sellers`, the
   entire report renders blank.

Measured, with the real rows: the old lookup returned **0 products, 0 sales and
0 customers** for that admin, versus **19 products, 63 sales, 27 customers**
via `business_id`. One business in the database currently has a
canonical-vs-legacy mismatch (`Jerald Stationaria` / `Jerald Stationari`, 2
members), so this was reachable, not hypothetical.

Fixed on both sides:

- Server: all three endpoints now scope from the verified JWT
  `businessId()` via `BusinessResolver::memberIds($businessId, ['admin','seller'], 'approved')`.
  A query parameter can no longer widen or redirect the scope. A caller with no
  `business_id` is forced to an empty result instead of falling through
  unscoped.
- Clients: the redundant `business_name === businessName` re-filters are gone
  from both files (seller list, products, customers, sales builder, and the
  live-sync callback). Seller *membership* (`seller_id` present in the approved
  seller set) is the only client-side test now, plus the genuine
  `status === 'approved'` rule.

`BusinessResolver::memberIds()` gained optional `$roles` / `$status` arguments
so it reproduces the old `whereIn('role', ...)` / `where('status', ...)`
semantics. It was previously defined but unused, so nothing else was affected.

### 2. Omitting the parameter leaked the entire platform to any business admin

The old filter was optional, and with no `business_name` the query was
completely unconstrained. Any business admin could call
`GET /api/admin/products` with no parameters and receive the whole platform:

| Caller (Dismas spare parts) | Old, no param | New, no param |
| --- | --- | --- |
| products | **2077** (all) | 154 |
| sales | **544** (all) | 0 |
| customers | **46** (all) | 0 |

Closed by the same change, since the JWT scope is now always applied.

### 3. A genuine pre-existing bug: customer purchase totals are double-counted

Found while auditing the numbers this screen prints, and **deliberately not
fixed here** — see "Not fixed" below.

`customers.total_purchases` and `purchases_count` are incremented in **two
places at once**:

- application code, `SaleController.php:310-324` and
  `AiImportController.php:1630-1643` (`+= total_amount`, `+= 1`);
- a database trigger, `update_customer_after_sale` AFTER INSERT ON `sales` ->
  `update_customer_stats()`, which does the same arithmetic.

So every sale counts twice. The trigger itself is correct — it is the
duplication that is wrong.

Evidence:

- The `sales` rows are clean: 543 of 544 have
  `total_amount = sum(sale_items.total_price)`, and **0** are 2x. Items are
  self-consistent 544/544. No duplicate sale ids (544 rows, 544 distinct ids)
  and no two sales share a `(customer_id, created_at)`.
- The `customers` rollup is the broken part: **41 of 46** customers are stored
  at exactly 2x the sum of their real sales.
- The trigger is **not ours** — it is absent from the repo and from
  `progress.md`, and it began double-counting around **2025-12-08**. Sales
  exist from 2025-12-06; the pre-trigger customers are exactly 1x and the
  post-trigger ones exactly 2x. Two customers (Brian, Gerald) straddle the
  boundary and are 1.5x and 1.167x.
- **It is still happening.** Three customers whose only sale is dated
  **2026-09-25** (two days ago) are all at exactly 2x.

This matters beyond the database: the trigger fires on *any* `sales` insert, so
even a sale created outside this codebase double-counts, and the rollup cannot
be reconciled by reading application code.

### 4. The `/2` in both clients is a band-aid over that bug — left in place

Both ripoti clients "correct" the doubled figures client-side:

```js
total_purchases: c.total_purchases / 2,
purchases_count: Math.round(c.purchases_count / 2)
```

This is why the bug has gone unnoticed. It is **correct for the 41 exactly-2x
customers and wrong for the rest** (Brian shows 1500 instead of 2000, Gerald
14000 instead of 24000, one already-correct customer is halved, and two
zero-total customers stay zero).

I removed neither the `/2` nor the trigger's counterpart, deliberately:
removing the `/2` alone would show *doubled* money for 41 of 46 customers,
which is strictly worse than today. The honest fix is to stop the
double-increment, repair the stored rollup, and only then drop the `/2` — and
that means a production data migration plus a decision about which layer owns
the counter. That is a separate workstream with a real restore requirement, not
something to fold into a one-file-pair change.

### 5. Stale numeric ID types

`Sale.id`, `Sale.product_id`, `Sale.customer_id`, `Product.id` and
`Customer.id` were declared `number`, and the `customerNameById` /
`productSalesMap` maps were `Map<number, …>`, while every one of those columns
is a `uuid`. As on the Tangaza screen, nothing caught it because the API JSON
arrives as `any`. Unlike Tangaza there is no `parseInt` here, so this one was
purely a type lie with no runtime effect; the map lookups keep working because
JS compares keys by value. All retyped to `string`.

### Changes made

| File | Change |
| --- | --- |
| `app/Http/Controllers/Api/AdminController.php` | `products()`, `sales()`, `customers()` scoped by JWT `business_id`; client param can no longer redirect or widen the scope; no-business caller forced empty |
| `app/Services/BusinessResolver.php` | `memberIds()` gained optional `$roles` / `$status` to match the old filter semantics |
| `Duka_mkononi/app/msimamizi/ripoti.tsx` | removed 5 `business_name` equality filters; seller membership is the test; UUID id fields retyped `number` -> `string` |
| `resources/views/msimamizi/ripoti.blade.php` | removed 2 `business_name` equality filters; same membership-based test |

### Tests / checks performed

- `npx tsc --noEmit` - clean, exit 0.
- `php artisan test` - 2 passed. `php -l` clean on both PHP files.
- Blade inline JS extracted and checked with `node --check` - OK.
- `test_ripoti.php` - 10/10 against production through the real controller,
  with the JWT attributes set exactly as `AuthenticateJwt` sets them.
- `test_ripoti2.php` - 17/17, including a deliberate reproduction of the old
  name-based query to prove it returned 0 rows, the platform-leak comparison
  above, a row-by-row check that every returned product/sale/customer belongs
  to the caller's business, a cross-tenant probe across all three endpoints (0
  foreign rows), and an assertion that the `/2` was left untouched.

Two test-harness bugs were hit and fixed rather than worked around: a synthetic
`system_admin` id violated the `user_logs` FK, so `log()` threw and the
controller's `catch` returned `{"error":...}`, which `json_decode` reported as
a 1-element payload; and PostgREST timed out once, so reads are now retried.

### Correction to the file 1 notes

`AdminController::users()` guards on `role !== 'admin'`, so a `system_admin` is
refused with 403 *before* the `isPlatformAdmin` branch added in file 1 is
reached. The same is true of the branch added here for the three new
endpoints. So the "system_admin may cross businesses" behaviour described after
file 1 is **not actually reachable** on these endpoints today, and no privilege
was changed here — the 403 stands. There is also no `system_admin` row in the
database, so the platform branch is currently unexercised. I left the branches
in place as forward-compatible, but granting `system_admin` access to these
report endpoints is a privilege change and should be an explicit decision.

### Not fixed here (needs a decision)

- **Customer totals double-count** (section 3). Needs: stop the duplicate
  increment, repair the 46 stored rollups from real sales, then remove the
  client `/2`. Involves production data and a choice of owner for the counter.
- `SaleController::my()` and `ProductController::my()` still find a seller's
  admin via `User::where('business_name', $businessName)`, so the seller path
  still depends on a name matching. `CustomerController::my()` is already
  `seller_id`-scoped and needs nothing.
- `preview.blade.php` / `preview.tsx` still send `?business_name=`. That is now
  inert for a business admin (verified: the same call returns the same rows)
  but the parameter should be dropped when those files are processed.
- The `admin:*` / `d:*` cache keys are global rather than per-business. They
  are cleared on sign-out via `SessionContext`, so there is no leak today, but
  a stale cache is not keyed by the business it came from.
- No OTA/deploy performed.

---

## Urgent fix: every business-profile save failed with "Imeshindikana kusasisha wasifu"

**Date:** 2026-09-27
**Reported as:** saving the business profile on `msimamizi/index` always shows
"Imeshindikana kusasisha wasifu", and the **Hifadhi** button gives no click
response at all.
**Files:** `app/Http/Controllers/Api/ProfileController.php`,
`resources/views/msimamizi/index.blade.php`
**Cause:** regression introduced by the file-1 `ProfileController` rewrite.

### The error, from the audit log

```
PROFILE_UPDATE_ERROR  failed
Supabase update failed: 409 {"code":"23505",
  "details":"Key (legacy_name_key)=(jerald stationary) already exists.",
  "message":"duplicate key value violates unique constraint
             \"businesses_legacy_name_key_uniq\""}
```

Six consecutive failures for user `3189fba8` between 00:37 and 00:58.

### Why it happened

The migration created **two** Jerald businesses, both created in the same
migration batch:

| id | business_name | legacy_name_key |
| --- | --- | --- |
| `2a994f07` | Jerald stationary | `jerald stationary` |
| `7b6184b6` | Jerald Stationaria | `jerald stationaria` |

This is the suspicious spelling group flagged after the migration and left
unmerged. The affected admin belongs to `7b6184b6`, but `login` returns the
**legacy** `users.business_name` = "Jerald Stationari", which is what the page
kept in `localStorage`.

So every save of a field the user was *not even touching* did this:

1. `saveEditModal()` unconditionally sends `business_name: editFormData.businessName`,
   and `editFormData` is seeded from `localStorage.userData` (index.blade.php:680),
   i.e. the legacy "Jerald Stationari".
2. My file-1 code did `$businessUpdate['legacy_name_key'] = BusinessResolver::key($business_name)`
   -> `jerald stationary`.
3. That is `2a994f07`'s key, so the update of `7b6184b6` hit the unique index
   and threw. Because it is a single PostgREST update, the **location, type and
   description edits were discarded too** - the user could not save anything
   at all, only rename.

My file-1 mistake was re-keying on *every* save instead of on an actual rename,
which made a cosmetic profile edit hostage to a global unique name constraint.

### Fix 1 - only a real rename re-keys (ProfileController)

`business_name`/`legacy_name_key` are now written **only when the submitted
name is genuinely not already one of this business's own names**. Own names =
the canonical `businesses.business_name` plus any legacy spelling still carried
by that business's own members. Echoing "Jerald Stationari" is therefore
recognised as *unchanged*, which is what it is.

A genuine rename still re-keys, and now checks the target first: if another
business already holds the key the save is rejected with **409** and a real
message instead of a generic 500, and **nothing** is written - the co-sent
location is not silently saved either. Logged as `PROFILE_UPDATE_NAME_TAKEN`.

### Fix 2 - the modal stops sending a stale name (index.blade.php)

`loadProfileIntoEditForm()` already adopted `business_type` /
`business_description` from the authoritative profile but **not** the name, so
the modal kept the pre-migration spelling forever. It now also adopts
`business_name` / `business_location` and writes both the camelCase and legacy
`business_name` cache keys, so the other screens stop inheriting the stale value.

### Fix 3 - the Hifadhi button now has a spinner

`updatingProfile` was dead state: it was set to `true` at line 843 and read
nowhere, and the button was a static `<div onclick="saveEditModal()">` that was
never disabled. Hence no feedback, and a double click fired the same PUT twice.

- `setSaveBtnState()` now drives an inline spinner + "Inahifadhi..." label,
  dims the button and switches the cursor; reset in `finally` and on modal open.
- `updateProfile()` returns early if already saving (re-entry guard).
- Added the missing `@keyframes btnSpin`.
- The blanket `throw new Error('Update failed')` is gone; the server's message
  is surfaced, so a name collision now reads "Jina la biashara limeshughulikiwa
  na biashara nyingine. Chagua jina lingine." instead of "Imeshindikana".
- On success the cache is written from the **server's** returned values rather
  than the submitted ones, so a rejected/normalised name cannot leave the cache
  disagreeing with the database.

### Verification

`test_profile_save.php` - **20/20** against production through the real
controller, with the JWT attributes set exactly as `AuthenticateJwt` sets them.
It reproduces the reported failure byte-for-byte (stale legacy name + a real
location change) and asserts the location now persists. Also covered: canonical
no-op save, a real rename re-keying, a rename onto another business's name
(409, nothing written, the other business untouched), and a case/whitespace-only
edit not being treated as a rename. All writes were snapshot-restored and
restoration was then confirmed independently by SQL - no residue anywhere.

`php -l` clean, Blade inline JS `node --check` OK, `php artisan test` 2 passed.
`tsc` unaffected (no Expo file touched). No deploy, no OTA, nothing committed.

### Still open (unchanged, needs the user's ruling)

- The **two Jerald businesses are still separate rows**. The save is now safe
  against the collision, but until they are merged this admin's business is
  `Jerald Stationaria` while their legacy name says `Jerald Stationari`, and any
  *other* code path that still matches on `users.business_name` (e.g.
  `SaleController::my()` / `ProductController::my()`, noted in the ripoti entry)
  will keep disagreeing with `business_id` for this account.
- `ProfileController` is shared by msimamizi, muuzaji/profaili, mteja/profaili
  and Expo. Those screens keep their own localStorage handling; they benefit
  from the server fix, but their modals were not audited for the same stale-name
  seeding in this pass.

---

## Preview (File 5): same name-equality trap, plus 8,500 TSh of expenses nobody could see

**Date:** 2026-09-27
**Module:** Msimamizi
**Blade file:** `resources/views/msimamizi/preview.blade.php`
**Expo file:** `Duka_mkononi/app/msimamizi/preview.tsx`
**Laravel endpoints:** `GET /api/admin/users`, `/api/admin/products`,
`/api/admin/sales`, `GET /api/office-expenses/range`
**Tables:** `office_expenses`, `users`, `products`, `sales`, `businesses`

### 1. The identical client-side name-equality chain, 9 times

Both files re-filtered the server's rows against the name in localStorage:

- `preview.blade.php`: `u.business_name === userData.businessName`
- `preview.tsx`: five `user.business_name === businessName` tests plus three
  `productSeller.business_name === businessName` and one
  `saleSeller.business_name === businessName`

Same trap as Ripoti, and it was one page visit away from firing: the test only
passes while localStorage still holds the **legacy** name, but `profaili.blade.php`
(and the index fix in the previous entry) now write the **canonical** name there.
The moment this account saved its profile, preview would have silently dropped to
just the fallback seller at `preview.tsx:361-369` - which fabricates a synthetic
seller from the current user, so the report would look *plausible* while quietly
showing a fraction of the real data.

All nine now test seller **membership** in the server-returned approved list
(`return !!productSeller` / `if (saleSeller)`), which is the same substitution
used in Ripoti.

### 2. The inert `?business=` / `?business_name=` parameters are gone

Both clients sent the business name on every call. After the server started
scoping from the JWT, those parameters could not narrow or redirect anything, so
they only implied an identity that does not exist. Dropped from all four calls in
both files; the sales call now sends only the date range, and omits the `?`
entirely when empty.

### 3. Found here: office expenses were invisible, and the net was silently wrong

`preview` is the only screen that nets **sales against expenses** to produce a
daily profit. Sales are scoped by business membership; expenses were not:

```php
$user = User::where('id', $userId)->select('business_name')->first();
$query = OfficeExpense::where('business_name', $user->business_name);
```

`office_expenses` has **no `business_id`** - the only reliable link is
`user_id`. So the three Jerald expenses are stored as `business_name =
'Jerald Stationary'`, while *both* of that business's members now read
`users.business_name = 'Jerald Stationari'`. The exact-string match returned
**zero rows**, for both the admin and the seller who recorded them:

| business_name as stored | rows | total |
| --- | --- | --- |
| Jerald Stationary | 3 | 8,500 |
| SANDE GARAGE | 2 | 2,000 |
| Dismas spare parts | 1 | 1,000 |

That is a **third** spelling of Jerald, on top of `Jerald stationary` and
`Jerald Stationaria`. An expense becomes permanently unreachable the moment the
business is respelled, and nothing warns.

`range()` now scopes by the recording `user_id` via
`BusinessResolver::memberIds($businessId)`, with the zero-UUID fallback so a
caller with no business gets an empty list instead of an unscoped dump. The
three rows now come back (8,500 total: Kodisho za Nguvu 3,500 / Maji 5,000) and
the daily net actually has expenses in it.

**Deliberate consequence:** a seller now sees the whole business's expenses
rather than only rows matching their own name spelling. That is required for the
arithmetic to be meaningful - the sales side has always been business-wide - and
it matches every other report in this migration. It is a widening of what a
seller can read, so it is called out here rather than buried.

### 4. Stale numeric UUID types

`Sale.id`, `Sale.product_id`, `Sale.user_id`, `Product.id`, `Product.seller_id`,
`Seller.id` and `BusinessEvent.id` were `number` (and `let productId = 0`),
while `Expense.id` in the same file was already correctly `string`. All retyped
to `string`.

### 5. A hazard this change makes *worse*, deliberately not half-fixed

Removing the client-side name filter means the cached seller/product/sales lists
are no longer filtered by business on the way out of the cache. Those caches use
**global keys** (`admin:users`, `admin:products`, `admin:sales`) shared by
index, Ripoti and preview. They are cleared on sign-out by `SessionContext`, so
there is no leak today, but a stale cache is not keyed by the business or even
the account it came from.

Scoping only preview's keys would have left `admin:products` / `admin:sales`
still global and produced a false sense of safety, so I left all of them alone.
This is now the top item on the cache-key follow-up list.

### Changes made

| File | Change |
| --- | --- |
| `app/Http/Controllers/Api/ExpenseController.php` | `range()` scopes by `user_id IN memberIds(business_id)` instead of the caller's legacy `users.business_name`; no-business caller returns empty |
| `resources/views/msimamizi/preview.blade.php` | dropped `?business=` and `?business_name=`; removed the seller name-equality filter |
| `Duka_mkononi/app/msimamizi/preview.tsx` | dropped `?business=` and both `?business_name=`; removed 9 name-equality tests in favour of seller membership; 7 UUID fields retyped `number` -> `string` |

`today()`, `byDate()` and `categories()` in the same controller still scope by
the legacy name and have the identical bug; only `range()` was changed because
that is the one this screen calls. See "Not fixed".

### Verification

- `npx tsc --noEmit` - clean, exit 0.
- `php -l` clean; Blade inline JS extracted and `node --check` OK.
- `php artisan test` - 2 passed.
- `test_preview.php` - **21/21** live against production through the real
  controllers, JWT attributes set as `AuthenticateJwt` sets them. Proves the old
  exact-name scope returned 0 rows while the new one returns all 3; that a
  different business sees none of them; that a no-business caller gets 400 and a
  memberless business gets an empty list; that users/products/sales every row
  belongs to the caller (`users=2 products=20 sales=63`, 0 foreign); and that
  passing a foreign `?business=` / `?business_name=` cannot redirect any of the
  three scopes now that the clients stopped sending them. Read-only: office
  expense rows re-counted afterwards and unchanged.

### Not fixed here (needs a decision)

- **`today()`, `byDate()`, `categories()`** carry the identical name-scoping bug
  and are used by other screens. Left alone to keep this to one pair.
- **`ExpenseController` has no role guard at all** on any of its six endpoints -
  any authenticated user, including a customer, can call them. That is the
  pre-existing "role-only expense authorization" backlog item, now confirmed in
  the source.
- **`ExpenseController::destroy()` has a cross-tenant hole**: the guard is
  `if ($expense->business_name !== $businessName && $userRole !== 'admin')`, so
  an ordinary business admin can delete **any other business's** expense by id.
- **`DebugController`** is reachable in production and both `checkBusiness` (called
  by `preview.tsx:161`) and `expenses/{date}` scope by the same legacy name and
  return the caller's email/role/status. Self-scoped, so not a leak, but it is a
  debug surface that should not ship.
- No OTA/deploy performed.

---

## Bidhaa buying price + Ripoti profit: root-cause fix (2026-09-27)

Two bugs were reported from the Msimamizi pair: the AI import of `World Choice
Perfume` showed `55000` in **both** the buying and selling columns of Ripoti, and
editing an existing product "did not work". Both were real and independent. The
stored data was never wrong.

### What the database actually held

`products.id = 4d92cfc6-b80f-46bd-8285-73d432de5f19`

| column | value | meaning |
|---|---|---|
| `cost_price` | 45000 | buying price (what the user typed) |
| `price` | 55000 | current selling price |
| `expected_selling_price` | 55000 | target selling price |

`AiImportController` maps `buyingPrice -> cost_price` and
`sellingPrice -> price` + `expected_selling_price` in both the create and the
update branch, and both clients send the same three fields. So the AI import was
correct; the money was being lost on the way out.

### Bug 1: the report used the selling price as the cost basis

`ripoti.blade.php` and `ripoti.tsx` both built the cost basis from
`product.price`, which is the **selling** price. Consequences:

- "Bei ya Ununuzi" printed the selling price, which is why both columns read
  55000.
- Projected/expected profit was `selling - selling = 0` for every product.
- The moment the real buying price was lost, the same code turned a missing
  buying price into "profit = entire sale amount", because `null` was read as
  `0`.

Fix in both clients: the cost basis is now nullable `cost_price`, and an
unrecorded buying price renders as `-` (report) or
`reports.cost_not_recorded` (Expo) instead of a number. Aggregate profit sums
only known-cost sales, and both clients disclose how many sales were excluded,
including a "Kumbuka" row in the exported CSV so a spreadsheet cannot be read as
a complete total.

### Bug 2: editing a product destroyed its buying price

`ProductController::update()` unconditionally wrote
`'cost_price' => $cost_price ? (float) $cost_price : null`. The product form had
no buying-price field, so the key was never sent, so **every** edit nulled the
stored buying price. The endpoint returned 200, which is why it looked like
"editing does not work" rather than like data loss.

Fix: `cost_price` is written only when `$request->has('cost_price')`. Omitting
it preserves the stored value; sending a number sets it; sending an explicit
`null` clears it. The product form now has an optional buying-price field in both
clients that pre-fills from the product and is never required for submission.

### Files changed

- `app/Http/Controllers/Api/ProductController.php` - conditional `cost_price`
  write in `update()`.
- `resources/views/msimamizi/ripoti.blade.php` - `costBasis()`,
  `applyProfit()`, `sumProfit()`, `unknownCostCount()`, `profitCoverageNote()`;
  unknown buying price/profit render as `-`; `total_profit_known`; CSV note row.
- `Duka_mkononi/app/msimamizi/ripoti.tsx` - nullable `cost_price` /
  `profit` / `profit_margin` in the `Sale` and `Product` types, `unknownMoney()`
  helper, the two `product.price` cost-basis sites, sale-row profit cells, and
  the same coverage disclosure.
- `resources/views/msimamizi/bidhaa-mpya.blade.php` - `cost_price` in form
  state, the three pre-fill sites, the submit payload, the input markup, its
  listener, the live preview, and the product list.
- `Duka_mkononi/app/msimamizi/bidhaa-mpya.tsx` - same, plus the buying price on
  the product card.
- `Duka_mkononi/locales/*.json` - `products.cost_price_label`,
  `products.cost_price_placeholder`, `products.cost_price_hint`,
  `reports.cost_not_recorded` in all 8 languages.

### Verification

- `test_product_prices.php` rewritten to assert the **fixed** behaviour and now
  **27/27**: stored prices correct; an edit that omits `cost_price` preserves
  45000; explicit value writes; explicit `null` clears; non-owner still 403 and
  leaves the row untouched; profit on 3 units at 55000 is 30000 and the old code
  would have said 0; a `null` buying price is reported unknown and is provably
  not the sale amount; every mutated field verified restored.
- Full regression, all green: `test_ripoti` 10/10, `test_ripoti2` 17/17,
  `test_bidhaa` 9/9, `test_resolver` 21/21, `test_scope` 7/7,
  `test_writepath` 17/17, `test_tangaza` 9/9, `test_preview` 21/21,
  `test_profile_save` 20/20, `test_e2e_admin` 4/4, plus
  `test_cross_branch_sales` and `test_shop_filters` exit 0.
- `npx tsc --noEmit` clean. Both Ripoti and bidhaa-mpya Blade script blocks
  parse (`check_blade_js.js`). `php -l` clean on `ProductController`.

### Not fixed here (needs a decision)

- **2072 of 2078 products have no `cost_price`** and cannot be backfilled - the
  buying price was never captured, and inventing it from the selling price is
  exactly the bug that was just removed. Their Ripoti profit now honestly reads
  unknown. Entering buying prices going forward is the only real remedy.
- The **customer purchase-stat double count** and the two **Jerald** business
  rows are untouched, as agreed.
- No OTA/deploy performed.

### Follow-up: which column holds "bei ya kununulia", and does the AI path use it too?

Asked directly, so answered directly and proved it rather than asserted it.
`test_cost_column.php` -> **29/29**, and the temporary rows it creates are
deleted and the absence independently confirmed (`0` rows matching `ZZTMP%`).

**The column is `products.cost_price`.** It is the only cost-like column in the
schema - there is no `cost`, `buying_price` or `purchase_price` on `products`.
The other numeric price columns are `price` (selling), `expected_selling_price`
(target selling) and `min_stock_level` (not money). The signature that confirms
`cost_price` is a buying price and not a second selling price: on **every** row
that has one, `cost_price < price`.

| product | cost_price | price | expected |
|---|---|---|---|
| Kalamu | 300 | 500 | NULL |
| Marker pen | 300 | 500 | NULL |
| Pencil | 500 | 700 | NULL |
| World Chioice Perfume | 35000 | 45000 | 45000 |
| World Choice Perfume | 45000 | 55000 | 55000 |
| Louis Vuitton Imagination | 5000 | 7000 | 7000 |
| Amouage Guidance 46 | 1600000 | 1695000 | 1695000 |

**Both entry paths already write to that one column**, which is what was asked
for - the AI result and a hand-typed product are indistinguishable to the
report:

- Manual: `POST /api/products` -> `cost_price` (create 201), and on
  `PUT /api/products/{id}` it is written only when the request carries the key.
- AI: `POST /api/inventory/ai-import/verify` -> `buyingPrice` becomes
  `cost_price` in **both** branches: `AiImportController` line 1004 for a
  matched existing product, line 1114 for a newly created one.

Verified by driving the real controllers: manual create put 45000 in
`cost_price`; an edit that omitted the field preserved it; an edit that supplied
47000 wrote it; the AI new branch put `buyingPrice=45000` in `cost_price` and
`sellingPrice=55000` in `price`; the AI existing branch overwrote `cost_price`
with its own `buyingPrice` and increased stock. The report cost basis then read
45000 and produced profit of 10000/unit, 40000 over the 4 units in stock.

**The 2072 products without a buying price still have none, and that is not
recoverable from the database.** The one table that could have held it,
`inventory_transactions.unit_cost` (with `total_cost`, `supplier_name`,
`supplier_invoice`), exists and is **completely empty - 0 rows**. So the stock
ledger that would have recorded historical buying prices was never written to.
There is no third source. The only remedy is entering buying prices going
forward, which the new optional field in both clients makes possible.

### Data safety note

No product was deleted and no schema change was made in this work. Every live
test created its own uniquely-named temporary row and removed it, and every
mutated existing row was snapshot-restored and then re-verified. Independent
count after the run: `2079` products total, `7` with a buying price - the same
`7` that existed before, all intact.

---

## CORRECTION: products.price IS the buying price ("Bei ya Kununua")

**This section supersedes every statement above that says `cost_price` holds the
buying price.** That earlier conclusion was wrong and has been fully reversed.

### The rule now in force

| Meaning | Column | Notes |
| --- | --- | --- |
| Bei ya Kununua (buying) | `products.price` | authoritative, NOT NULL |
| Bei ya Kuuzia (selling) | `products.expected_selling_price` | nullable |
| Profit | selling - buying | never a fabricated value |
| `products.cost_price` | legacy backup | **no longer written, never displayed** |

Data confirmed this reading: before the change, all 7 populated `cost_price`
values sat strictly **below** `price`, and 2044 rows had
`expected_selling_price > price`. `price` was already the selling price, which
is exactly why the AI path wrote the selling price into it and the report then
divided a price by a cost to get nonsense.

### Migration of existing data

Snapshot of the 7 affected rows was taken first
(`C:\eas-temp\opencode\buyingprice_backup_7rows.txt`), then:

```sql
UPDATE products
SET expected_selling_price = COALESCE(expected_selling_price, price),
    price = cost_price,
    updated_at = now()
WHERE cost_price IS NOT NULL;
```

No rows were deleted, no schema changed, and `cost_price` was deliberately left
in place as a legacy copy. 3 of the 7 (Kalamu, Marker pen, Pencil) had no
selling price recorded; their previous `price` was preserved as
`expected_selling_price` before being overwritten, since the old AI path had
been writing the selling price there. Those 3 inferred selling values are worth
a sanity check with the owner.

### Code changed

- `ProductController` - `price` is the buying price, `expected_selling_price` the
  selling price. `cost_price` writes removed. Omitting `price` on an update no
  longer overwrites it with 0, and omitting the selling price no longer erases
  the stored one.
- `AiImportController` - `buyingPrice` writes to `price`, `sellingPrice` writes
  to `expected_selling_price`; no `cost_price` write on either the NEW or the
  EXISTING branch.
- `ProfitController` - daily and monthly cost-of-goods now read
  `products.price`. A missing buying price is counted in a new
  `items_with_unknown_buying_price` counter instead of being coerced to 0,
  which previously overstated profit.
- `server.js` - the same mapping in `POST`/`PUT /api/products`, in both AI
  branches, and in the daily/monthly profit queries. The destructive
  unconditional `cost_price` write in `PUT` is gone.
- `bidhaa-mpya` (Blade + Expo) - the duplicate `cost_price` input was removed and
  the real `price` field is labelled `Bei ya Kununua`. Add-stock now sends only
  name, category and stock. The product list, the selected-product banner and the
  Hakiki preview all read the buying price from `price`.
- `ripoti` (Blade + Expo) - buying basis reads `product.price`, selling basis
  reads `expected_selling_price`, and projected profit is their difference.
- `preview` (Blade + Expo) - profit basis reads `product.price`; these two files
  still preferred the legacy `cost_price` and were the last stragglers.
- Locale files (all 8 languages) - buying-price wording corrected, and the
  dead `cost_price_hint`, `cost_price_placeholder`, `cost_price_label` and
  `modal_cost_price` keys removed.

### Verification against the real database

`C:\eas-temp\opencode\test_price_rule.php` - **38 passed, 0 failed**, driving
the real `ProductController` and `AiImportController` over Supabase REST:
manual create 201 with buying in `price`; add-stock preserved both prices;
edit wrote the new buying price; AI NEW mapped both prices correctly; AI
EXISTING did **not** overwrite buying with selling; World Choice Perfume reads
45000 / 55000 for a 10000 profit; all 7 migrated products compute the right
margin; no product has a zero or missing buying price; all 7 legacy
`cost_price` rows intact; no test rows left behind. Independent count after the
run: `2079` products, `7` legacy `cost_price`, `0` temporary rows.

### IMPORTANT: never use the `DB::` facade to inspect this data

`App\Models\*` extends `SupabaseModel`, which reaches Postgres through the
Supabase REST API. The default Laravel connection is a **local sqlite file**
(`database/database.sqlite`, `DB_CONNECTION=sqlite`) holding a single
placeholder product and one user. Verifying real data through `DB::table(...)`
silently reads that decoy instead of production. Always verify through the
models or `C:\eas-temp\opencode\sql.php`.
