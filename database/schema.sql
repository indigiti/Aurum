-- Per-account transactional state. Each account is serialized independently and row-locked.
-- Suitable for a controlled simulation MVP; normalize ledger/trades before high-volume production.
CREATE TABLE IF NOT EXISTS aurum_state_v2 (
  scope VARCHAR(90) NOT NULL PRIMARY KEY,
  payload JSON NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactionally maintained relational financial projections; v2 state remains execution authority.
CREATE TABLE IF NOT EXISTS aurum_accounts(scope VARCHAR(90) PRIMARY KEY,initial_cents BIGINT NOT NULL,balance_cents BIGINT NOT NULL,equity_cents BIGINT NOT NULL,phase INT NOT NULL,status VARCHAR(20) NOT NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS aurum_ledger(scope VARCHAR(90) NOT NULL,event_id VARCHAR(64) NOT NULL,event_type VARCHAR(40) NOT NULL,delta_cents BIGINT NOT NULL,balance_cents BIGINT NOT NULL,phase INT NOT NULL,event_ms BIGINT NOT NULL,reference_id VARCHAR(64),PRIMARY KEY(scope,event_id),INDEX(scope,event_ms)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS aurum_fills(scope VARCHAR(90) NOT NULL,fill_id VARCHAR(64) NOT NULL,position_id VARCHAR(64) NOT NULL,side VARCHAR(4) NOT NULL,units INT NOT NULL,entry_cents BIGINT NOT NULL,exit_cents BIGINT NOT NULL,pnl_cents BIGINT NOT NULL,closed_ms BIGINT NOT NULL,reason VARCHAR(32) NOT NULL,PRIMARY KEY(scope,fill_id),INDEX(scope,closed_ms)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS aurum_open_positions(scope VARCHAR(90) NOT NULL,position_id VARCHAR(64) NOT NULL,side VARCHAR(4) NOT NULL,units INT NOT NULL,entry_cents BIGINT NOT NULL,sl_cents BIGINT,tp_cents BIGINT,opened_ms BIGINT NOT NULL,PRIMARY KEY(scope,position_id)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS aurum_pending_orders(scope VARCHAR(90) NOT NULL,order_id VARCHAR(64) NOT NULL,order_type VARCHAR(16) NOT NULL,units INT NOT NULL,trigger_cents BIGINT NOT NULL,sl_cents BIGINT,tp_cents BIGINT,created_ms BIGINT NOT NULL,PRIMARY KEY(scope,order_id)) ENGINE=InnoDB;
