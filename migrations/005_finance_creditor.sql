-- Financeiro: tipo da despesa (despesa/imposto) e repasses ao credor feitos pelos vendedores.
ALTER TABLE accounts_payable ADD COLUMN kind VARCHAR(10) NOT NULL DEFAULT 'despesa';
UPDATE accounts_payable SET kind = 'imposto' WHERE UPPER(COALESCE(category, '')) LIKE '%IMPOSTO%';
ALTER TABLE sale_payments ADD COLUMN to_creditor TINYINT(1) NOT NULL DEFAULT 0;
CREATE TABLE creditor_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_id INT UNSIGNED NULL,
  sale_id INT UNSIGNED NULL,
  sale_payment_id INT UNSIGNED NULL UNIQUE,
  amount DECIMAL(10,2) NOT NULL,
  paid_on DATE NOT NULL,
  note VARCHAR(200) NULL,
  source VARCHAR(10) NOT NULL DEFAULT 'manual',
  created_by VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_creditor_paid (paid_on),
  CONSTRAINT fk_creditor_seller FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE SET NULL,
  CONSTRAINT fk_creditor_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  CONSTRAINT fk_creditor_pay FOREIGN KEY (sale_payment_id) REFERENCES sale_payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
