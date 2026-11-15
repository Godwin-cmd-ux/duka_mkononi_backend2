# Dukamkononi — Scanner ON/OFF Control and Change Password

This document covers the two improvements requested: centralized scanner
availability control (already wired into the settings module) and Change
Password on the website and mobile app. No scanner workflow was rebuilt and no
database migration is required.

## 1. Scanner ON/OFF control

### 1.1 Where the setting lives

* Web: `resources/views/msimamizi/mipangilio.blade.php`, "Huduma za Hiari"
  section, toggle "Unataka kutumia kipengele cha Scanner?" with
  `data-feature="scanner_enabled"`.
* Backend: `app/Services/BusinessSettings.php` (`DEFAULTS['scanner_enabled'] =
  false`, `enabled('scanner', $businessId)`, `features()` returns
  `scanner`).
* Mobile: `Duka_mkononi/context/FeatureContext.tsx` reads `/api/settings/features`
  and exposes `isEnabled('scanner')`.

### 1.2 Behaviour

* ON: every `[data-feature="scanner"]` element is shown (web) and every
  `isEnabled('scanner')` gate is true (mobile); all scanner endpoints work.
* OFF: those elements are hidden, mobile hides scanner entry points, and every
  scanner endpoint returns `403 FEATURE_DISABLED`. Manual quantity entry,
  receiving, selling, transfers and order listing keep working.
* Admin-only: `SettingsController::updateFeatures` calls `isAdmin`, so sellers
  cannot change it. Business scoping follows `business_id` from the auth token;
  one business cannot read or change another's setting.
* Data is preserved: disabling only flips the flag; `product_items`,
  reservations, sessions and history stay intact and become usable again when
  re-enabled.

### 1.3 Enforcement points

* Web: `public/js/dm-features.js` applies `[data-feature="scanner"]`.
* Mobile: `FeatureContext` + `useFeatures()`.
* API: `ScannerController` (`requireFeature('scanner')`) and
  `ProductController` (scanner source gated).
* All scanner routes sit inside the `auth.jwt` group.

## 2. Change Password

### 2.1 Authentication architecture

Passwords live in the `users` table (`password` column, bcrypt) managed by
Laravel. Supabase is used for data only; authentication is Laravel's
`password_hash`/`password_verify`. One account password works on web and mobile.

### 2.2 Endpoint

`PUT /api/user/password` (`ProfileController::changePassword`), authenticated.
Accepts `current_password`/`currentPassword`, `new_password`/`newPassword`,
`confirm_password`/`confirmPassword`. Verifies the current password, rejects
reuse, enforces a 6-character minimum, bcrypt-hashes the new value. Passwords
are never logged or returned.

### 2.3 Website

* Admin: `resources/views/msimamizi/mipangilio.blade.php` → "Taarifa Zako"
  section has a separate "Badilisha Nenosiri" form (`#passwordForm`) posting to
  `/api/user/password`.
* Seller: `resources/views/muuzaji/profaili.blade.php` → password modal.
* Customer: `resources/views/mteja/profaili.blade.php` → password modal.

### 2.4 Mobile

* Admin: `Duka_mkononi/app/msimamizi/index.tsx` → password modal.
* Seller: `Duka_mkononi/app/muuzaji/profaili.tsx` → password modal.
* Customer: `Duka_mkononi/app/mteja/profaili.tsx` → password modal.

All call the same endpoint, so a change on one platform authenticates on the
other. Existing login, logout and password reset flows are untouched.

## 3. Testing

* Scanner toggle: verified by existing `QrScannerTest` ("scanner operations are
  refused when the feature is off", "a scanned sale is refused when the feature
  is off") plus `BusinessSettings` tests.
* Password: endpoint logic covered by manual verification (current-password
  check, mismatch, too-short, reuse rejection). No new migration.

## 4. Deployment

No migration required. Deploy Laravel + web assets + mobile build. Existing
settings rows and users keep working; scanner defaults to OFF for new businesses.
Rollback is a code revert; no data is lost.
