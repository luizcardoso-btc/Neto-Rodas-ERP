-- Folha de pagamento: pagamentos semanais realmente feitos aos funcionarios
CREATE TABLE payroll_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  seller_id INT NULL,
  employee VARCHAR(120) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  week_start DATE NULL,
  paid_on DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_payroll_paid (paid_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
