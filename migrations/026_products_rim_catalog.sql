-- Roda do estoque ligada a uma roda do catalogo do provador (mesmo nome e mostra preco e estoque no provador)
ALTER TABLE products ADD COLUMN rim_catalog_id INT UNSIGNED NULL AFTER image_path, ADD INDEX idx_products_rim_catalog (rim_catalog_id)
