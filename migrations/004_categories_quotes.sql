-- Categorias de itens (pneu/roda/servico), categoria da troca, configuracoes da loja e orcamentos.
ALTER TABLE sale_items ADD COLUMN category VARCHAR(20) NOT NULL DEFAULT 'pneu';
UPDATE sale_items SET category = 'servico' WHERE product_id IS NULL;
UPDATE sale_items i JOIN products p ON p.id = i.product_id SET i.category = p.category;
ALTER TABLE sale_payments ADD COLUMN trade_category VARCHAR(10) NULL;
UPDATE sale_payments SET trade_category = 'pneu' WHERE method = 'troca';
CREATE TABLE settings (
  k VARCHAR(60) PRIMARY KEY,
  v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE quotes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token CHAR(40) NOT NULL UNIQUE,
  customer_name VARCHAR(160) NOT NULL,
  customer_phone VARCHAR(30) NULL,
  customer_email VARCHAR(160) NULL,
  customer_tax_id VARCHAR(20) NULL,
  customer_car VARCHAR(120) NULL,
  seller_id INT UNSIGNED NULL,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  valid_until DATE NULL,
  payment_terms VARCHAR(255) NULL,
  notes TEXT NULL,
  created_by VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_quotes_created (created_at),
  CONSTRAINT fk_quotes_seller FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE quote_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quote_id INT UNSIGNED NOT NULL,
  category VARCHAR(20) NOT NULL DEFAULT 'pneu',
  description VARCHAR(200) NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_qitems_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
