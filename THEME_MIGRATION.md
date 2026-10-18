# Theme preference migration (Phase 1 — theme system)

This file exists so the theme-preference migration is easy to find at the
project root. The executable migration itself lives in the standard Laravel
migration directory, as Laravel expects — there is deliberately **no**
migration file in the project root.

## Where is the migration?

```
database/migrations/2026_10_17_000001_add_preferred_theme_to_users_table.php
```

## What does it do?

- Adds one column, `preferred_theme`, to the existing `users` table.
- Allowed values (validated server-side as well, in
  `app/Services/ThemePreference.php`):
  - `white-professional` — the existing Duka Mkononi design (**default**)
  - `royal-champagne-cream` — the champagne/gold theme
  - `midnight-royal-gold` — the dark charcoal/gold theme
  - `emerald-bazaar` — the mist-green / deep-emerald daylight theme
  - `oceanic-sapphire` — the ocean-navy / sapphire / icy-blue daylight theme
    (public website)
- Defaults **new** users to `white-professional`.
- Backfills existing users (rows left NULL, or holding a value outside the
  allow-list, are set to `white-professional`). No other column is modified.
- `down()` removes the column cleanly (`dropColumn`).

## How do I run it?

Standard Laravel migration process, from the project root:

```bash
php artisan migrate
```

Useful variants:

```bash
php artisan migrate:status        # list migrations and their status
php artisan migrate:rollback      # undo this migration (drops the column)
```

**Do not run the migration against production without explicit
authorization.** It has **not** been executed by the Phase 1 implementation —
it is prepared only. If `php artisan migrate` has not been run yet, the
backend degrades gracefully: login still succeeds, theme synchronization is
simply skipped and the browser keeps its local preference.

## Supabase (the live database)

The API does not use this Laravel connection for user data: `App\Models\User`
reads and writes the Supabase `users` table over PostgREST, so the programmatic
migration above only covers a local/SQLite install. For Supabase, run the SQL
equivalent instead:

```
supabase/migrations/20261017000001_add_preferred_theme.sql
```

It adds the same `preferred_theme` column to `public.users` with the same
default and the same approved values (also enforced by a CHECK constraint),
backfills existing rows, and reloads the PostgREST schema cache so
`PUT /api/user/theme` and login can see the column immediately. Paste it into
the Supabase SQL Editor or apply it with `supabase db push`. It is idempotent
and touches no other column, table, policy or grant.

```
supabase/migrations/20261017000002_add_emerald_bazaar_theme.sql
```

Widens that CHECK constraint to admit `emerald-bazaar`. Run it **after**
000001 (on a fresh project `supabase db push` orders them automatically). It
is guarded, so it is harmless if the column does not exist yet, and it is
idempotent: it drops and re-adds only `users_preferred_theme_check`. If you
applied 000001 through the SQL Editor, it is not recorded in the CLI's history
table — apply 000002 the same way.

```
supabase/migrations/20261017000003_add_oceanic_sapphire_theme.sql
```

Brings the allow-list to five values, admitting `oceanic-sapphire`. It is
**self-sufficient**: if 000001 was never applied it creates
`preferred_theme` itself (text, default, backfill, NOT NULL), then replaces
`users_preferred_theme_check`; on a database that already has the column it is
a pure no-op apart from the constraint. Run it on its own or after 000001/
000002 — either order works, and 
`supabase db push` applies the files in name order anyway. Idempotent, and the
only row-level write is `preferred_theme` on rows holding no valid value.

## Related Phase 1 pieces

| Piece | File |
| --- | --- |
| Column allow-list + validation | `app/Services/ThemePreference.php` |
| Login synchronization rules | `app/Http/Controllers/Api/AuthController.php` (`login()`) |
| Authenticated sync endpoint | `PUT /api/user/theme` → `app/Http/Controllers/Api/ProfileController.php` (`theme()`) |
| Early (no-flash) restore | `resources/views/partials/dm-locale.blade.php` |
| Theme manager module | `public/js/dm-theme.js` (localStorage key `dukamkononi_theme`) |
| Centralized theme tokens | `public/css/themes.css` |
| Selector widget | `resources/views/partials/dm-theme-widget.blade.php` |
| Mobile palette + selector | `Duka_mkononi/lib/theme.tsx`, `Duka_mkononi/components/theme-selector.tsx` |
| Theme descriptions (8 languages) | `Duka_mkononi/locales/*.json` (`theme.emerald_desc`, `theme.oceanic_desc`) |
| Web theme descriptions (8 languages) | `locales.json` (`ui.theme_oceanic_desc`) |

## Phase 3 — Oceanic Sapphire (public website)

The fifth theme on the public website:

- The eleven public pages (`/`, `/shop`, `/home`, `/login`, `/forgot`,
  `/track-orders`, `/advertisements`, `/businesses`, `/admin-signup`,
  `/cashier-signup`, `/client-signup`) carry `class="dm-public"` on `<body>`.
