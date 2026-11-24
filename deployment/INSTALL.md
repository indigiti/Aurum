# Aurum Cloudways deployment

## Package layout
- Copy the contents of `aurum/public_html/` into Cloudways document root `public_html/aurum/`.
- Copy `aurum-private/` into the application root beside `public_html`, never into the public document root.
- Copy `aurum-private/backend/config.example.php` to `aurum-private/config/local.php`, create the `config` directory if needed, and set private values there. Configure `AURUM_PRIVATE_PATH` only if the default sibling path is different. PHP 8.2+, curl, PDO and PDO_MySQL are required.
- Build uses Node.js in GitHub Actions only. Cloudways does not run Node or Python for this application.

## Database-free staging
- In `config/local.php`, set `APP_ENV=staging`, `STORAGE_DRIVER=json`, and `AURUM_ADMIN_EMAILS` to the exact administrator email addresses.
- Ensure PHP can create `aurum-private/private-storage` with permissions restricted to the application user.
- Keep staging behind HTTPS and Cloudways access restrictions. The demo registration is not production-ready identity management.
- Exclude `/aurum/api/*` from page caching. API responses are private/no-store except public market candles.
- Test `/aurum/api/market.php?action=ticker`, `/aurum/api/market.php?action=candles`, registration, orders, SL/TP and account risk rules.

## MariaDB cutover
- Create a MariaDB database and import `aurum-private/database/schema.sql`.
- In `config/local.php`, set `APP_ENV=production`, `STORAGE_DRIVER=mariadb`, `DB_DSN=mysql:host=HOST;dbname=DATABASE;charset=utf8mb4`, `DB_USER`, `DB_PASSWORD`, and `AURUM_ADMIN_EMAILS`.
- Back up the JSON private-storage directory. Run the one-time migration from a trusted shell: `php aurum-private/backend/migrate-json-to-mariadb.php`; it reads the private configuration file. It inserts missing account scopes, verifies existing rows, refuses conflicting overwrites and retains JSON source files.
- Switch `STORAGE_DRIVER=mariadb` only after migration verification. Never fall back to JSON in production.

## Operational limitations
- This MVP evaluates orders, stops, targets and drawdown when the PHP API observes a fresh OKX REST quote. It does not guarantee unattended stop execution between requests. A dedicated persistent worker and server-side market stream are mandatory before any funded or paid challenge.
- OKX XAU-USDT-SWAP is a USDT-quoted gold perpetual proxy, not spot XAU/USD. Aurum fetches its contract specification from OKX; the current contract value is 0.001 XAU per contract. Simulator positions are denominated in troy ounces (100 oz per simulated lot), not exchange contracts. The simulator assumes USDT/USD parity for its virtual USD ledger. Validate data display rights and jurisdiction before public launch.
- No payments, real broker execution, actual payouts, identity verification, KYC/AML, support tooling or commercial challenge sales are enabled.
- JSON is single-host staging storage. MariaDB uses one transactionally locked JSON payload per user for this MVP; high-volume production requires normalized account, order, fill, ledger and audit tables.
