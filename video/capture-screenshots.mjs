import { chromium } from "playwright";
import { mkdirSync, readdirSync, existsSync, copyFileSync } from "fs";
import path from "path";
import { fileURLToPath } from "url";
import mysql from "mysql2/promise";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(__dirname, "assets", "clean");
const DOCS_ASSETS = path.join(__dirname, "..", "assets");
const BASE = "http://balance-testing.local";
const CAPTURE_USER_ID = 87;

function findMysqlSocket() {
  const runDir = path.join(process.env.HOME || "", "Library/Application Support/Local/run");
  if (!existsSync(runDir)) return null;
  for (const dir of readdirSync(runDir)) {
    const sock = path.join(runDir, dir, "mysql", "mysqld.sock");
    if (existsSync(sock)) return sock;
  }
  return null;
}

const MYSQL_SOCKET = findMysqlSocket();

mkdirSync(OUT, { recursive: true });

const HIDE_NOTICES_CSS = `
  .notice, .update-nag, .updated, .error, .is-dismissible,
  #elementor-notice-bar, .rank-math-notice, .e-notice,
  #wpbody-content > .wrap > .notice,
  #wpbody-content > .notice,
  #loco-notices, .fs-notice, .wrap > .notice,
  .components-notice, [class*="Notice"], [id*="notice"] { display: none !important; }
  #wpadminbar { display: none !important; }
  html { margin-top: 0 !important; }
  body.admin-bar { padding-top: 0 !important; }
  #litespeed_meta_boxes, #postbox-container-2 #postbox-container-2,
  #perfmatters-meta-box, .rank-math-metabox-wrap,
  #postbox-container-2 #side-sortables .postbox:not(#submitdiv):not(#bt-linked-exercise) { display: none !important; }
  .column-rank_math_title, .column-rank_math_description, .column-seo-details { display: none !important; }
`;

const HIDE_ADMIN_BAR_CSS = `
  #wpadminbar { display: none !important; }
  html { margin-top: 0 !important; }
  body.admin-bar { padding-top: 0 !important; }
`;

async function dbConnect() {
  if (!MYSQL_SOCKET) throw new Error("Local MySQL socket not found — start the Local site.");
  return mysql.createConnection({
    socketPath: MYSQL_SOCKET,
    user: "root",
    password: "root",
    database: "local",
  });
}

async function ensureTestLinkedExercise(conn, testId, exerciseId) {
  await conn.query(
    "INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_bt_linked_exercise_id', ?) ON DUPLICATE KEY UPDATE meta_value = ?",
    [testId, String(exerciseId), String(exerciseId)]
  );
}

async function backupCaptureUserState(conn) {
  const [roundRows] = await conn.query(
    "SELECT meta_value FROM wp_usermeta WHERE user_id = ? AND meta_key = 'test_round' LIMIT 1",
    [CAPTURE_USER_ID]
  );
  const [visRows] = await conn.query(
    "SELECT meta_value FROM wp_usermeta WHERE user_id = ? AND meta_key = 'bt_exercises_visible' LIMIT 1",
    [CAPTURE_USER_ID]
  );
  const [ratings] = await conn.query(
    "SELECT test_id, round, rating FROM wp_user_ratings WHERE user_id = ? AND round = 1",
    [CAPTURE_USER_ID]
  );
  return {
    testRound: roundRows[0]?.meta_value ?? "3",
    exercisesVisible: visRows[0]?.meta_value ?? "1",
    roundRatings: ratings,
  };
}

async function setActiveBalanceTestState(conn) {
  await conn.query(
    "INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (?, 'test_round', '1') ON DUPLICATE KEY UPDATE meta_value = '1'",
    [CAPTURE_USER_ID]
  );
  await conn.query("DELETE FROM wp_user_ratings WHERE user_id = ? AND round = 1", [CAPTURE_USER_ID]);
}

async function restoreCaptureUserState(conn, backup) {
  await conn.query(
    "UPDATE wp_usermeta SET meta_value = ? WHERE user_id = ? AND meta_key = 'test_round'",
    [backup.testRound, CAPTURE_USER_ID]
  );
  await conn.query(
    "INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (?, 'bt_exercises_visible', ?) ON DUPLICATE KEY UPDATE meta_value = ?",
    [CAPTURE_USER_ID, backup.exercisesVisible, backup.exercisesVisible]
  );
  await conn.query("DELETE FROM wp_user_ratings WHERE user_id = ? AND round = 1", [CAPTURE_USER_ID]);
  for (const row of backup.roundRatings) {
    await conn.query(
      "INSERT INTO wp_user_ratings (user_id, test_id, round, rating) VALUES (?, ?, ?, ?)",
      [CAPTURE_USER_ID, row.test_id, row.round, row.rating]
    );
  }
}

async function cleanAdmin(page) {
  await page.addStyleTag({ content: HIDE_NOTICES_CSS });
  await page.evaluate(() => {
    document.querySelectorAll(".notice, .update-nag, [class*='notice']").forEach((el) => el.remove());
  });
}

