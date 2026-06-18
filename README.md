# Balance Testing Plugin Notes

**Client documentation (HTML with screenshots):**

- **Live guide (GitHub Pages):** https://mdhemalakanda.github.io/balance-testing/
- **Local copy:** [docs/user-guide.html](docs/user-guide.html)

Grouped administrator guide (Introduction, Balance tests, **Exercises** hub with all screenshots, Participants, Participant site, Rounds & emails, FAQ). Search, scrollable sidebar groups, mobile-friendly.

## Login Form Behavior

- Frontend login failures now redirect back to the same page where the login form was submitted.
- Empty credential submissions are also returned to the same frontend page with an inline error message.
- Forgot-password is handled directly on the frontend login page via an inline reset form, so users do not need to use `wp-login.php`.

### Wordfence 2FA on `[bt_account]`

When **Wordfence Login Security** 2FA is enabled, the custom login page (`[bt_account]` on Omatestaus, the front page, or any page with that shortcode — including Elementor layouts):

1. **Submits to the same page** (not `wp-login.php`), so users stay on parempitasapaino.fi during login and 2FA.
2. **Two-step login for administrators only:** subscribers / normal balance-testing users log in with **username + password** (no 2FA step). Users with `manage_options` (and super admins on multisite) use step 1 = password, step 2 = **2FA code only** (`?login=2fa_required&bt_token=…`, 10 min transient). In **Wordfence → Login Security**, still enable 2FA only for the **Administrator** role so settings match this behavior. Forgot-password is on a separate view (`?reset=form`).
3. **Live server:** exclude the homepage, `/omatestaus/`, and **`/bt-user-account/`** (all `?action=` tabs, including **`?action=progress-checkin&test_question_access_key=…`** from scheduled emails) from full-page cache (LiteSpeed, Cloudflare, etc.) so POST login, per-user test state, and progress-form styles are not cached. The plugin sets `DONOTCACHEPAGE`, `nocache_headers()`, and `X-LiteSpeed-Cache-Control: no-cache` on account / email-access URLs. Frontend CSS uses **filemtime** versions (`assets/css/style.css`) so deploys bust browser caches for the 1–10 number-button scales.
4. Shows Finnish messages for invalid 2FA (`?login=2fa_failed`) vs wrong password (`?login=failed`).
5. After successful login, redirects to `/bt-user-account/`.

**Fallback (no JavaScript):** users can enter password and 6-digit code together in the password field (Wordfence combined login), e.g. `MyPassword482901`.

**Admin setup:** Wordfence → Login Security → enable 2FA for roles; each user activates 2FA in their profile (authenticator app + recovery codes).

## Tests → Users Progress (admin React UI)

The **Tests → Users Progress** screen is fully React-driven (`DisplayUsers.jsx`, `UserDetails.jsx`). The legacy PHP progress table above the React mount point was removed; user detail (round rules, exercise assignments, progress summary, ratings) loads only inside `#display-user`.

Toolbar styles live in `frontend/test-user-management/src/App.css` (built to `dist/test-user-management.css`). Run `npm install && npm run build` in `frontend/test-user-management/` after admin UI CSS changes.

## Per-round test limits (admin)

- **Tests → Users Progress** → open a user → **Per-round test limits** panel.
- Per user and per round (1–3), admins can override:
  - **Max tests** — how many tests the user may complete in that round (defaults: **43** for rounds 1–2, **42** for round 3).
  - **3/4 threshold** — how many ratings of 3 or 4 stop further tests in that round (default: **6** for all rounds). Reaching the threshold ends the round early; completing **max tests** also ends the round.
- When a round ends (either path), the plugin **always** schedules the next-round invite email and optional auto-access — even if the user finished **below** the 3/4 threshold. A separate **low-rating admin alert** still fires when below threshold. See `NOTIFICATION_RULES.md`.
- Empty override fields use plugin defaults. **Clear overrides** removes all custom limits for that user.
- Overrides are stored in user meta `balance_testing_user_round_rules`.
- REST: `GET/POST /wp-json/balance-testing/v1/round_rules?user={id}` (requires `manage_options`).
- Runtime logic lives in `includes/RoundLimits.php`.
- Verify pilot defaults (requires Local site + DB running):  
  `"/path/to/local/php" wp-content/plugins/balance-testing/scripts/verify-round-limits.php`
- Verify priority pool ordering (round 2+ attempt order vs `post__in`):  
  `php wp-content/plugins/balance-testing/scripts/setup-priority-order-uat.php [user_id]`  
  `php wp-content/plugins/balance-testing/scripts/verify-priority-order.php [user_id]`  
- Full ordering audit (catalog + priority regression + full walk):  
  `php wp-content/plugins/balance-testing/scripts/verify-test-ordering.php`  
  Add `--fix-catalog` to swap `menu_order` for known 1B7/1B8 inversion.  
  CLI scripts auto-use the Local MySQL socket via `scripts/cli-bootstrap.php`.

### Rating carry-over between rounds

- **Ratings 3 and 4** — priority pool in the next round (re-test).
- **Rating 5** — carried to the next round (priority pool); no longer excluded like ratings 1–2.
- **Rating 6 (Mahdoton)** — permanently excluded from all following rounds.
- **Ratings 1–2** — excluded from later rounds (easy / very easy).

