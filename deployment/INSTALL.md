# Aurum Cloudways deployment

## DigiOps deployment (recommended)
Register the repository `indigiti/Aurum`, branch `main`, artifact `digiops-release`, URL `/aurum/`, public path `public_html/aurum/`, private path `private_html/aurum/`. Health URL: `https://stage.digiti.in/aurum/`; optionally use health path `/api/health.php` for backend and storage verification. Select a successful main-branch artifact and deploy in DigiOps. No Cloudways CLI, Node.js or Python runtime is required.

The artifact contains `public/`, `private/`, and `RELEASE.json` at its root. DigiOps publishes the public payload and overlays only deployable private code; existing configuration and JSON storage are not included in the artifact. The PHP API discovers `private_html/aurum/backend/bootstrap.php` automatically. A missing private runtime produces HTTP 503.

Manual ZIP fallback: copy the contents of `aurum/public_html/` to `public_html/aurum/` and the contents of `aurum-private/` to `private_html/aurum/` (or keep the legacy sibling `aurum-private/`). PHP 8.2+, cURL, PDO and PDO_MySQL are required for MariaDB. Copy the private `backend/config.example.php` into `config/local.php` only when you need custom settings; never overwrite an existing configuration.

## Database-free staging
- In `config/local.php`, set `APP_ENV=staging`, `STORAGE_DRIVER=json`, and leave `AURUM_ADMIN_EMAILS` empty until trusted admin identities are provisioned. The current prototype does not verify email ownership; do not expose allowlisted registration to untrusted users.
- Ensure PHP can create `private_html/aurum/private-storage` with permissions restricted to the application user.
- Keep staging behind HTTPS and Cloudways access restrictions. The demo registration is not production-ready identity management.
- Exclude `/aurum/api/*` from page caching. API responses are private/no-store except public market candles.
- Test `/aurum/api/market.php?action=ticker`, `/aurum/api/market.php?action=candles`, registration, orders, SL/TP and account risk rules.

## MariaDB cutover
- Create a MariaDB database and import `private_html/aurum/database/schema.sql`.
- In `config/local.php`, set `APP_ENV=production`, `STORAGE_DRIVER=mariadb`, `DB_DSN=mysql:host=HOST;dbname=DATABASE;charset=utf8mb4`, `DB_USER`, `DB_PASSWORD`, and `AURUM_ADMIN_EMAILS`.
- Back up the JSON private-storage directory. Run the one-time migration from a trusted shell: `php private_html/aurum/backend/migrate-json-to-mariadb.php`; it reads the private configuration file. It inserts missing account scopes, verifies existing rows, refuses conflicting overwrites and retains JSON source files.
- Switch `STORAGE_DRIVER=mariadb` only after migration verification. Never fall back to JSON in production.

## Operational limitations
- This MVP evaluates orders, stops, targets and drawdown when the PHP API observes a fresh OKX REST quote. It does not guarantee unattended stop execution between requests. A dedicated persistent worker and server-side market stream are mandatory before any funded or paid challenge.
- OKX XAU-USDT-SWAP is a USDT-quoted gold perpetual proxy, not spot XAU/USD. Aurum fetches its contract specification from OKX; the current contract value is 0.001 XAU per contract. Simulator positions are denominated in troy ounces (100 oz per simulated lot), not exchange contracts. The simulator assumes USDT/USD parity for its virtual USD ledger. Validate data display rights and jurisdiction before public launch.
- No payments, real broker execution, actual payouts, identity verification, KYC/AML, support tooling or commercial challenge sales are enabled.
- JSON is single-host staging storage. MariaDB uses one transactionally locked JSON payload per user for this MVP; high-volume production requires normalized account, order, fill, ledger and audit tables.
