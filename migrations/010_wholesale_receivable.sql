-- Atacado: tipo da venda e preco de atacado por produto
ALTER TABLE sales ADD COLUMN sale_type ENUM('varejo','atacado') NOT NULL DEFAULT 'varejo';
ALTER TABLE products ADD COLUMN wholesale_price DECIMAL(10,2) NULL;
