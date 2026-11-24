# Aurum

Gold-only simulated prop-trading platform. Target: static Next.js frontend, PHP API on Cloudways, JSON staging, MariaDB production. No Node.js runtime. No real broker execution, deposits or payouts.

## Market data

Aurum's internal symbol `XAUUSD` uses the OKX `XAU-USDT-SWAP` gold perpetual as a **USDT-denominated proxy**, not spot XAU/USD. No currency conversion is implied. Public market data only; no OKX credentials or order endpoints. Confirm display/redistribution rights and regional access before public deployment.
