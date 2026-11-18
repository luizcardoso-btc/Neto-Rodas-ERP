-- Emprestimos de pneus/rodas entre a loja e empresas parceiras
CREATE TABLE loans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  direction ENUM('in','out') NOT NULL,          -- in = pegamos emprestado, out = emprestamos
  partner VARCHAR(120) NOT NULL,
  partner_phone VARCHAR(30) NULL,
  product_id INT UNSIGNED NULL,
  description VARCHAR(200) NOT NULL,
  quantity INT NOT NULL,
  unit_value DECIMAL(10,2) NULL,
  stock_moved TINYINT(1) NOT NULL DEFAULT 0,
  loan_date DATE NOT NULL,
  due_date DATE NULL,
  notes VARCHAR(255) NULL,
  status ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
  created_by VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_loans_status (status, direction),
  CONSTRAINT fk_loans_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE loan_settlements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  loan_id INT UNSIGNED NOT NULL,
  kind ENUM('devolucao','pagamento') NOT NULL,
  quantity INT NOT NULL,
  amount DECIMAL(10,2) NULL,
  method VARCHAR(20) NULL,
  sale_id INT UNSIGNED NULL,
  payable_id INT UNSIGNED NULL,
  settle_date DATE NOT NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ls_loan FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE sales MODIFY source ENUM('pdv','mobile','emprestimo') NOT NULL DEFAULT 'pdv';
