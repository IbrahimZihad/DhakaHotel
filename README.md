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
- **Security** — CSRF tokens on every write, escaping on output, prepared statements, upload content inspection, CSP and other headers, secrets only in `.env`, login lockout that costs the same for an unknown address, password changes that revoke every other admin session, and absolute URLs built only from `APP_URL`/`TRUSTED_HOSTS` so a spoofed `Host` header cannot reach the sitemap or social tags.
- **Hosting** — a `Dockerfile` (`php:8.5-apache`) and a `render.yaml` blueprint, so the audited `.htaccess` keeps protecting the app on a managed host instead of being silently dropped.

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

Five wrong passwords in a fifteen-minute window lock both that address and the requesting IP: the sixth attempt is refused before the credentials are read, so the real password is rejected too. The window expires on its own; clearing it early is a database edit:

```sql
DELETE FROM admin_login_attempts WHERE success = 0;
```

## Hosting the database on Aiven (or any managed MySQL)

The app talks to MySQL over PDO with the same code whether the server is on
`127.0.0.1` or in a cloud region - only `.env` changes.

1. **Point `.env` at the service.** Aiven gives you a hostname, a non-standard
   port (usually `27437` for MySQL) and `avnadmin`:

   ```ini
   DB_HOST=mysql-xxxxxx-dhakahotel.b.aivencloud.com
   DB_PORT=27437
   DB_NAME=defaultdb
   DB_USER=avnadmin
   DB_PASSWORD=<secret from the Aiven console>
   DB_SSL_MODE=required
   DB_SSL_CA=certs/ca.pem
   ```

2. **Download the Project CA certificate** from the Aiven console (Service →
   CA Certificate) and save it as `certs/ca.pem`. A relative `DB_SSL_CA` is
   resolved from the project root, so the same value works under Apache, from the
   CLI and from a scheduled worker. `certs/` is git-ignored and blocked from the
   web by the bundled `.htaccess`.

3. **Migrate, then check:**

   ```bat
   php database\migration_runner.php migrate
   php database\migration_runner.php status
   ```

   An empty schema is not a working site: every page returns 200 while the
   storefront lists no dishes and `menu-item.php` returns 404, so migrate and load
   data in the same sitting.

4. **Move the data.** `database/seed_runner.php seed` fills a fresh database with
   demo dishes; it will not reproduce a menu the owner has edited. To carry an
   existing database across, copy the rows with PDO (the bundled
   `scripts/port_database.php` does exactly this and refuses to run unless every
   destination table is empty, so it cannot overwrite cloud data):

   ```bat
   php scripts\port_database.php --source=local --tables=admins,restaurant_settings,categories,menu_items,customers,orders,order_items,order_status_history,payments,reviews
   php database\seed_runner.php seed
   ```

   The seeder is safe to run afterwards or repeatedly - every insert is keyed
   (`INSERT IGNORE`, or a pre-check on `email` / `demo_key` / `order_number`), so
   it reports `26 kept` rather than re-adding settings and never touches an
   existing admin's password. On a database that already has real content it still
   adds its own demo rows: 5 extra categories, 15 demo dishes and one demo order.
   Those dishes are visible on the public menu (only the aggregate rating in
   `includes/seo.php` filters `is_demo`), so delete or unlist them before launch.

   The XAMPP `mysql.exe` client is MariaDB's and cannot authenticate to MySQL 8.4:
   it fails with `Plugin caching_sha2_password could not be loaded`. Use the app's
   own PDO connection for anything that has to reach the cloud server.

5. **Verification depends on your clock.** `DB_SSL_MODE=required` makes the
   driver check the server certificate against the CA *and* the app confirms
   after the handshake that the session really is encrypted. A machine clock that
   is hours behind real time rejects a freshly issued CA with the misleading
   `Cannot connect to MySQL using SSL` - the Aiven Project CA is stamped with the
   minute it was created, so a slow clock fails the handshake until real time
   passes that stamp. Turn on automatic time sync before debugging the certificate.

   `DB_SSL_VERIFY_SERVER_CERT=false` is available as a stopgap. The connection
   stays encrypted, but the app no longer proves it is talking to Aiven - that is
   a man-in-the-middle opening, so it must not be the production setting.

