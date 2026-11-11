-- Classificacao ATACADO no estoque (alem de novo e usado)
ALTER TABLE products MODIFY condition_type ENUM('novo','usado','atacado') NOT NULL DEFAULT 'novo';
