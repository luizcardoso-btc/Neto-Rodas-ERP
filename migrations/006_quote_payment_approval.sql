-- Orcamento: forma de pagamento estruturada (cartao em Nx), aprovacao e vinculo com a venda do PDV.
ALTER TABLE quotes ADD COLUMN pay_method VARCHAR(12) NULL;
ALTER TABLE quotes ADD COLUMN installments TINYINT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE quotes ADD COLUMN status VARCHAR(12) NOT NULL DEFAULT 'aberto';
ALTER TABLE quotes ADD COLUMN sale_id INT UNSIGNED NULL;
ALTER TABLE quotes ADD COLUMN approved_at DATETIME NULL;
