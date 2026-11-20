-- Fotos dos carros da biblioteca (PNG sem fundo), uma por angulo, para o giro na plataforma
CREATE TABLE car_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  car_id INT UNSIGNED NOT NULL,
  angle VARCHAR(40) NOT NULL DEFAULT 'Lateral direita',
  sort SMALLINT NOT NULL DEFAULT 0,
  path VARCHAR(255) NOT NULL,
  wheels TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_car_images (car_id, sort),
  CONSTRAINT fk_car_images FOREIGN KEY (car_id) REFERENCES car_models(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
