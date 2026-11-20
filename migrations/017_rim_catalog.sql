-- Catalogo de rodas do provador: fotos ja limpas (sem fundo, aro redondo) guardadas no proprio sistema em public/rodas
CREATE TABLE rim_catalog (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  brand VARCHAR(60) NULL,
  rim TINYINT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rim_catalog_path (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO rim_catalog (name, brand, rim, path) VALUES
('RODA SANDERO RS PRETA DIAMANTADA', 'SCORRO', 14, 'rodas/sandero-rs-aro14.webp');