async function cleanFrontend(page) {
  await page.addStyleTag({ content: HIDE_ADMIN_BAR_CSS });
}

async function login(page, user = "admin", pass = "123") {
  await page.goto(`${BASE}/wp-login.php`);
  await page.fill("#user_login", user);
  await page.fill("#user_pass", pass);
  await page.click("#wp-submit");
  await page.waitForURL("**/wp-admin/**", { timeout: 20000 });
}

async function shot(page, name, opts = {}) {
  const file = path.join(OUT, `${name}.png`);
  const target = opts.locator ?? (opts.selector ? page.locator(opts.selector).first() : null);
  if (target) {
    await target.waitFor({ state: "visible", timeout: 20000 });
    await target.screenshot({ path: file });
  } else {
    await page.screenshot({ path: file, fullPage: false });
  }
  console.log("saved", name);
}

async function viewportAtTop(page) {
  await page.evaluate(() => window.scrollTo(0, 0));
}

async function getFirstUserId(page) {
  return page.evaluate(async () => {
    const res = await fetch("/wp-json/balance-testing/v1/users?status=publish", {
      headers: { "X-WP-Nonce": window.btAdmin?.nonce ?? "" },
      credentials: "same-origin",
    });
    if (!res.ok) return null;
    const data = await res.json();
    const users = Object.values(data).filter((u) => u?.user_id);
    users.sort((a, b) => Number(a.user_id) - Number(b.user_id));
    return users[0]?.user_id ?? null;
  });
}

async function setExerciseVisibility(page, userId, visible) {
  await page.evaluate(
    async ({ uid, vis }) => {
      await fetch(`/wp-json/balance-testing/v1/user_exercises/visibility?user=${uid}`, {
        method: "POST",
        headers: {
          "X-WP-Nonce": window.btAdmin?.nonce ?? "",
          "Content-Type": "application/json",
        },
        credentials: "same-origin",
        body: JSON.stringify({ visible: vis }),
      });
    },
    { uid: userId, vis: visible }
  );
}

