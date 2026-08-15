# Billions Earn

Premium cashback, rewards, referral & marketplace platform. Runs on plain PHP 8.1+
with a lightweight custom MVC kernel (no framework), MySQL/MariaDB, and Composer
for a handful of focused dependencies. Built to run on standard Hostinger shared
hosting.

Production target: `https://earn.billionsstore.com/`, logically and technically
separate from the existing `https://billionsstore.com/` site (separate app,
separate database, separate document root/subdomain).

## Stack

- PHP 8.1+ (developed/tested on 8.4), PDO MySQL, `bcmath` for all money math
- Custom Router/Container/Session/View kernel (`src/Core`) - no framework
- `vlucas/phpdotenv` for `.env` loading, `phpmailer/phpmailer` for SMTP,
  `endroid/qr-code` for self-hosted QR generation (deposit address, referral link)
- Plain PHP templates (`resources/views`), no build step, no Node required
- PHPUnit 10 for tests

## Local setup

```bash
composer install
cp .env.example .env        # edit DB_*, MAIL_*, APP_URL
php bin/migrate.php         # creates all tables (idempotent)
php bin/seed.php            # roles, super admin, membership tiers, referral
                             # levels, bootstrap invitation code, demo products
php -S 127.0.0.1:8080 -t public
```

`bin/seed.php` prints a generated super-admin password and a bootstrap
invitation code once - save them immediately, they are not stored or shown
again. Override them by exporting `SEED_ADMIN_EMAIL`, `SEED_ADMIN_PASSWORD`,
`SEED_INVITE_CODE` before running the seeder.

Set `MAIL_MAILER=log` in `.env` for local development - emails are written to
`storage/logs/app-*.log` instead of being sent, so you can grab verification/
reset links without a real SMTP server.

### Tests

```bash
composer test
```

