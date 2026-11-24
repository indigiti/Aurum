# Cloudways staging installation
The artifact is built for /aurum. Build with AURUM_BASE_PATH="" if hosting at domain root. No Node.js or Python server required.
- Upload the contents of the artifact's public_html to the target document root or /aurum folder. Install backend and database outside the entire public document root; do not put them inside a publicly accessible subdirectory. For /aurum, edit API require paths to the private sibling directory matching your Cloudways layout. Keep private-storage next to backend outside the webroot. PHP 8.2+ with curl is required.
- Protect staging with HTTPS and Cloudways access restrictions; registration is a prototype and lacks production rate limiting, verification and recovery. Disable reverse proxy/cache for all API URLs.
- Set STORAGE_DRIVER=json (default) and APP_ENV=staging. Verify PHP can write the private storage directory.
- Test /api/market.php?action=ticker and /api/trading.php, then register a virtual account. Market feed may be unavailable due to regional/network access. Orders are blocked when stale.
- Production compatibility: import database/schema.sql into MariaDB using the database manager, configure APP_ENV=production, STORAGE_DRIVER=mariadb, DB_DSN=mysql:host=...;dbname=...;charset=utf8mb4, DB_USER and DB_PASSWORD using private environment configuration. No silent JSON fallback.
- Existing JSON state migration is not yet implemented. Do not switch an occupied deployment to MariaDB without a validated migration. MariaDB compatibility uses a serialized transaction row, not a scalable normalized ledger.
- No continuous worker: SL/TP and drawdown are evaluated on observed request-time server quotes only. No tick history, pending orders, fees, swaps, payment or real execution. Do not run paid challenges.
