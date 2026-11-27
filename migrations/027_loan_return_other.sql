-- Devolucao de emprestimo com outro pneu (outra marca ou medida): guarda o que voltou de fato
ALTER TABLE loan_settlements ADD COLUMN returned_product_id INT UNSIGNED NULL AFTER payable_id, ADD COLUMN returned_desc VARCHAR(200) NULL AFTER returned_product_id
