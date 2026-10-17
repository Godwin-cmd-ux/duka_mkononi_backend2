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
  - `royal-champagne-cream` — the alternate champagne/gold theme
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
  the Royal attribute selector, so the default theme renders byte-identically.
- Preference storage, login sync, `PUT /api/user/theme`, the no-flash early
  restore and the 8-language labels are shared with the public pages — one
  mechanism, one localStorage key, one server column.