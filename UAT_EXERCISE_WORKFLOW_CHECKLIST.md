# UAT checklist — Test → Exercise workflow (client-facing)

Use this checklist on a **fresh test user** to verify end-to-end behavior.

## Preconditions

- The user is on round 1, 2, or 3 and can submit test ratings.
- Test/exercise mapping exists (via identifier or copied link).
- Admin can access:  
  `http://balance-testing.local/wp-admin/edit.php?post_type=test&page=test-users&user_id=<USER_ID>#/test/user/<USER_ID>`

---

## 1) Auto-suggestion from ratings 3/4

- [ ] Submit tests for the user until round completion condition is met.
- [ ] Ensure at least some tests in the completed round are rated **3** or **4**.
- [ ] Open **Tests → Users Progress → user detail → Exercise assignments**.
- [ ] Verify suggested assignments are created automatically.
- [ ] Verify max per round is respected (**5 per round**).
- [ ] Verify rows without a mapped exercise are skipped (no invalid row appears).

Expected: up to 5 exercises are suggested from round ratings 3/4.

---

## 2) Identifier mapping coverage

- [ ] In assignment rows, confirm exercise identifiers are shown (e.g. `TEST-12`).
- [ ] Confirm suggested rows match expected tests/exercises by identifier.

Expected: test → exercise matching works via copied link or shared identifier.

---

## 3) Admin workflow (approve/add/edit/delete)

- [ ] Approve one suggested row.
- [ ] Use **Approve all suggested** and verify statuses update.
- [ ] Add one manual exercise using search dropdown.
- [ ] Verify manual add appears in active round and is ready for display.
- [ ] Open **Edit exercise** link and confirm target post opens.
- [ ] Remove one assignment and verify row disappears.

Expected: admin can review, approve, add, edit, and delete assignments.

---

## 4) Order management

- [ ] Reorder rows using arrow buttons.
- [ ] Reorder rows using **drag handle** (top ↕ bottom).
- [ ] Refresh page and verify order persists.

Expected: displayed order is saved and stable after reload.

---

## 5) Visibility toggle to Harjoitukset

- [ ] Click **Exercises invisible** (hide).
- [ ] Open user frontend:  
  `http://balance-testing.local/bt-user-account/?action=exercises`
- [ ] Verify assigned exercises are not shown.
- [ ] Back in admin, click **Display exercises**.
- [ ] Reload frontend page and verify exercises appear.

Expected: exercises appear for the user only when display is enabled.

---

## 6) Countdown text and day behavior

- [ ] On frontend, verify exact text format:  
  **`X päivää jäljellä suositellusta ajasta tehdä harjoituksia`**
- [ ] (If possible) set remaining to 0 days in test data and reload frontend.
- [ ] Confirm exercises still remain visible at 0 days.

Expected: countdown is shown in Finnish format; 0 days does **not** hide exercises.

---

## 7) Regression quick checks

- [ ] Round tabs show correct counts (`Round N (x/5)`).
- [ ] Display button state is correct (`Display exercises` vs `Exercises invisible`).
- [ ] No duplicate assignment appears for same user+round+exercise.
- [ ] No JS errors in browser console while reorder/add/remove/visibility actions run.

---

## Pass criteria

Release is acceptable when all checked items above pass on at least one fresh user end-to-end.