6. **Server-side differences from XAMPP.** Aiven's MySQL runs
   `ANSI_QUOTES, PIPES_AS_CONCAT, ONLY_FULL_GROUP_BY, STRICT_ALL_TABLES`, while
   XAMPP's MariaDB does not. In practice: `"..."` is an identifier and never a
   string, `||` concatenates instead of meaning OR, every selected column must be
   aggregated or grouped, and bad values raise errors instead of being coerced.
   The bundled queries follow all four rules, but keep them in mind when adding
   one - and test any new SQL against the cloud server, not only against XAMPP.

   MySQL 8 also returns `information_schema` column names in upper case
   (`TABLE_NAME`), where MariaDB returned them in lower case, so a query that
   reads that schema has to use the new spelling. The cloud database's own default
   collation is `utf8mb4_0900_ai_ci` while the migrations pin every table to
   `utf8mb4_unicode_ci`; keep that clause in any new table or a join across the
   two can fail with *Illegal mix of collations*.

7. **Lock it down on the provider side too.** Aiven accepts plaintext connections
   until *Require SSL* is enabled in the service parameters, so the app enforcing
   TLS is only half of it. Create a dedicated database user and database instead
   of using `avnadmin`, grant only `SELECT, INSERT, UPDATE, DELETE` (add `DDL`
   only for the migration window), and set the service's IP filter to the web
   host's address.

`database/reset_demo.php` refuses to run unless the database is named exactly
`dhaka_hotel_demo`, so it cannot wipe a cloud database - but `seed_runner.php` and
plain SQL will, so take a dump before changing data. XAMPP's `mysqldump` is the
MariaDB one and cannot authenticate to Aiven's MySQL 8.4
(`caching_sha2_password`), so back up on the provider side (Aiven service backups)
or with a MySQL 8 client:

```bat
mysqldump -h <host> -P <port> -u <user> -p --default-character-set=utf8mb4 --no-data <db> > schema.sql
```

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

:: copy content tables into the database .env points at (target must be empty)
php scripts\port_database.php --source=local

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

## Docker image

The app ships a production image so it can run on any Docker host, Render included.

| File | What it does |
| --- | --- |
| `Dockerfile` | `php:8.5-apache` + `pdo_mysql`, `mbstring`, `zip`; hardened PHP and Apache config; `composer install --no-dev --optimize-autoloader` at build time; deletes `.git/` and `tests/` from the image |
| `.dockerignore` | keeps `.env`, `certs/`, `.git/`, `storage/`, `uploads/menu/*`, `*.sql` and `vendor/` out of the build context |
| `scripts/docker-entrypoint.sh` | writes the `DB_SSL_CA_PEM` secret to `/etc/dhaka/ca.pem` (640, `root:www-data`) and points `DB_SSL_CA` at it, moves Apache to `$PORT`, repairs `uploads/` and `storage/` ownership |
| `render.yaml` | Render blueprint: the web service plus the email-queue cron |

`php:8.5-apache` (mod_php) is a deliberate choice, not an oversight. Every access rule in this project lives in `.htaccess` — the blocked folders, the dot-path deny, the "no PHP inside `uploads/`" guard, the security headers, the `application/manifest+json` type — and only Apache reads that file. Render's *native* PHP runtime is Nginx + PHP-FPM, which ignores `.htaccess` entirely, so switching the service to the native runtime un-does the audit one rule at a time, silently. Pin the 8.5 base image for the same reason: `PASSWORD_DEFAULT` there is bcrypt cost 12, which matches the fixed-cost timing comparison in the login path.

Build and run it locally (Docker Desktop):

```bat
docker build -t dhaka-hotel:local .

:: there is no .env inside the image, so the app reads real environment variables
docker run -d --name dhaka-hotel -p 8080:8080 -e PORT=8080 --env-file C:\somewhere\prod.env dhaka-hotel:local
```

`--env-file` cannot carry a multi-line value, so give it `DB_SSL_CA_PEM` the way Render will — as a literal environment variable:

```bat
docker run -d --name dhaka-hotel -p 8080:8080 -e PORT=8080 --env-file C:\somewhere\prod.env ^
  -e DB_SSL_CA_PEM="-----BEGIN CERTIFICATE-----\n...\n-----END CERTIFICATE-----" dhaka-hotel:local
```