Exclude and priority SQL is in `Utils.php` (`prepare_sql_for_exclude_tests`, `get_priority_test_ids`).

## Symptom assessment questions (0–10)

On **Alkukysely** (`initial_assignment.php`) and **Edistymisen seuranta** (`progress.php`), the former eight balance/symptom items are replaced by two shared questions (partial: `templates/my-account/components/partials/symptom-assessment-questions.php`):

1. **I. Oireiden voimakkuus** — `oireiden_voimakkuus` (0 = ei oireita, 10 = voimakkain mahdollinen oire)
2. **II. Vaikutus toimintakykyyn** — `vaikutus_toimintakykyyn` (0 = ei haittaa, 10 = suurin mahdollinen haitta)

Both use numeric radio groups (0–10, left to right) inside `.bt-item-single .bt-radio-groups`. Styles in `assets/css/style.css` render each value as a **clickable number button** (selected value gets a blue border/background).

## Progress table — exercise answer labels

On **Edistymisen seuranta** (`progress.php`), the two exercise-frequency rows store numeric values (`exercise_days` 1–5, `exercise_frequency` 1–4) but display the full Finnish answer text (e.g. `3 - Noin joka toinen päivä`) via `Utils::get_exercise_days_label()` and `Utils::get_exercise_frequency_label()`.

## Test difficulty scale (1–6)

Each **test** post has an ACF select field **`test_scale`**. The test page shows the matching 6-step reference table and rating buttons (1–6).

| `test_scale` value | Scale |
|---|---|
| `balance` | Balance (Asteikko / Nimi / Kuvaus) |
| `eyes` | Eyes |
| `coordination` | Coordination |
| `strength` | Strength (Taso / Vaikeustaso / Kuvaus ja suoriutuminen) |
| `habituation` | Habituation |

Definitions live in `includes/TestScale.php`. The template `templates/my-account/components/content/test.php` reads `test_scale` from the current test and renders the table. Level **6 (Mahdoton)** uses bright red (`#e53935`) for the button and table row.

On viewports **≤767px**, the scale table switches to stacked card rows (level + name + description) at full width; the old `max-width: 210px` mobile constraint on `.bt-my-account-info-single` was removed so the table is no longer clipped.

Next-test ordering in `test.php` (via `Utils::resolve_next_test_query()`):

- **General pool** (Round 1 and after priority pool is exhausted): **`menu_order DESC`** / **`title DESC`** (admin catalog order).
- **Priority pool** (Round 2+): IDs from `get_priority_test_ids()` in **`ORDER BY MIN(attempt_id) ASC`**, then `WP_Query` with **`orderby => post__in`** so attempt order is preserved (not re-sorted by `menu_order`).

### Round-complete / navigation hardening (`test.php`)

- **`is_user_permitted_for_test()` runs before** the next-test `WP_Query` — completed rounds never load a test post.
- If the query returns a test the user **already rated in the current round**, that test is excluded and the next candidate is fetched (avoids duplicate-submit loops after progress ↔ balance-tests navigation).
- **`BalanceTest`**: duplicate rating shows *Olet jo tehnyt tämän testin tässä kierroksessa.*; submissions are ignored when the round is already complete.

If `test_scale` is empty or invalid, the **balance** scale is used as fallback.

## User account — Harjoitukset (exercises tab)

Sidebar order (after **Alkukysely** / when `test_round` is set):

1. **Tasapainotestit** — `?action=balance-tests`
2. **Harjoitukset** — `?action=exercises`
3. **Seuraa edistymistä** — `?action=progress-checkin`

Template: `templates/my-account/components/content/exercises.php` shows **per-user assigned exercises** only when an admin has clicked **Display exercises**. Otherwise the user sees a Finnish waiting message. Exercise cards are included directly in the template loop (not via `bt_file_import`) so `$exercise_id` stays in scope.

### Test → exercise assignment workflow

1. When a user **completes a test round** (no more tests permitted), the plugin auto-suggests **up to 5 exercises per round** — one per distinct test rated **3 or 4** in that round (`ExerciseRepository::MAX_PER_ROUND = 5`). Suggestions run when the final rating ends the round (form submit) and when the user next opens the tests tab. Ratings without a linked exercise post (e.g. intro copy *Hyvä tietää ennen testien tekemistä*) are skipped; the assigner keeps scanning all 3/4 ratings in attempt order until 5 are linked or the list ends. No suggestions are created if the round has no 3/4 ratings.
2. Admin reviews suggestions in **Tests → Users Progress** → user detail → **Exercise assignments** panel (React). The **User display order** table shows position numbers (`#1`, `#2`, …) that update immediately when using the arrow buttons; the round tabs below show each row's Harjoitukset position.
3. Admin **approves** auto-suggested exercises, may **reorder**, **add** manual exercises (searchable autocomplete — manual adds are **auto-approved**), or **remove** rows. When exercises are already displayed, newly approved rows are promoted to **visible** immediately.
4. Admin toggles **Display exercises** / **Exercises invisible** (per user). Only when displayed do approved rows become **visible** to the user. Re-displaying after hiding works when the user still has approved or visible assignment rows.
5. User sees a countdown: **X päivää jäljellä suositellusta ajasta tehdä harjoituksia** (default period: **12 days**, configurable under **Tests → Settings → Exercise recommended period**). Countdown uses WordPress site timezone (`mysql2date` / `current_time`). At **0 days**, exercises **remain visible**.

