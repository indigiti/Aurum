# Aurum modular layered architecture

- Public delivery: static Next.js export and thin PHP route dispatchers. No business logic in public PHP files.
- Presentation: Workspace and Replay views, chart lifecycle, formatting; API operations use injected application ports.
- Frontend application: workspace state, account and order workflows, injected API and quote-stream contracts. Domain models are separate. Browser network adapters live in infrastructure.
- Backend HTTP: private controllers handle request parsing, sessions, CSRF and response formatting.
- Backend application: trading orchestration, account access, challenge/calendar settings, risk runner and market service. Storage and market feed are ports; composition root wires the concrete adapters.
- Backend domain: engine, rules, calendar restrictions, payout rules, analytics and backtesting. No HTTP, storage or provider I/O.
- Infrastructure: JSON/MariaDB transactions, OKX market feed, email and AI provider. Config and construction are centralized in bootstrap.php. Existing root PHP include files forward to their layered implementations for compatibility.

The architecture gate in CI checks public route dispatch, selected prohibited domain/application dependencies and browser network boundaries. These checks are deliberately scoped static rules, not a formal proof of all transitive dependencies. The HTTP controllers still coordinate authentication/admin workflows; subsequent changes should extract these into application services where appropriate. PHP backend remains a modular monolith; no Node runtime, no new database requirement, no changes to DigiOps paths or URL. The architecture does not alter existing trade data or make risk polling tick-complete.
