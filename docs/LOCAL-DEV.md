# Local development — Balance Testing site

## Site URL

- **Front end:** http://balance-testing.local/
- **Admin:** http://balance-testing.local/wp-admin/

Use **http** (not https) for this Local site.

## Local email (Mailpit) — Safari fix

Local’s **Tools → Mailpit → Open Mailpit** opens `http://localhost:10060/`. **Safari with HTTPS-Only enabled blocks that URL** (WebKit error 305). Apple does not allow an exception for `localhost` — this is a Safari limitation, not a WordPress bug.

### One-time setup (makes Local’s button work in Safari)

```bash
cd "/Users/mdhemalakhand/Local Sites/balance-testing"

# 1. Mailpit subdomain + /etc/hosts
./scripts/install-local-mailpit-route.sh balance-testing.local 10060

# 2. Quit Local.app completely, then patch its “Open Mailpit” button (macOS password prompt)
./scripts/patch-local-mailpit-button.sh
```

After step 2, **Open Mailpit** in Local opens `http://mailpit.balance-testing.local/` instead of `http://localhost:10060/`.

Re-run the patch script after **Local app updates** (updates restore the old `localhost` URL).

### Use anytime (no Local patch)

| What | URL |
|------|-----|
| **Full Mailpit UI** | http://mailpit.balance-testing.local/ |
| **Shortcut** | http://balance-testing.local/mailpit/ |
| **WordPress inbox** | wp-admin → **Local Email** |

### Other browsers / Safari setting

Chrome or Firefox can use `http://localhost:10060/` directly.

To keep using `localhost` in Safari: **Safari → Settings → Security** → disable **Warn before connecting to a website over HTTP**.

### Links inside emails

Login and reset links in captured mail should point to **`http://balance-testing.local/...`**, not `localhost:10060`.

## Default admin credentials

| Field    | Value |
|----------|-------|
| Username | `admin` |
| Password | `123` |

Local development only.

## Plugin path

```
wp-content/plugins/balance-testing/
```
