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
- CI checks, static export and Cloudways ZIP packaging.

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
```

## Cloudways
See [deployment/INSTALL.md](deployment/INSTALL.md). Copy the built `aurum/public_html` contents to `public_html/aurum/` and the private `aurum-private/` folder beside `public_html`. Copy `aurum-private/backend/config.example.php` to `aurum-private/config/local.php` and set private configuration. Keep backend and storage outside the document root.

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