Runs against a separate `billions_earn_test` database (see `tests/bootstrap.php`
for connection defaults, overridable via real env vars). The suite truncates
and reseeds that database once per run - never point `DB_DATABASE` for tests at
a database you care about. Covers: registration/invite validation, login,
password reset single-use tokens, wallet ledger integrity (credit/debit,
insufficient-balance rejection, frozen-wallet blocking, admin-override),
deposit approve/reject and double-approval prevention, referral welcome/
deposit-bonus idempotency, withdrawal min/max/duplicate-in-flight/reject-releases-funds/
full-lifecycle-to-paid/double-pay prevention, and an IDOR check (a user cannot
cancel another user's withdrawal).

## Architecture

```
public/            Document root - index.php front controller, assets, uploads
bootstrap/app.php  Env loading, error/exception handlers, timezone
routes/web.php     All routes (user + admin), grouped by middleware
src/Core/          Router, Request, Response, Database (PDO + transaction()
                   helper with safe re-entrancy), Session, Csrf, View, Logger
src/Middleware/    Security headers, CSRF, auth/guest guards (user + admin),
                   maintenance mode
src/Controllers/   User-facing controllers; src/Controllers/Admin/ for the
                   admin panel
src/Services/      All business logic lives here, not in controllers -
                   WalletService, ReferralService, DepositService,
                   WithdrawalService, OrderService, ProductService,
                   AuthService, AdminAuthService, ChatbotService, etc.
resources/views/   Plain PHP templates, resources/views/admin/ for the admin UI
database/migrations/  Plain numbered .sql files, applied by bin/migrate.php
                       via a schema_migrations table (safe to re-run)
tests/             PHPUnit, see above
```

### Financial model (why it's built this way)

Every balance-changing action goes through `WalletService::applyLedgerEntry()`,
which is the **only** code path allowed to write to `wallets` - it always runs
inside `Database::transaction()`, locks the wallet row (`SELECT ... FOR UPDATE`),
and writes an append-only `wallet_ledger` row alongside the balance update. All
money values are `DECIMAL` columns and all arithmetic goes through `bcmath`
(never floats). Deposits, withdrawals, referral bonuses, and cashback all
enforce idempotency at the database level (unique constraints, status guards
checked under a row lock), not just in application logic - see
`uniq_reward_dedupe` on `referral_rewards`, the `status = 'pending'` guard in
`DepositService::approve`/`WithdrawalService::*`, and the `uniq_deposit_txid`
constraint.

Withdrawals debit spendable balances (referral -> cashback -> deposited, in
that priority order) into a `reserved` balance at request time, so funds are
locked the moment a withdrawal is submitted - not just checked-and-hoped-for at
approval time. Rejecting or cancelling a withdrawal releases the exact
per-bucket amounts back via a compensating ledger entry, never a silent
balance edit.

## Hostinger deployment

The current document root reported for this subdomain is
`/home/u681671055/domains/billionsstore.com/public_html/earn`. **Prefer**
changing the `earn` subdomain's document root in hPanel to
`.../public_html/earn/public` instead - that keeps `src/`, `bootstrap/`,
`routes/`, `database/`, `storage/`, `vendor/`, and `.env` outside the web root
entirely (the most important single hardening step you can take). A root-level
`.htaccess` fallback that rewrites into `/public/` is included in case the
document root truly cannot be changed, but it's a fallback, not the
recommended setup.

1. **PHP version & extensions**: PHP 8.1+ (8.2/8.3 recommended on Hostinger).
   Required extensions: `pdo_mysql`, `mbstring`, `bcmath`, `json`, `gd`
   (image re-encoding on upload), `fileinfo` (MIME detection). All are
   standard on Hostinger's PHP versions - just confirm they're enabled in
   hPanel's PHP configuration for this domain.
2. **Database**: create a MySQL/MariaDB database + user in hPanel, then run
   `php bin/migrate.php` and `php bin/seed.php` once via SSH (or Hostinger's
   "Run PHP script" / cron-once trick if SSH isn't available).
3. **`.env`**: copy `.env.example` to `.env` on the server and fill in real
   values. **Never commit `.env`.** Set `APP_ENV=production`,
   `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` (the site is HTTPS).
4. **File permissions**: `storage/` and `public/uploads/` must be writable by
   the PHP process (typically `750`/`640` under the hosting user - Hostinger's
   shared hosting runs PHP as your account user, so default ownership is
   usually already correct; just ensure the directories exist and aren't
   locked to `000`).
5. **SSL**: enable Hostinger's free SSL for the `earn` subdomain in hPanel,
   then force HTTPS (the app already sends HSTS when
   `SESSION_SECURE_COOKIE=true`).
6. **Cron**: none are required for the app to function today (no queued
   emails, no background workers). If you later add scheduled cleanup
   (expired token pruning, price-cache refresh, etc.), Hostinger's cron runs
   `php /path/to/script.php` on a schedule - no persistent worker needed,
   matching the "no long-running processes" constraint of shared hosting.
7. **SMTP**: set `MAIL_MAILER=smtp` and the `MAIL_*` variables to Hostinger's
   (or any) SMTP credentials for the domain. Verify with a test registration
   that the verification email actually arrives before going live.
8. **BTC deposit address**: set it from the admin panel
   (`/admin/settings`) after first login, not via `.env` - it's stored in
   `site_settings` so it can be changed without a deploy.

## Environment variables

See `.env.example` for the full list with safe placeholders. Highlights:

| Variable | Purpose |
|---|---|
| `APP_ENV`, `APP_DEBUG` | `production`/`false` on the live server - never expose stack traces publicly |
| `APP_URL` | Used to build absolute links in emails (verification, password reset) |
| `DB_*` | Database connection |
| `SESSION_SECURE_COOKIE` | `true` in production (HTTPS) - controls the `Secure` cookie flag and HSTS |
| `MAIL_*` | SMTP credentials. `MAIL_MAILER=log` for local dev (no real send) |
| `BTC_PRICE_PROVIDER` | `manual` today - admin enters the BTC/USD rate at deposit-approval time, which is what actually gets permanently recorded per deposit regardless of this setting |
| `RATE_LIMIT_LOGIN_*` | Login/reset brute-force throttling |

The BTC receiving address, minimum deposit/withdrawal, welcome bonus, referral
levels, and membership tiers are **not** environment variables - they're admin-
configurable database settings (`/admin/settings`, `/admin/membership-levels`),
per the "don't hardcode business rules" requirement.

## Current scope & known limitations

Built and verified end-to-end against a running instance + real MariaDB:
invite-only registration, mandatory email verification, login with rate
limiting, password reset, the full wallet ledger engine, BTC deposit submit/
approve/reject with permanent rate recording, withdrawal request through
paid/completed with fund holds and releases, multi-level referral bonuses
(welcome + deposit-triggered, both idempotent), membership auto-upgrade,
product marketplace (search/filter/CSV import-export/click tracking), manual
order recording with cashback credit/reversal, a full RBAC'd admin panel
covering every module above, a rule-based chatbot, and a security/audit pass.

Not built in this pass (flagged honestly rather than left silently missing):

- **Automated marketplace API integrations** (Amazon/eBay/Walmart affiliate
  APIs) - the architecture supports it (`marketplaces.affiliate_base_url`,
  `product_clicks` tracking, `external_order_reference` on orders) but only
  manual product entry + CSV import are wired up, per the spec's own
  "version one" scope.
- **AI chatbot layer** - the rule-based chatbot is fully functional and is the
  primary path; `AI_CHATBOT_PROVIDER`/`AI_CHATBOT_API_KEY` are reserved in
  `.env.example` for a future provider, not yet implemented.
- **Content/legal pages** (`pages` table exists; no admin UI or public
  routes wired up yet for About/Terms/Privacy/FAQ pages) - the `faq` table
  does back the chatbot's FAQ fallback already.
- **Idempotency-key protection on non-financial double-submits** (e.g.
  double-clicking "Create product" could create two rows) - all
  financially-critical paths (deposits, withdrawals, referral bonuses,
  cashback) are idempotent at the database level; lower-stakes admin CRUD
  is not yet.
- **CSP still allows `'unsafe-inline'`** for script/style, needed by a few
  inline `onclick=confirm(...)` handlers and the CSRF-token bootstrap script.
  Tightening this further means moving those to external JS with
  `addEventListener` - straightforward but not done in this pass.
