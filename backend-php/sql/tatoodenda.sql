-- =============================================================================
--  ESQUEMA  ·  TatooDenda simplificado (MySQL / MariaDB · InnoDB · utf8mb4)
--  Importar con phpMyAdmin (Importar) o por terminal (MAMP, puerto 8889):
--    /Applications/MAMP/Library/bin/mysql -u root -p -h 127.0.0.1 -P 8889 \
--      < backend-php/sql/tatoodenda.sql
--
--  Usuario admin de ejemplo:  admin / admin123  (cámbialo tras el primer login)
-- =============================================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `tatoodenda`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tatoodenda`;

-- Para reimportar limpio (respeta el orden por las claves foráneas):
DROP TABLE IF EXISTS `lineapedido`;
DROP TABLE IF EXISTS `pedido`;
DROP TABLE IF EXISTS `producto`;
DROP TABLE IF EXISTS `categoria`;
DROP TABLE IF EXISTS `user`;

-- ---------------------------------------------------------------------------
--  CATEGORIAS (autoreferencial: una principal tiene padre = su propio id)
-- ---------------------------------------------------------------------------
CREATE TABLE `categoria` (
  `id`     INT AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL,
  `padre`  INT NOT NULL DEFAULT -1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  PRODUCTOS
-- ---------------------------------------------------------------------------
CREATE TABLE `producto` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `nombre`      VARCHAR(150) NOT NULL,
  `descripcion` TEXT,
  `precio`      DECIMAL(10,2) NOT NULL DEFAULT 0,
  `categoria`   INT NOT NULL,
  `imagen`      VARCHAR(255) DEFAULT '',
  KEY `idx_producto_categoria` (`categoria`),
  CONSTRAINT `fk_producto_categoria`
    FOREIGN KEY (`categoria`) REFERENCES `categoria` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  USUARIOS
-- ---------------------------------------------------------------------------
CREATE TABLE `user` (
  `id`        INT AUTO_INCREMENT PRIMARY KEY,
  `username`  VARCHAR(30)  NOT NULL UNIQUE,
  `password`  VARCHAR(255) NOT NULL,
  `nombre`    VARCHAR(100) NOT NULL,
  `email`     VARCHAR(150) NOT NULL UNIQUE,
  `direccion` VARCHAR(255) DEFAULT '',
  `ciudad`    VARCHAR(100) DEFAULT '',
  `cp`        VARCHAR(10)  DEFAULT '',
  `role`      VARCHAR(10)  NOT NULL DEFAULT 'user',
  `resetToken`       VARCHAR(64) DEFAULT NULL,   -- hash SHA-256 del token
  `resetTokenExpiry` DATETIME    DEFAULT NULL,
  KEY `idx_user_resettoken` (`resetToken`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  PEDIDOS
-- ---------------------------------------------------------------------------
CREATE TABLE `pedido` (
  `id`     INT AUTO_INCREMENT PRIMARY KEY,
  `iduser` INT NOT NULL,
  `fecha`  DATE NOT NULL,
  `total`  DECIMAL(10,2) NOT NULL DEFAULT 0,
  KEY `idx_pedido_iduser` (`iduser`),
  CONSTRAINT `fk_pedido_user`
    FOREIGN KEY (`iduser`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  LÍNEAS DE PEDIDO
-- ---------------------------------------------------------------------------
CREATE TABLE `lineapedido` (
  `id`       INT AUTO_INCREMENT PRIMARY KEY,
  `idpedido` INT NOT NULL,
  `idprod`   INT NOT NULL,
  `color`    VARCHAR(50) NOT NULL DEFAULT 'Estándar',
  `cant`     INT NOT NULL DEFAULT 1,
  `nombre`   VARCHAR(150) DEFAULT '',
  `precio`   DECIMAL(10,2) NOT NULL DEFAULT 0,
  KEY `idx_linea_idpedido` (`idpedido`),
  KEY `idx_linea_idprod` (`idprod`),
  CONSTRAINT `fk_linea_pedido`
    FOREIGN KEY (`idpedido`) REFERENCES `pedido` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_linea_producto`
    FOREIGN KEY (`idprod`) REFERENCES `producto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  DATOS DE EJEMPLO (opcional)
--  Los ids de categoría coinciden con el mapa de carpetas de imágenes:
--  1 anillo · 2 barbell · 3 tuneles · 4 plug · 5 expander · 6 banana
--  7 labret · 9 circular-barbel · 11 piercing · 12 dilataciones
-- =============================================================================
INSERT INTO `categoria` (`id`, `nombre`, `padre`) VALUES
  (1,  'Anillos',           1),
  (2,  'Barbells',          2),
  (3,  'Túneles',           3),
  (4,  'Plugs',             4),
  (5,  'Expanders',         5),
  (6,  'Bananas',           6),
  (7,  'Labrets',           7),
  (11, 'Piercings',         11),
  (12, 'Dilataciones',      12);

INSERT INTO `producto` (`nombre`, `descripcion`, `precio`, `categoria`, `imagen`) VALUES
  ('Anillo con corazón',   'Anillo de acero quirúrgico con corazón',      8.50,  1,  'anillo-corazon.jpg'),
  ('Anillo fino',          'Aro fino con bisagra, varios acabados',       6.90,  1,  'anillo-fino.jpg'),
  ('Barbell con flecha',   'Barbell recto con motivo de flecha',          7.20,  2,  'barbell-flecha.jpg'),
  ('Túnel de silicona',    'Túnel flexible de silicona, varias tallas',   5.00,  3,  'tunel-silicona.jpg'),
  ('Plug doble',           'Plug de madera con doble acabado',            9.90,  4,  'plug-doble.jpg'),
  ('Banana con gema',      'Banana para ombligo con gema de colores',     7.80,  6,  'banana-gema.jpg'),
  ('Labret simple',        'Labret de titanio con rosca interna',         6.50,  7,  'labret-simple.jpg'),
  ('Set de dilatadores',   'Juego de dilatadores progresivos',           12.00, 12,  'set-dilatadores.jpg');

-- Usuario administrador de ejemplo (password: admin123 — bcrypt)
INSERT INTO `user` (`username`, `password`, `nombre`, `email`, `direccion`, `ciudad`, `cp`, `role`) VALUES
  ('admin',
   '$2b$10$kYMYXH.QUdiiGT5kJDBemOfkdV0Mh8EfYk/q10dlJrbw7BsGzEAda',
   'Administrador', 'admin@tatoodenda.com', '', 'Donostia', '20001', 'admin');