Assignment statuses: `suggested` → `approved` → `visible`.

REST (admin): `GET/POST /wp-json/balance-testing/v1/user_exercises` and related routes in `includes/API/UserExercises.php`.

### Exercise identifier

- Meta key `_bt_exercise_identifier` — set automatically when using **Bulk copy** or **Copy to excercise** (fallback link when `_bt_copied_from_test_id` is missing). No manual sidebar field.
- List table columns: **Identifier**, **Linked test**.

### Bulk copy tests → exercises

**Tests → Bulk copy to exercises** copies all published tests that have no linked exercise yet, sets identifier from meta or title prefix (e.g. `72.` → `TEST-72`), and publishes the new exercise posts. The admin screen uses a card layout with stats (published / linked / ready to copy) and hides third-party plugin notices (Rank Math, etc.) on this page and **Users Progress**.

## Copy test → excercise (admin)

On **Tests → Edit test**, the publish box includes **Copy To excercise** below **Update** only (`includes/Migration/CopyToExcercise/`).

The same action is available on **Tests** list rows (after **Duplicate This** when present).

- Creates a new **excercise** draft from the current **test** post.
- Copies title, content, excerpt, menu order, featured image, taxonomies, and all post meta.
- Re-saves ACF fields (`images`, `test_video_embed`, `test_upload_video`, `test_scale`, and any other fields on the test).
- Redirects to the new exercise draft with a success notice.
- Source link is stored on the exercise as `_bt_copied_from_test_id`.

Frontend styles (`assets/css/style.css`) are enqueued on the public site only — not on wp-admin — so they do not affect the post editor Publish box.

## Public registration (disabled)

Balance Testing **does not create WordPress users**. The plugin enforces closed registration:

- `includes/UserRegistrationGuard.php` forces `users_can_register` off (even if another plugin sets it in the database).
- `wp-login.php?action=register` redirects to `/omatestaus/?register=blocked`.
- `[bt_account]` always shows **login** when registration is disabled (not a blank “Registration is disabled” page).
- Every new WP user triggers a line in `wp-content/debug.log` prefixed with `[balance-testing registration-guard]` (user id, email, IP, call stack) so you can see **what path** created the account.

### If you still see spam users with disposable emails

Those accounts were **not** created by balance-testing. Typical sources:

1. Registration was open in the past (check **Users → Registered** date).
2. **Users → Add New** (admin can use any email).
3. Another active plugin on the server (membership, LMS, forms).

### Cleaning up spam accounts (manual)

1. **Users** → filter by **Subscriber**, sort by **Registered**.
2. Delete accounts with disposable domains or no real activity.
3. Keep **Settings → General → Anyone can register** unchecked (the plugin also forces this off).
4. Remove “Register” links from page builder menus on Omatestaus if any remain.
5. After deploy, watch `debug.log` for `[balance-testing registration-guard]` — if new lines appear, note the `call_stack` field to find the plugin or URL still creating users.

### REST API

`/wp-json/balance-testing/v1/users` and `/user_progress` require `manage_options` (admin only).

### Linux deploy (case-sensitive paths)

The plugin autoloader maps `BalanceTesting\Migration\…` to `includes/Migration/…`. The folder must be spelled **`Migration`** (not `MIgration`). macOS often hides this typo; Linux servers fatally error with `Class "BalanceTesting\Migration\CopyToExcercise\CopyToExcercise" not found`. After deploy, confirm `includes/Migration/CopyToExcercise/CopyToExcercise.php` exists on the server.

## Agent browser verification (Cursor)

Live UI checks (login, Tasapainotestit nav, progress forms) should use **Cursor’s inline Browser Tab** (`cursor-ide-browser` MCP), not ad-hoc Playwright scripts.

**One-time setup (Cursor IDE):**

1. **Settings → Tools & MCP → Browser Automation** → **Browser Tab** → enabled.
2. **Cmd+Shift+P → “Open Browser Tab”** (or editor **… → Open Browser**).
3. In Agent chat, confirm **“Connected to Browser Tab”**.
4. **Reload Window**, then start a **new Agent chat** (browser MCP attaches per session).

**Fallback:** project `.cursor/mcp.json` includes `@playwright/mcp` if the built-in browser cannot be leased. After adding MCP config, reload Cursor once. First run: `npx playwright install chromium`.

Local site URL for checks: `http://balance-testing.local/` (admin test login documented in dev notes).

**Local email (Mailpit):** Safari blocks `http://localhost:10060`. Use **http://mailpit.balance-testing.local/** or run `scripts/patch-local-mailpit-button.sh` (after quitting Local) so **Open Mailpit** works. See [docs/LOCAL-DEV.md](docs/LOCAL-DEV.md).