Checked against the live Aiven database with the CA delivered only as `DB_SSL_CA_PEM`: 11 public pages return 200 with no error text in the body; `storage/logs/`, `config/database.php`, `includes/functions.php`, `scripts/port_database.php`, `database/migrations/*.sql`, `.env`, `.git/config`, `tests/run.php`, `certs/ca.pem`, `vendor/autoload.php` and `composer.json` are all denied; `Server: Apache` with no `X-Powered-By`; assets cached, `service-worker.js` `no-cache`; Bangla renders correctly; admin login and session work.

Overriding the command on the same image runs the CLI the same way the Render cron service will, and the entrypoint still writes the CA first - `php database/migration_runner.php status` reported "10 migration file(s), 0 pending" against Aiven, and `php scripts/process_email_queue.php --limit=20` printed "Mailer state: unconfigured / Queued notifications were left untouched".

### Where the dish photos come from

`uploads/menu/` is git-ignored and excluded by `.dockerignore`, so a fresh clone or deploy starts with an empty uploads folder. The launch photos are therefore *also* present under `assets/images/menu/`, which is a tracked path (8.6 MB, not yet committed as of writing - they have to be pushed before Render can see them). This works without touching the database because `menu_image_url()` (`includes/functions.php:143`) resolves an `image_path` by looking in `uploads/<path>` first and then in `assets/images/<path>`; a value like `menu/beef-liver.webp` finds the local copy on XAMPP and the bundled copy in the container.

Verified in a container started with no `uploads/` volume at all: `/menu.php` returns 200 and every dish `src` resolves under `/assets/images/menu/...` with HTTP 200 and an `image/*` type - no placeholders, nothing broken.

Keep the two folders straight:

| | curated launch photos | anything an admin uploads after go-live |
| --- | --- | --- |
| lives in | `assets/images/menu/`, committed to git | `uploads/menu/`, on the Render Disk |
| survives a deploy | yes - it is inside the image | only if the Disk is attached |
| how to change it | edit the file, commit, redeploy | the admin menu editor |

If you re-tune or swap a photo on XAMPP, copy it into `assets/images/menu/` as well before deploying, or the live site keeps serving the old one. Six items (চিকেন বার্গার, ফ্রেঞ্চ ফ্রাই, চিকেন স্যান্ডউইচ, বোরহানি, তাজা লেবুর শরবত, ফিরনি) still point at bundled SVG illustrations rather than photographs and render as illustrations everywhere; replace them from the admin once the real dishes are photographed.

Two things the image deliberately does **not** do:

- **It never migrates on boot.** An automatic `migrate` in the entrypoint would run against whatever `DB_*` the environment points at, including a production database, on every container start and on every cron tick. Run it yourself from a throwaway container:

  ```bat
  docker run --rm --env-file C:\somewhere\prod.env dhaka-hotel:local ^
    php database/migration_runner.php status
  ```

- **It does not seed demo data.** `APP_DEMO_MODE=false` is the default in `config/app.php`, so a container without a `.env` cannot serve demo content by accident.

## Deploying to Render

The image is Apache, so the `.htaccess` rules that were audited stay in force on Render.

1. Push the branch containing `Dockerfile`, `.dockerignore`, `scripts/docker-entrypoint.sh` and `render.yaml` to GitHub. Nothing in `render.yaml` holds a credential — the `sync: false` keys are created empty for you to fill in the dashboard.
2. Render dashboard → **Add New → Blueprint** → pick the repo. Render reads `render.yaml` and shows both services it will create.
3. Before the first deploy, edit these values in the dashboard (they are blank in the blueprint on purpose): `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_SSL_CA_PEM`, `OWNER_EMAIL`, `OWNER_WHATSAPP`, `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`. The web service *and* the cron service each need the `DB_*` and `MAIL_*` set — cron jobs are separate services and inherit nothing.
4. Replace `REPLACE-WITH-YOUR-RENDER-DOMAIN` in `APP_URL` and `TRUSTED_HOSTS`. `APP_URL` feeds the canonical tag, `og:url`, `og:image` and the sitemap `<loc>` entries; if it still says `REPLACE-...` the site publishes a domain nobody owns. Add the custom domain to `TRUSTED_HOSTS` when the restaurant gets one, or a spoofed `Host` header is rejected from URL generation.
5. Paste the **whole** Aiven Project CA PEM — `-----BEGIN CERTIFICATE-----` through `-----END CERTIFICATE-----`, newlines included — as the `DB_SSL_CA_PEM` value. The entrypoint turns it back into `/etc/dhaka/ca.pem`, so `certs/` never has to be committed or baked into the image.
6. Deploy. Render builds the image, and the container listens on the port it is given; `PORT` is set by Render automatically, and the entrypoint rewrites Apache's `Listen` and `<VirtualHost>` to match.
7. Apply the schema (the one-off container command above), then load the content. `scripts/port_database.php` reads the local XAMPP database and writes to whatever `.env` points at, so run it from the machine that has both - not from the container, which has no local MySQL to copy from. Never seed demo data into the live database.
8. Watch **Shell** in the service for the first minute after deploy. `require_secure_transport` on Aiven and a wrong `DB_SSL_*` combination both surface as a connection failure with nothing on the page.

