-- ERP Pneus - schema inicial

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sellers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  weekly_pay DECIMAL(10,2) NOT NULL DEFAULT 0,
  access_token CHAR(40) NOT NULL UNIQUE,
  color VARCHAR(7) NOT NULL DEFAULT '#10b981',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL DEFAULT '',
  email VARCHAR(160) NULL,
  phone VARCHAR(30) NULL,
  tax_id VARCHAR(20) NULL,
  car VARCHAR(120) NULL,
  street VARCHAR(160) NULL,
  number VARCHAR(20) NULL,
  district VARCHAR(100) NULL,
  city VARCHAR(100) NULL,
  state CHAR(2) NULL,
  zip VARCHAR(10) NULL,
  country VARCHAR(60) NOT NULL DEFAULT 'Brasil',
  notes TEXT NULL,
  geo_latitude DECIMAL(10,7) NULL,
  geo_longitude DECIMAL(10,7) NULL,
  geo_precision VARCHAR(20) NULL,
  geo_status VARCHAR(20) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_customers_phone (phone),
  INDEX idx_customers_tax (tax_id),
  INDEX idx_customers_name (first_name, last_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  description TEXT NULL,
  gtin VARCHAR(20) NULL,
  mpn VARCHAR(60) NULL,
  google_category VARCHAR(200) NULL,
  category VARCHAR(30) NOT NULL DEFAULT 'pneu',
  condition_type ENUM('novo','usado') NOT NULL DEFAULT 'novo',
  brand VARCHAR(80) NULL,
  model VARCHAR(80) NULL,
  compatible_cars TEXT NULL,
  image_path VARCHAR(255) NULL,
  width VARCHAR(10) NULL,
  profile VARCHAR(10) NULL,
  rim VARCHAR(10) NULL,
  location VARCHAR(60) NULL,
  cost_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_products_name (name),
  INDEX idx_products_brand (brand),
  INDEX idx_products_gtin (gtin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_token CHAR(36) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NULL,
  seller_id INT UNSIGNED NULL,
  total DECIMAL(10,2) NOT NULL,
  payment_status ENUM('paid','pending') NOT NULL DEFAULT 'paid',
  source ENUM('pdv','mobile') NOT NULL DEFAULT 'pdv',
  wants_nfe TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sales_created (created_at),
  INDEX idx_sales_customer (customer_id),
  INDEX idx_sales_seller (seller_id),
  CONSTRAINT fk_sales_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_sales_seller FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sale_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  description VARCHAR(200) NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_items_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sale_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NOT NULL,
  method ENUM('dinheiro','pix','cartao','prazo','troca') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  note VARCHAR(200) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_movements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  type ENUM('in','out','adjust') NOT NULL,
  quantity INT NOT NULL,
  reason VARCHAR(200) NULL,
  sale_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_movements_product (product_id, created_at),
  CONSTRAINT fk_movements_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_movements_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE accounts_receivable (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NULL,
  customer_id INT UNSIGNED NULL,
  description VARCHAR(200) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  due_date DATE NULL,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ar_due (due_date),
  CONSTRAINT fk_ar_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL,
  CONSTRAINT fk_ar_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE accounts_payable (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  description VARCHAR(200) NOT NULL,
  category VARCHAR(60) NULL,
  amount DECIMAL(10,2) NOT NULL,
  due_date DATE NULL,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ap_due (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
