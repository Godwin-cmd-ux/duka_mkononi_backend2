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
