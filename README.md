# Aurum

A gold-only simulated prop-trading platform. Internal display symbol `XAUUSD` uses the OKX `XAU-USDT-SWAP` perpetual as a **USDT-denominated market proxy**, not spot XAU/USD.

## Stack and hosting
- Frontend: Next.js static export, React, TypeScript, Tailwind CSS, Lightweight Charts, Lucide.
- Backend: PHP 8.2 REST API.
- Staging: JSON files with per-user locks; no database required.
- Production adapter: MariaDB/InnoDB, one transactionally locked state record per account.
- Hosting: Cloudways PHP application. Node is used in CI to build static files only; no Node or Python server runs on Cloudways.

## Features in this MVP
- OKX public WebSocket ticker and REST candles.
- Market orders and pending limit/stop orders in simulated mode.
- Bid/ask fills, margin/exposure checks, SL/TP, position closing.
- Configurable two-phase challenge rules and risk limits.
- Registration/login with PHP sessions, CSRF protection and per-email auth throttling.
- Trader dashboard, challenge status, journal and performance analytics.
- Historical SMA crossover backtest with next-candle execution and explicit spread/slippage/fee assumptions.
- Admin allowlist, trader overview, account lock/unlock/reset and challenge rule settings.
- JSON staging storage and a one-time JSON-to-MariaDB migration script.
- CI checks, static export, Chromium browser tests, MariaDB adapter/migration integration tests and Cloudways ZIP packaging.

## Local build
```bash
cd frontend
npm install
AURUM_BASE_PATH=/aurum npm run build
cd ..
bash deployment/package.sh
```

## Tests
```bash
php tests/engine.php
bash tests/api-smoke.sh
# Run against a local MariaDB test database with DB_DSN/DB_USER/DB_PASSWORD set
php tests/mariadb-smoke.php
cd frontend
npx playwright install chromium
npm run test:e2e
```

## Cloudways / DigiOps
DigiOps deployment settings: repository `indigiti/Aurum`, branch `main`, artifact `digiops-release`, public URL `/aurum/`, public path `public_html/aurum/`, private path `private_html/aurum/`. CI packages the static frontend and PHP APIs in `release/public/`, the private PHP backend and schema in `release/private/`, and source metadata in `release/RELEASE.json`. API discovery is automatic; no Cloudways shell is needed. Existing private configuration and storage are not packaged. See [deployment/INSTALL.md](deployment/INSTALL.md). A manual Cloudways ZIP is also available.

## Market data details
- OKX instrument: `XAU-USDT-SWAP`.
- REST: `https://www.okx.com/api/v5/market/ticker` and `/api/v5/market/candles`.
- WebSocket: `wss://ws.okx.com/ws/v5/public` (port 443; OKX retired public port 8443 in October 2026).
- Public market data only; no API keys and no OKX order placement.
- OKX public instrument metadata reports contract value in XAU; Aurum reads the current `ctVal`, lot size and tick size at runtime. The current contract specification is 0.001 XAU per contract (1,000 contracts per ounce). The simulator uses troy ounces and defines 100 oz as one simulated lot; these are not OKX contract counts.
- The contract is USDT quoted. The virtual USD ledger assumes USDT/USD parity; it does not perform a currency conversion.

## Critical operational limits
This is a **simulated MVP**, not a commercial prop firm or brokerage. The PHP engine evaluates pending orders, stops, targets and drawdown only when the API observes a fresh server quote. It cannot guarantee unattended stop execution while the app receives no requests. Before paid evaluations or funded accounts, add a continuously running server-side market/execution worker, normalized ledger and order tables, full audit trail, stronger identity controls, KYC/AML and jurisdictional/legal review. Do not treat simulated payout metrics as actual money owed.

MariaDB's current adapter stores a per-account JSON payload under a row lock. It is suitable for controlled MVP testing, not high-volume production; normalize accounts, orders, fills, positions, ledger, snapshots and audit records before scale.