async function main() {
  const conn = await dbConnect();
  const userBackup = await backupCaptureUserState(conn);

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 2,
  });
  const page = await context.newPage();

  try {
    await login(page);

    await page.goto(`${BASE}/wp-admin/edit.php?post_type=test`);
    await cleanAdmin(page);
    await page.waitForSelector("#adminmenu");
    await viewportAtTop(page);
    await shot(page, "02-tests-list");

    const editHref = await page.locator("tbody#the-list tr .row-title").first().getAttribute("href");
    const testId = parseInt(new URL(editHref, BASE).searchParams.get("post") || "0", 10);

    const [exerciseRows] = await conn.query(
      "SELECT ID FROM wp_posts WHERE post_type = 'excercise' AND post_status IN ('publish','draft') ORDER BY ID ASC LIMIT 1"
    );
    const exerciseId = exerciseRows[0]?.ID ? Number(exerciseRows[0].ID) : 0;
    if (testId && exerciseId) {
      await ensureTestLinkedExercise(conn, testId, exerciseId);
    }

    await page.goto(editHref);
    await cleanAdmin(page);
    await page.waitForSelector("#title");
    await viewportAtTop(page);
    await shot(page, "01-edit-test");
    await page.evaluate(() => document.querySelector("#submitdiv")?.scrollIntoView({ block: "center" }));
    await page.waitForSelector(".bt-copy-to-excercise-btn", { timeout: 15000 });
    await shot(page, "01b-copy-button", { selector: "#submitdiv" });

    await page.waitForSelector("#bt-linked-exercise-root", { timeout: 15000 });
    await page.waitForSelector(".bt-linked-exercise__card", { timeout: 15000 });
    await page.evaluate(() => document.querySelector("#bt-linked-exercise")?.scrollIntoView({ block: "start" }));
    await page.waitForTimeout(400);
    await shot(page, "01c-linked-exercise", { selector: "#bt-linked-exercise" });

    const search = page.locator("#bt-linked-exercise-search");
    await search.click();
    await search.fill("tasapaino");
    await page.locator("#bt-linked-exercise-search-btn").click();
    await page.waitForSelector(".bt-linked-exercise__option", { timeout: 15000 });
    await page.waitForTimeout(300);
    await shot(page, "01c-linked-exercise-open", { selector: "#bt-linked-exercise" });

    await page.goto(`${BASE}/wp-admin/edit.php?post_type=excercise`);
    await cleanAdmin(page);
    await page.waitForSelector("#posts-filter");
    await viewportAtTop(page);
    await shot(page, "03-exercises-list");

    await page.goto(`${BASE}/wp-admin/edit.php?post_type=test&page=bt-bulk-copy-exercises`);
    await cleanAdmin(page);
    await page.waitForSelector(".wrap");
    await viewportAtTop(page);
    await shot(page, "04-bulk-copy");

    await page.goto(`${BASE}/wp-admin/edit.php?post_type=test&page=test-users#/test/table`);
    await cleanAdmin(page);
    await page.waitForSelector('button:has-text("View")', { timeout: 45000 });
    await viewportAtTop(page);
    await shot(page, "05-users-progress");

    const userId = await getFirstUserId(page);
    if (!userId) throw new Error("No participant user found for user-detail screenshots");

    await page.goto(
      `${BASE}/wp-admin/edit.php?post_type=test&page=test-users&user_id=${userId}#/test/user/${userId}`
    );
    await cleanAdmin(page);
    await page.waitForSelector("text=Exercise assignments", { timeout: 45000 });
    await page.waitForSelector(
      'button:has-text("Display exercises"), button:has-text("Exercises invisible")',
      { timeout: 30000 }
    );
    await page.waitForSelector("text=/Ready to display:/", { timeout: 30000 });

    const panel = page.locator(".MuiPaper-root").filter({ hasText: "Exercise assignments" }).first();
    await panel.scrollIntoViewIfNeeded();
    await shot(page, "06b-user-detail", { locator: panel });

    const approveBtn = page.locator('button:has-text("Approve")').first();
    if (await approveBtn.count()) {
      await approveBtn.scrollIntoViewIfNeeded();
    } else {
      await page.locator('button:has-text("Approve all suggested")').scrollIntoViewIfNeeded();
    }
    await shot(page, "06-exercise-assignments", { locator: panel });

    await page.goto(`${BASE}/wp-admin/edit.php?post_type=test&page=settings`);
    await cleanAdmin(page);
    await page.waitForSelector(".wrap");
    await viewportAtTop(page);
    await shot(page, "07-settings");

    // Frontend captures — temporarily show active balance test UI
    await setActiveBalanceTestState(conn);
    await page.context().clearCookies();
    await login(page);

    await page.goto(`${BASE}/bt-user-account/?action=balance-tests`);
    await cleanFrontend(page);
    await page.waitForSelector(".bt-student-account-nav", { timeout: 15000 });
    await shot(page, "08-account-nav");

    await page.waitForSelector(".bt-test-box", { timeout: 15000 });
    await page.evaluate(() => {
      document.querySelector(".bt-test-box")?.scrollIntoView({ block: "start" });
    });
    await shot(page, "09-balance-tests", { selector: ".bt-test-box" });

    await page.goto(`${BASE}/bt-user-account/?action=exercises`);
    await cleanFrontend(page);
    await page.waitForSelector("#exercises .bt-exercises-list, #exercises .bt-alert", {
      timeout: 15000,
    });
    await shot(page, "10-exercises");

    await conn.query(
      "INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (?, 'bt_exercises_visible', '0') ON DUPLICATE KEY UPDATE meta_value = '0'",
      [CAPTURE_USER_ID]
    );
    await page.context().clearCookies();
    await login(page);
    await page.goto(`${BASE}/bt-user-account/?action=exercises`);
    await cleanFrontend(page);
    await page.waitForSelector("#exercises", { timeout: 15000 });
    await shot(page, "10-exercises-waiting");
  } finally {
    await browser.close();
    await restoreCaptureUserState(conn, userBackup);
    await conn.end();
    console.log("Restored capture user DB state");
  }

  copyToDocsAssets([
    "01-edit-test.png",
    "01b-copy-button.png",
    "01c-linked-exercise.png",
    "01c-linked-exercise-open.png",
    "02-tests-list.png",
    "03-exercises-list.png",
    "04-bulk-copy.png",
    "05-users-progress.png",
    "06-exercise-assignments.png",
    "06b-user-detail.png",
    "07-settings.png",
    "08-account-nav.png",
    "09-balance-tests.png",
    "10-exercises.png",
    "10-exercises-waiting.png",
  ]);

  console.log("Done. Clean assets in", OUT);
}

function copyToDocsAssets(names) {
  mkdirSync(DOCS_ASSETS, { recursive: true });
  const legacy = {
    "01b-copy-button.png": "01b-copy-to-exercise-button.png",
    "04-bulk-copy.png": "04-bulk-copy-exercises.png",
    "05-users-progress.png": "05-users-progress-list.png",
    "08-account-nav.png": "08-frontend-account-nav.png",
    "09-balance-tests.png": "09-frontend-balance-tests.png",
    "10-exercises.png": "10-frontend-exercises.png",
    "06-exercise-assignments.png": "06-user-detail-exercises.png",
    "06b-user-detail.png": "06b-user-detail-admin.png",
  };
  for (const name of names) {
    const src = path.join(OUT, name);
    if (!existsSync(src)) continue;
    copyFileSync(src, path.join(DOCS_ASSETS, name));
    if (legacy[name]) {
      copyFileSync(src, path.join(DOCS_ASSETS, legacy[name]));
    }
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
