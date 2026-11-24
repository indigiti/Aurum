-- Per-account transactional state. Each account is serialized independently and row-locked.
-- Suitable for a controlled simulation MVP; normalize ledger/trades before high-volume production.
CREATE TABLE IF NOT EXISTS aurum_state_v2 (
  scope VARCHAR(90) NOT NULL PRIMARY KEY,
  payload JSON NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
