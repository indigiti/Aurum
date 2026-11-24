# Aurum architecture and operational boundaries

## Deployment
Next.js is built to static files in GitHub Actions. Cloudways serves the exported frontend and PHP 8.2 API. No Node.js or Python runtime is used on Cloudways. DigiOps deploys `release/public/` to `public_html/aurum/` and overlays `release/private/` onto `private_html/aurum/`. Private configuration and JSON state remain outside the document root and are excluded from the release. The public API discovers this private runtime without server environment changes.

## Market data
Internal display symbol XAUUSD maps to OKX `XAU-USDT-SWAP`. This is a USDT-quoted gold perpetual proxy, not spot XAU/USD. The simulator assumes 1 USDT = 1 USD for virtual ledger calculations. The browser uses OKX public WebSocket tickers for chart updates; PHP retrieves REST quotes for server-side simulation. A quote must be fresh before a trade request is accepted.

## Simulated execution
All orders are virtual. Market and pending orders are simulated from OKX bid/ask. A position unit is one troy ounce, or 0.01 standard lot. Stops, targets, daily loss and max drawdown are processed only when PHP observes a fresh quote through an API request. This is request-driven and is not a continuously running matching engine. Do not use this build for paid evaluations, real funded accounts or unattended risk enforcement.

## Persistence
Staging JSON uses per-user files, atomic rename and file locks; it is single-host and low-concurrency. MariaDB uses one transactionally locked JSON state payload per account, which isolates concurrent users but is not a normalized trading ledger. Before commercial/high-volume production, migrate accounts, orders, fills, ledger entries, risk events and audit logs to normalized InnoDB tables and run concurrency/recovery tests.

## Security
Admin access is granted only to addresses in the private `AURUM_ADMIN_EMAILS` configuration. The staging registration flow is a prototype: add email verification, password reset, rate limiting, MFA, abuse controls, privacy retention and formal security review before public launch. Never place `aurum-private` inside `public_html`.
