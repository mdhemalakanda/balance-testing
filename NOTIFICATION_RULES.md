# Notification Rules

## Low 3/4 Rating Admin Alert (Rounds 1-3)

The system sends an admin alert to `jani@selkakuntoutus.fi` when all tests in the current round are completed and the user has fewer than the **required 3/4 threshold** for that round (default **6**; overridable per user in admin).

- Applies to rounds `1`, `2`, and `3`.
- Trigger point: round completion check when no more tests are available for the user in that round.
- Deduplication: sent once per user per round using user meta keys:
  - `round_1_low_rating_admin_mail_sent`
  - `round_2_low_rating_admin_mail_sent`
  - `round_3_low_rating_admin_mail_sent`

Mail includes:
- Username
- Email
- Round number
- Count of ratings that are `3` or `4`

## Round Progress Scheduling Rules

- Scheduling is triggered from backend logic in `includes/RoundLimits.php` (`send_permitted_user_to_schedule_mail`, via `Utils::send_permitted_user_to_schedule_mail`) when the user can no longer continue tests in the current round.
- A round ends when the user reaches the **max tests** limit for that round **or** the **3/4 rating threshold** (default `6`; overridable per user in admin). Either path stops further tests in that round.
- When a round ends, the **next-round invite** is always scheduled (round 2 → `round_2_mail_scheduled`, round 3 → `round_3_mail_scheduled`, round 4/final → `round_4_mail_scheduled`), **including** when the user finished below the 3/4 threshold. This keeps exercise suggestions and round progression aligned: users who completed a round (and may have exercises suggested from available 3/4 ratings) still receive the follow-up email and auto-access path.
- If the round ended **below** the 3/4 threshold, the **low-rating admin alert** is still sent (once per user per round) in addition to the next-round invite schedule.
- Max tests defaults: **43** for rounds 1–2, **42** for round 3 (overridable per user in admin).

## Per-round admin overrides

- Admin UI: **Tests → Users Progress** → user detail → **Per-round test limits**.
- User meta key: `balance_testing_user_round_rules` (rounds `1`–`3`: optional `max_tests`, `rating_threshold`).
- Implementation: `includes/RoundLimits.php` (does not modify existing `Utils` rating/exclude SQL).
- Admin "round completed" mail for rounds 1/2/3 is sent only when a new schedule is actually created (duplicate schedule attempts do not re-send admin mail).
- UI template `test.php` no longer controls notification scheduling flags; it only renders user messages.

## Admin User Detail Data Mapping

- Admin user detail view now resolves question data by selected user ID key from `/wp-json/balance-testing/v1/users?user={id}`.
- Backend `/users` endpoint now returns a single-user keyed payload when `user` param is provided, preventing first-record fallback issues.
- This prevents preliminary question blocks from showing another user's data when switching between users in admin view.

## Admin User List Filtering

- Backend `/users` endpoint now ignores orphan rows where `user_id` does not map to an existing WordPress user account.
- This prevents stale IDs from deleted users from appearing in admin progress/user lists.

## Test Video Rendering Safeguards

- Test video view supports fallback lookup from direct post meta (`test_video_embed`, `test_upload_video`) if ACF helper values are unavailable.
- If no video source exists for the selected test, no on-page warning is shown (video block is omitted). Missing-video cases are still logged with `test_id` and `user_id` for support investigation.

## Round 2 and 3 Follow-up Email Body

- Scheduled invite emails for rounds 2 and 3 share the same Finnish body copy in `includes/Schedule/TestAccessMail.php` (`build_round_follow_up_email_html`).
- Copy explains the 12-day follow-up window, when to complete the next test round depending on when exercises started, includes a **Kirjaudu täältä** login link, and closes with **Ystävällisin terveisin, Jani Mikkonen / Parempi tasapaino- verkkokurssit**.
- Round 4 (final) invite email keeps its own shorter closing message.

## Round Invite Schedule Admin Settings

- Test settings admin page now includes a **Round Invite Schedule Settings** section for configuring round follow-up timings.
- Added configurable delay fields for:
  - `round_2_invite_delay`
  - `round_3_invite_delay`
  - `round_4_invite_delay` (final invite)
  - `auto_access_delay`
- Added `enable_auto_access` checkbox to grant questionnaire access automatically after configured delay even without email-link click.
- Values are saved in option key `balance_testing_round_invite_schedule_settings` using WordPress Settings API with sanitization defaults:
  - Invite delays default to `12 Day(s)` for rounds 2/3/4.
  - Auto-access delay defaults to `10 Day(s)`.
  - **Exercise recommended period** (`exercise_recommended_days`) defaults to `12` days for the Harjoitukset countdown.

## Exercise assignment notifications

- When auto-suggestions are created after a round ends, admin receives a **review exercises** email (once per user per round; meta `round_{N}_exercise_suggest_admin_mail_sent`).
- When admin clicks **Display exercises**, the user receives **Harjoituksesi ovat valmiina** with a link to `?action=exercises` (once per user; meta `bt_exercises_displayed_user_mail_sent`).
- Implementation: `includes/Exercise/ExerciseAdminNotifier.php`, triggered from `ExerciseAssigner` and `ExerciseVisibility`.

## Front-Page Login Flow (Omatestaus)

- Login attempts from `omatestaus` now stay on the same front page.
- Invalid credentials redirect back to `omatestaus` with `?login=failed` instead of sending users to `wp-login.php`.
- Empty username/password redirect back to `omatestaus` with `?login=empty`.
- Successful login from `omatestaus` redirects to `/bt-user-account/` to avoid redirect loops.
- Lost-password link on the login form points to WordPress reset flow with return URL `omatestaus/?reset=sent`.
