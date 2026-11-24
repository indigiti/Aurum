-- Initial compatibility store; normalize ledger/accounts before commercial scale.
CREATE TABLE IF NOT EXISTS aurum_state (id INT PRIMARY KEY, payload LONGTEXT NOT NULL CHECK (JSON_VALID(payload))) ENGINE=InnoDB;
INSERT IGNORE INTO aurum_state(id,payload) VALUES (1,'{"users":{},"accounts":{}}');
