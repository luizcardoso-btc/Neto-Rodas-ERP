-- Carros dos clientes fotografados no provador: ficam salvos para a proxima venda
-- (as fotos ja recortadas vao no proprio banco, assim nao se perdem quando o sistema atualiza)
CREATE TABLE customer_cars (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NULL,
  owner_name VARCHAR(120) NOT NULL DEFAULT '',
  phone VARCHAR(30) NULL,
  brand VARCHAR(40) NOT NULL,
  model VARCHAR(60) NOT NULL,
  color VARCHAR(40) NULL,
  plate VARCHAR(10) NULL,
  tire VARCHAR(20) NOT NULL DEFAULT '185/60 R15',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_customer_cars_customer (customer_id),
  INDEX idx_customer_cars_plate (plate),
  CONSTRAINT fk_customer_cars_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customer_car_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  car_id INT UNSIGNED NOT NULL,
  angle VARCHAR(40) NOT NULL DEFAULT 'Lateral direita',
  sort SMALLINT NOT NULL DEFAULT 0,
  mime VARCHAR(30) NOT NULL DEFAULT 'image/png',
  data MEDIUMBLOB NOT NULL,
  wheels TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_customer_car_images (car_id, sort),
  CONSTRAINT fk_customer_car_images FOREIGN KEY (car_id) REFERENCES customer_cars(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
