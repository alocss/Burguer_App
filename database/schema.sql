CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','staff','manager','admin') NOT NULL DEFAULT 'customer',
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  sort_order INT NOT NULL DEFAULT 0,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(140) NOT NULL,
  slug VARCHAR(160) NOT NULL UNIQUE,
  description VARCHAR(500) NOT NULL,
  price_cents INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  featured BOOLEAN NOT NULL DEFAULT FALSE,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  sold_out BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS addon_groups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  min_choices TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_choices TINYINT UNSIGNED NOT NULL DEFAULT 1,
  required BOOLEAN NOT NULL DEFAULT FALSE,
  active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS addons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  price_cents INT UNSIGNED NOT NULL DEFAULT 0,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  CONSTRAINT fk_addons_group FOREIGN KEY (group_id) REFERENCES addon_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_addon_groups (
  product_id BIGINT UNSIGNED NOT NULL,
  group_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY(product_id, group_id),
  FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY(group_id) REFERENCES addon_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  type ENUM('percent','fixed','free_delivery') NOT NULL,
  value INT UNSIGNED NOT NULL,
  min_order_cents INT UNSIGNED NOT NULL DEFAULT 0,
  usage_limit INT UNSIGNED NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  expires_at DATETIME NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_areas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  fee_cents INT UNSIGNED NOT NULL,
  min_order_cents INT UNSIGNED NOT NULL DEFAULT 0,
  eta_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 45,
  active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS banners (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(140) NOT NULL,
  body VARCHAR(300) NULL,
  image_path VARCHAR(255) NOT NULL,
  destination_url VARCHAR(255) NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS addresses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  delivery_area_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(60) NOT NULL DEFAULT 'Casa',
  street VARCHAR(160) NOT NULL,
  number VARCHAR(30) NOT NULL,
  complement VARCHAR(100) NULL,
  reference VARCHAR(160) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(delivery_area_id) REFERENCES delivery_areas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_number CHAR(16) NOT NULL UNIQUE,
  idempotency_key CHAR(64) NOT NULL UNIQUE,
  user_id BIGINT UNSIGNED NOT NULL,
  address_id BIGINT UNSIGNED NULL,
  fulfillment ENUM('delivery','pickup') NOT NULL,
  status ENUM('received','confirmed','preparing','out_for_delivery','delivered','cancelled','rejected') NOT NULL DEFAULT 'received',
  payment_method ENUM('cash','pix_delivery','debit_delivery','credit_delivery','online') NOT NULL,
  payment_status ENUM('pending','paid','failed','refunded','not_required') NOT NULL DEFAULT 'pending',
  subtotal_cents INT UNSIGNED NOT NULL,
  discount_cents INT UNSIGNED NOT NULL DEFAULT 0,
  delivery_fee_cents INT UNSIGNED NOT NULL DEFAULT 0,
  total_cents INT UNSIGNED NOT NULL,
  notes VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES users(id),
  FOREIGN KEY(address_id) REFERENCES addresses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_name VARCHAR(140) NOT NULL,
  unit_price_cents INT UNSIGNED NOT NULL,
  quantity SMALLINT UNSIGNED NOT NULL,
  total_cents INT UNSIGNED NOT NULL,
  FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY(product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_item_addons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_item_id BIGINT UNSIGNED NOT NULL,
  addon_id BIGINT UNSIGNED NOT NULL,
  addon_name VARCHAR(120) NOT NULL,
  unit_price_cents INT UNSIGNED NOT NULL DEFAULT 0,
  quantity SMALLINT UNSIGNED NOT NULL,
  total_cents INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
  FOREIGN KEY(addon_id) REFERENCES addons(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_item_customizations (
  order_item_id BIGINT UNSIGNED PRIMARY KEY,
  notes VARCHAR(300) NOT NULL,
  FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  old_status VARCHAR(40) NULL,
  new_status VARCHAR(40) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY(actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id VARCHAR(80) NULL,
  metadata_json JSON NULL,
  ip_hash CHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_created(created_at),
  FOREIGN KEY(actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  bucket_key CHAR(64) PRIMARY KEY,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  window_started_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categories (id,name,slug,sort_order) VALUES
(1,'Mais pedidos','mais-pedidos',1),(2,'Hambúrgueres','hamburgueres',2),(3,'Combos','combos',3),(4,'Acompanhamentos','acompanhamentos',4),(5,'Bebidas','bebidas',5),(6,'Sobremesas','sobremesas',6);

INSERT IGNORE INTO products (id,category_id,name,slug,description,price_cents,image_path,featured) VALUES
(1,2,'House Bacon','house-bacon','Blend 180g, cheddar inglês, bacon crocante, cebola caramelizada e molho House.',3890,'/assets/house-bacon.jpg',1),
(2,2,'House de Costela','house-de-costela','Burger 180g, costela desfiada, queijo prato e barbecue de rapadura.',4290,'/assets/costela.jpg',1),
(3,2,'X-Bacon da House','x-bacon-da-house','Blend 160g, queijo, bacon, alface, tomate e maionese defumada.',3290,'/assets/x-bacon.jpg',1),
(4,2,'House Salad','house-salad','Blend 160g, queijo, alface, tomate, cebola roxa e molho verde.',3190,'/assets/salad.jpg',0),
(5,4,'Batata House','batata-house','Batatas rústicas, cheddar cremoso, bacon e cebolinha.',2490,'/assets/fries.jpg',1),
(6,3,'Combo Brasa','combo-brasa','Burger duplo, batata rústica e refrigerante.',4990,'/assets/combo.jpg',1),
(7,6,'Brownie na Brasa','brownie-na-brasa','Brownie, sorvete de baunilha e calda de chocolate.',2190,'/assets/dessert.jpg',0),
(8,5,'Coca-Cola 350ml','coca-cola-350','Lata bem gelada, tradicional ou zero.',790,'/assets/refrigerante.jpg',0),
(9,4,'Onion Rings','onion-rings','Anéis de cebola empanados, crocantes e acompanhados de molho da casa.',2290,'/assets/onion-rings.jpg',0);

INSERT IGNORE INTO addon_groups (id,name,min_choices,max_choices,required,active) VALUES
(1001,'Adicione à sua brasa',0,3,0,1),
(1002,'Quer tirar algo?',0,6,0,1);

INSERT IGNORE INTO addons (id,group_id,name,price_cents,active) VALUES
(1001,1001,'Bacon extra',550,1),
(1002,1001,'Cheddar extra',450,1),
(1003,1001,'Carne smash 100g',990,1),
(1004,1002,'Sem pão brioche',0,1),
(1005,1002,'Sem cheddar',0,1),
(1006,1002,'Sem bacon',0,1),
(1007,1002,'Sem cebola caramelizada',0,1),
(1008,1002,'Sem molho da casa',0,1),
(1009,1002,'Sem salada',0,1);

INSERT IGNORE INTO product_addon_groups (product_id,group_id) VALUES
(1,1001),(1,1002),(2,1001),(2,1002),(3,1001),(3,1002),(4,1001),(4,1002),(6,1001),(6,1002);

INSERT IGNORE INTO delivery_areas (id,name,fee_cents,min_order_cents,eta_minutes) VALUES
(1,'Centro',690,2000,40),(2,'Paripe',1290,2500,50),(3,'Areia Branca',1390,2500,55);

INSERT IGNORE INTO coupons (code,type,value,min_order_cents,usage_limit,active) VALUES
('BRASA15','percent',15,3000,500,1);

INSERT IGNORE INTO settings (setting_key,setting_value) VALUES
('store_name','Burguer App'),('store_open','1'),('opening_hours','Terça a domingo, 18h às 23h30'),('address','Rua das Brasas, 147 — Centro'),('minimum_order_cents','2000');

UPDATE products
SET description='Lata bem gelada, tradicional ou zero.', image_path='/assets/refrigerante.jpg'
WHERE id=8;

UPDATE settings SET setting_value='Burguer App' WHERE setting_key='store_name';

