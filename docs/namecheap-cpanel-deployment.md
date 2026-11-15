# Namecheap cPanel deployment

This guide prepares Garwin AI for a Namecheap shared-hosting deployment. It does
not deploy the application or make the current unfinished product modules
production-ready.

## 1. Confirm the hosting account

- Confirm the plan provides cPanel, PHP 8.2 or newer, MySQL/MariaDB, outbound
  HTTPS/cURL, and the PHP `pdo_mysql`, `curl`, `mbstring`, `openssl`, `session`,
  and `simplexml` extensions.
- Use cPanel **Domains** to set the site's document root to the application's
  `public/` directory. Do not serve the project root, `app/`, `database/`,
  `vendor/`, or `.env`.
- If the plan does not allow the site's document root to point to `public/`,
  confirm a supported private-code/public-web-root arrangement with Namecheap
  before uploading the application.

## 2. Create the production database

In cPanel **MySQL Database Wizard**, create a database and a dedicated database
user, then grant that user all privileges on that database. cPanel commonly
adds the account name as a prefix; use the complete database and username shown
in cPanel. The usual database host is `localhost`, but use the host shown by
Namecheap if it differs.

Set the production environment values in the project-root `.env` file:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.garwinai.com
SESSION_SECURE_COOKIE=true
APP_KEY=base64:<generated-32-byte-key>

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=<full-cpanel-database-name>
DB_USERNAME=<full-cpanel-database-user>
DB_PASSWORD=<database-password>
```

Generate `APP_KEY` once with `php bin/console key:generate`, store the output in
`.env`, and keep a protected backup. Do not change it after saving encrypted
integration credentials. Keep `.env` outside the document root and do not put
secrets in Git or a public web directory.

## 3. Configure cPanel Web Disk storage

In cPanel **Web Disk**, create a dedicated account restricted to a private
directory outside `public_html` and the application's `public/` directory.
Use that account's HTTPS WebDAV address and credentials; do not use the main
cPanel account password. If the Web Disk account is already restricted to the
storage directory, leave `APP_STORAGE_WEBDAV_ROOT` empty. Otherwise set it to a
safe relative directory within the Web Disk account.

```dotenv
APP_STORAGE_DRIVER=webdav
APP_STORAGE_WEBDAV_URL=https://<web-disk-host>:2078/
APP_STORAGE_WEBDAV_USERNAME=<web-disk-user>
APP_STORAGE_WEBDAV_PASSWORD=<web-disk-password>
APP_STORAGE_WEBDAV_ROOT=
```

Use the exact HTTPS endpoint shown by cPanel/Namecheap; the hostname and port
may differ from the example. Garwin AI creates its own `ACSD-73h5ZXp1Q` data
folder and a `UCSD-<user-id>-001` folder for every new account there. Each
account starts with `System`, `Documents`, `Media`, `Apps`, `Widgets`, and
`Themes`, and receives a 1 GiB application quota enforced when files are
uploaded or deleted. WebDAV requests verify TLS certificates,
require cURL and SimpleXML, and use the restricted Web Disk account. Do not put
the Web Disk folder under a public web root.

MySQL remains the source of truth for structured records such as calendar
events, account data, and AI Assistant conversation history. Web Disk stores
user files and attachments. Configure the encrypted OpenAI API key from the
Super Admin integration settings before users send assistant messages. For an
existing installation, move and verify existing user folders before switching
`APP_STORAGE_DRIVER`; the launch checklist will not treat missing remote user
folders as ready.

## 4. Prepare and deploy the application

Build frontend assets and install PHP dependencies before deployment:

```sh
npm ci
npm run build
composer install --no-dev --optimize-autoloader
```

Upload the application so `public/` is the only web-accessible directory. Use
cPanel **Git Version Control** if it is available and appropriate for the
account, or deploy through SFTP/File Manager. Do not expose `.git`, `.env`,
source files, or dependency manifests through the web root. The included
`public/.htaccess` routes application URLs to `public/index.php`; Apache
`mod_rewrite` and `.htaccess` overrides must be enabled.

Use a private cPanel **Terminal**/SSH session, when provided, to run:

```sh
php bin/console migrate
```

Set `GARWIN_ADMIN_EMAIL` and a strong, temporary `GARWIN_ADMIN_PASSWORD` in the
ignored project-root `.env`, then provision the first Super Admin:

```sh
php bin/console admin:bootstrap
```

Remove `GARWIN_ADMIN_PASSWORD` from `.env` immediately afterward. If the hosting
plan does not provide a private way to run these commands, ask Namecheap about
SSH/Terminal access before launch; do not add a public setup URL or leave a
temporary bootstrap password deployed.

## 5. Use available cPanel services safely

- **MultiPHP Manager / PHP Selector:** select PHP 8.2+ and enable the required
  extensions, especially `pdo_mysql`, `curl`, and `simplexml`.
- **SSL/TLS / AutoSSL:** install and verify the certificate, then enforce HTTPS.
  Availability and certificate setup vary by plan.
- **Backups / JetBackup (if included):** schedule and test backups for MySQL
  and Web Disk data separately. A database backup alone does not include user
  files.
- **Cron Jobs:** configure only for a documented maintenance command; this app
  currently has no scheduled job that must be installed.
- **Email accounts / deliverability:** cPanel mail can host domain mailboxes,
  but the app's transactional email integration must still be configured and
  verified in Garwin AI Admin Settings.

## 6. Verify before launch

1. Browse the HTTPS domain and confirm routing, static assets, and secure
   session cookies.
2. Confirm MySQL migrations completed and the Super Admin can sign in.
3. In Admin Settings, verify the launch checklist reports PHP, MySQL,
   migrations, the `public/` web root, WebDAV storage, encryption key, HTTPS,
   secure cookies, and disabled debug as ready.
4. Configure and verify the required provider integrations there. Do not mark
   the platform launch-ready until every required item is verified.
5. Test a new account, file upload/download/delete, and restore procedures from
   backups before announcing production availability.
