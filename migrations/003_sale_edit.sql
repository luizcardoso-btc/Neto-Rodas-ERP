-- Edicao de vendas pelo admin: linhas de servico (sem produto), vinculo da troca com o pneu usado e auditoria.
ALTER TABLE sale_items MODIFY product_id INT UNSIGNED NULL;
ALTER TABLE sale_payments ADD COLUMN product_id INT UNSIGNED NULL, ADD COLUMN trade_qty INT NOT NULL DEFAULT 1;
UPDATE sale_payments p
  JOIN (SELECT sale_id, MIN(product_id) pid, SUM(quantity) q FROM stock_movements
        WHERE type = 'in' AND reason LIKE 'Troca na venda%' GROUP BY sale_id HAVING COUNT(*) = 1) m ON m.sale_id = p.sale_id
  JOIN (SELECT sale_id FROM sale_payments WHERE method = 'troca' GROUP BY sale_id HAVING COUNT(*) = 1) t ON t.sale_id = p.sale_id
  SET p.product_id = m.pid, p.trade_qty = m.q
  WHERE p.method = 'troca';
CREATE TABLE sale_audit (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  username VARCHAR(60) NULL,
  summary VARCHAR(255) NOT NULL,
  snapshot MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_sale (sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
