# Dhaka Hotel

Bilingual (Bangla / English) restaurant site with a customer storefront, an owner admin panel, a single-screen POS and an installable PWA.

Plain **PHP 8.2+ / MySQL 8 (or MariaDB) / HTML / CSS / vanilla JavaScript**. PDO and prepared statements throughout. No Node, npm, bundler or front-end framework. Composer is used only for PHPMailer (email notifications).

## What is included

- **Public site** — menu with categories and search, product detail, session cart, guest checkout with idempotent order creation, order success page, order tracking (reference + phone), reviews with moderation, contact page and WhatsApp click-to-chat.
- **Admin** — login with rate limiting, dashboard (paid vs unpaid money kept separate), orders with enforced status transitions, separate payment and refund records, menu/category CRUD with validated image uploads, customers, review moderation, reports with CSV export, notification queue, settings and profile.
- **POS** (`admin/billing.php`) — category tiles, search, live bill, discount/delivery/tax, Generate Bill, Print Receipt, Complete Payment, Save Pending Bill, New Bill and resume of pending bills.
- **Receipts** — 58 mm, 80 mm and A4 layouts, independent receipt language, never changes payment state.
- **Bilingual** — every string comes from `includes/languages/bn.php` / `en.php`; Bangla is the default; admin language is independent from the public language.
- **PWA** — manifest, service worker with a public-pages-only cache, offline page, install instructions and real PNG icons.
- **Security** — CSRF tokens on every write, escaping on output, prepared statements, upload content inspection, CSP and other headers, secrets only in `.env`.

## Requirements

- PHP 8.2 or newer with `pdo_mysql`, `mbstring`, `fileinfo` and `gd` (icons/assets generation only)
- MySQL 8.0+ or MariaDB 10.4+
- Apache with `mod_rewrite`, `mod_headers`, `mod_mime` and `AllowOverride All` (XAMPP defaults are fine)
- Composer (optional, only for email notifications)
- A modern browser

## Local setup on Windows with XAMPP

1. Copy the project folder to `C:\xampp\htdocs\dhaka-hotel` (or any folder under `htdocs`).
2. Create the environment file:

   ```bat
   cd C:\xampp\htdocs\dhaka-hotel
   copy .env.example .env
   ```

3. Edit `.env` and set the database values. The defaults are:

   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=dhaka_hotel_demo
   DB_USER=root
   DB_PASSWORD=
   ```

   Set `APP_BASE_PATH=/dhaka-hotel` only if the site is served from that subfolder. Leave it empty at the web root.

4. Create an empty database in phpMyAdmin (`http://localhost/phpmyadmin`) or MySQL:

   ```sql
   CREATE DATABASE dhaka_hotel_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

5. Run the migrations and the repeatable demo seed from the project folder:

   ```bat
   C:\xampp\php\php.exe database\migration_runner.php migrate
   C:\xampp\php\php.exe database\seed_runner.php seed
   ```

6. Open `http://localhost/dhaka-hotel/` and the admin at `http://localhost/dhaka-hotel/admin/`.

### Demo login

```
Email:    admin@dhakahotel.demo
Password: DhakaDemo@2026!
```

The demo content (menu, reviews, sample orders) is fictional and labelled as demo in the UI.

## Useful CLI commands

```bat
:: show applied/pending migrations
php database\migration_runner.php status

:: apply pending migrations
php database\migration_runner.php migrate

:: roll back the most recent batch
php database\migration_runner.php rollback

:: reseed demo data (repeatable; does not drop tables)
php database\seed_runner.php seed

:: destructive demo reset - explicit flag + local env + exact DB name required
php database\reset_demo.php --confirm

:: process queued payment emails (cron / Task Scheduler)
php scripts\process_email_queue.php --limit=20
```

`reset_demo.php` refuses to run unless `APP_ENV` is local/development/testing, the database is exactly `dhaka_hotel_demo`, `DEMO_RESET_ALLOWED=true` is set in `.env`, and the `--confirm` flag is present.

## Email notifications

Emails are queued only after a payment is recorded (never on a generated or printed bill). The worker needs:

```bat
composer install
```

and SMTP values in `.env`:

```ini
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=orders@example.com
MAIL_FROM_NAME="Dhaka Hotel"
```

Without SMTP or PHPMailer the admin shows the queue state honestly and the worker leaves notifications untouched. No email is ever reported as sent when it was not.

## Payments, POS and receipts

- A bill generated in the POS is **pending and unpaid**. Printing it does not mark it paid.
- **Complete Payment** records cash, cash-on-delivery (COD), bKash, Nagad, card or another manual method. Non-cash methods require a reference. For COD no money is recorded and the receipt shows the balance due.
- Partial cash is recorded as a partial payment; the remaining balance stays visible.
- Payment attempts are idempotent (`pospay-<client key>`), so a retried request cannot charge twice.
- Receipts are available at `admin/receipt.php?id=…&paper=58|80|a4&rlang=bn|en`.

## PWA

- `manifest.webmanifest`, `service-worker.js`, `offline.php` and `install.php` are at the web root.
- The service worker caches public pages and static assets only. `admin/`, `api/`, `cart.php`, `checkout.php`, `order-success.php` and `track-order.php` are never cached.
- Bump `APP_ASSET_VERSION` in `.env` after changing CSS/JS, then reload twice (or clear site data) to activate a new cache.

## Tests and checks

```bat
php tests\run.php
php -l admin\billing.php
php -l admin\api\pos.php
```

`tests/run.php` covers money parsing/formatting, bill rounding, discounts and tax, payment status rules, status transitions, validation helpers and dictionary parity (including a scan for missing `__()` keys). Database-backed checks require a configured `.env` and the seeded demo database.

## Deployment checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_DEMO_MODE=false`
- [ ] `DB_*` and `MAIL_*` set in `.env`; `.env` never committed or served
- [ ] `APP_BASE_PATH` matches the deployment path; `APP_ASSET_VERSION` bumped
- [ ] HTTPS enabled and `SESSION_SECURE=true`
- [ ] `.htaccess` honoured (`AllowOverride All`) or equivalent server rules applied
- [ ] `vendor/` installed (`composer install --no-dev --optimize-autoloader`) if email is used
- [ ] Migrations applied; demo data not seeded on production
- [ ] Writable `uploads/menu/` and `storage/` for the web user
- [ ] Change the demo password and every seeded credential
- [ ] Cron/Task Scheduler runs `php scripts/process_email_queue.php`
- [ ] Backups scheduled for the database and `uploads/`

## Known limitations

- No online payment gateway. bKash/Nagad/card/other are recorded manually with a reference; refunds are bookkeeping records, not provider refunds.
- WhatsApp is click-to-chat only. There is no WhatsApp API integration.
- Email notifications need SMTP credentials and Composer-installed PHPMailer.
- Demo menu images are bundled SVG illustrations; uploaded images must be JPEG, PNG or WebP.
- The bundled `.htaccess` targets Apache. Nginx needs equivalent `location` rules for the blocked folders and the webmanifest MIME type.
- Browser QA on physical printers, iOS Safari and low-end Android devices is still recommended before go-live.