### What costs money, and what to expect

- **`disks:` needs a paid plan.** The blueprint mounts `uploads/` and `storage/` on Render Disks. Without them every deploy recreates the filesystem, so the dish photos admins upload vanish while their `image_path` rows survive and the menu quietly falls back to `placeholder.svg`. `starter` web service is $7/month and each Disk is billed separately; decide before you rely on uploads.
- **A Disk pins the service to a single instance.** Do not scale `dhaka-hotel-web` out; two containers with one Disk is not supported, and a split would also break the session and the POS's single-screen assumptions.
- **Sessions live inside the container.** Every deploy logs the admin out and empties every guest cart. That is invisible at low traffic but worth knowing before a lunch service.
- **Render's default outbound IPs are shared and can change.** If you enable an IP allow-list on Aiven, you must add Render's egress ranges or pay for dedicated static outbound IPs; plain `Require SSL` alone needs neither.
- **The email queue only drains if the cron service deploys too.** It runs `php scripts/process_email_queue.php --limit=20` every 10 minutes; with SMTP unset the worker leaves the queue alone and the admin says so honestly.
- **Free-tier spin-down** adds a multi-second cold start to the first request after idleness. A restaurant taking orders should not live on a sleeping instance; `starter` avoids it.

## Deployment checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_DEMO_MODE=false`
- [ ] `APP_URL` set to the public origin, or `TRUSTED_HOSTS` listing every real hostname; an unlisted `Host` header then cannot reach the sitemap, canonical or social tags
- [ ] `DB_*` and `MAIL_*` set in `.env`; `.env` never committed or served. A container has no `.env` - set the same names as real environment variables, which take priority over the file
- [ ] `.git/` not deployed (`.htaccess` denies dot-paths, but a repository folder should not be in the document root at all)
- [ ] `APP_BASE_PATH` matches the deployment path; `APP_ASSET_VERSION` bumped
- [ ] HTTPS enabled and `SESSION_SECURE=true`
- [ ] `.htaccess` honoured (`AllowOverride All`) or equivalent server rules applied - on Docker/Render the `php:8.5-apache` image satisfies this, Render's native PHP runtime does not
- [ ] `ServerTokens Prod` and `ServerSignature Off` in the server config, if you can edit it - the bundled `.htaccess` already removes `X-Powered-By` and the directory listings
- [ ] `vendor/` installed (`composer install --no-dev --optimize-autoloader`) if email is used
- [ ] Migrations applied; demo data not seeded on production
- [ ] Writable `uploads/menu/` and `storage/` for the web user, on storage that outlives a deploy (volume, Render Disk, or a host path) - otherwise uploaded dish photos disappear at the next release while their database rows survive
- [ ] Change the demo password and every seeded credential
- [ ] Cron/Task Scheduler runs `php scripts/process_email_queue.php`
- [ ] Backups scheduled for the database and `uploads/`

## Known limitations

- No online payment gateway. bKash/Nagad/card/other are recorded manually with a reference; refunds are bookkeeping records, not provider refunds.
- WhatsApp is click-to-chat only. There is no WhatsApp API integration.
- Email notifications need SMTP credentials and Composer-installed PHPMailer.
- Demo menu images are bundled SVG illustrations; uploaded images must be JPEG, PNG or WebP.
- The bundled `.htaccess` targets Apache. Nginx needs equivalent `location` rules for the blocked folders and the webmanifest MIME type, which is why the Docker image and the Render blueprint both use `php:8.5-apache` rather than an Nginx+PHP-FPM base.
- Browser QA on physical printers, iOS Safari and low-end Android devices is still recommended before go-live.
