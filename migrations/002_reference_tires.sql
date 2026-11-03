-- Estoque de referencia: apenas as medidas de exemplo (sem categoria). Quantidade e preco ficam em zero
-- ate serem informados em Estoque. Idempotente: nao duplica se o nome ja existir.
INSERT INTO products (name, category, condition_type, width, profile, rim, cost_price, price, stock)
SELECT CONCAT('Pneu ', t.w, '/', t.p, ' R', t.r), 'pneu', 'novo', t.w, t.p, t.r, 0, 0, 0
FROM (
  SELECT '175' AS w, '65' AS p, '14' AS r
  UNION ALL SELECT '185', '65', '15'
  UNION ALL SELECT '195', '55', '15'
  UNION ALL SELECT '185', '60', '15'
  UNION ALL SELECT '195', '55', '16'
  UNION ALL SELECT '205', '55', '16'
  UNION ALL SELECT '215', '65', '16'
  UNION ALL SELECT '215', '60', '17'
  UNION ALL SELECT '225', '60', '18'
  UNION ALL SELECT '235', '60', '18'
  UNION ALL SELECT '255', '55', '19'
  UNION ALL SELECT '265', '50', '20'
  UNION ALL SELECT '265', '65', '17'
  UNION ALL SELECT '265', '60', '18'
  UNION ALL SELECT '275', '65', '18'
) t
WHERE NOT EXISTS (SELECT 1 FROM products x WHERE x.name = CONCAT('Pneu ', t.w, '/', t.p, ' R', t.r));