- The public Oceanic rules and design tokens are scoped to
  `html[data-theme="oceanic-sapphire"] body.dm-public`, so they cannot move a
  pixel on the authenticated portals — those are themed by their own layer
  (Phase 5).
- The fifth card is shaped exactly like the other four: same class, size,
  radius, padding and type. Its description (`ui.theme_oceanic_desc` in all
  eight languages) is exposed as the button's `title` tooltip rather than as
  extra inside the card, so the row stays visually uniform, and its radio dot
  is filled with the theme's sapphire.

## Phase 2 — authenticated portal pages

The theme system now also covers the 28 standalone portal pages under
`resources/views/mteja/`, `muuzaji/`, `msimamizi/` and `system_admin/`:

- Each page links `/css/themes.css` and includes
  `@include('partials.dm-theme-widget', ['dmThemePlacement' => 'bottom'])`
  (fixed bottom-right; their left edge is owned by the fixed sidebar and
  they hide the floating language rail).
- Because those pages carry their own inline stylesheets with literal hexes,
  the Royal look is delivered by the **portal override section at the end of
  `public/css/themes.css`**: selector-level rules scoped to
  `html[data-theme="royal-champagne-cream"]` that re-skin the shared shell
  and component vocabulary (sidebar, headers, cards, tables, buttons, tabs,
  chips, modals, AI panels, entry hub) to the same Royal values used on the
  public pages. Section brand accents converge to royal gold; semantic
  status colors (`.b-*`, `.alert-*`, `.banner.*`, success/danger/warn,
  logout red, online-dot) keep their hue in **both** themes, and inline
  `style="…"` / JS-injected palette colors keep their existing hues by
  design.
- White Professional is untouched everywhere: every portal rule is scoped to
  a theme attribute selector, so the default theme renders byte-identically.
- The same override layer exists once per alternative palette. Emerald Bazaar
  reuses the Royal layer's selectors and translates every value through its
  own emerald map (mist surfaces, white cards, forest ink, emerald accents),
  so the portal coverage is identical across all approved themes.
- Preference storage, login sync, `PUT /api/user/theme`, the no-flash early
  restore and the 8-language labels are shared with the public pages — one
  mechanism, one localStorage key, one server column.

## Caching

Theme assets are versioned off their own file mtime, never by a hand-written
revision number: `partials/site-head.blade.php` and the eleven public pages
request `/css/themes.css?v={{ filemtime(...) }}`, and the widget requests
`/js/dm-theme.js?v={{ filemtime(...) }}`. A browser that cached an older
stylesheet (one whose allow-list did not know the new theme) therefore fetches
the current file on the next page load instead of keeping the card appearing
unselectable. Storage is equally strict: only identifiers in the allow-list are
written or restored, the preference survives navigation and reloads through
`localStorage["dukamkononi_theme"]`, and the inline no-flash snippet in
`partials/dm-locale.blade.php` applies it before first paint.

## Blade gotcha

`resources/views/partials/dm-theme-widget.blade.php` must not contain the
literal token `@verbatim` outside its two real blocks — not even inside a
`{{-- --}}` comment. Laravel pairs the first `@verbatim` with the first
`@endverbatim` textually, so a stray token in a comment consumes the real
opening style block, drops the widget markup, and leaves an orphan `@else`
that renders the page as a 500, or as a bare theme dialog with no theme
applied. In prose, write the word without the leading `@`.

## Phase 5 — Oceanic Sapphire on the portals and the mobile app

Oceanic Sapphire now reaches every authenticated surface as well:

- **Portals** (`resources/views/mteja|muuzaji|msimamizi|system_admin/*`,
  28 pages). `partials/dm-theme-widget.blade.php` renders the fifth card in
  both placements, so the bottom-right selector shows five themes. Because
  those pages keep their authored markup and their inline literal hexes, the
  palette arrives through the override layer at the end of
  `public/css/themes.css` (`PHASE 2 — portal coverage for Oceanic Sapphire`):
  shell, cards, tables, buttons, chips, modals, AI panels and entry hubs are
  re-skinned selector by selector, and companion rules recolour inline
  `style="…"` attributes and JS-injected colours. Semantic status colours keep
  their meaning (success green, danger red, warning amber, info sapphire).
- **Caching on the portals**: every portal page now requests
  `/css/themes.css?v={{ filemtime(...) }}` as well, so a visitor whose browser
  cached the four-theme stylesheet gets the fifth theme on the next load.
- **Mobile app** (`Duka_mkononi`): `lib/theme.tsx` declares the
  `oceanic-sapphire` id and palette (the same hexes as the website token
  block), and `components/theme-selector.tsx` adds the card with its real
  colour preview, its brand name and the `theme.oceanic_desc` key, which all
  eight locales define. Because both files type their maps as
  `Record<ThemeId, …>`, TypeScript fails the build if a new theme is missing
  from any of them.