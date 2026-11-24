-- ============================================================
-- BASE DE DATOS — El Rápido  (instalación completa)
-- ------------------------------------------------------------
-- CÓMO CARGARLO: phpMyAdmin > pestaña "Importar" (o pestaña SQL,
-- pegando todo el contenido). Crea la base "el_rapido" sola.
--
-- ⚠️ SOLO PARA INSTALACIÓN NUEVA (base vacía o inexistente).
--    No borra nada, pero si la base ya tiene datos, las categorías
--    de ejemplo darían error de duplicado (slug único) y los
--    productos se repetirían. Si ya tenés datos y solo querés
--    cambiar la clave del admin, usá database/generar_hash.php (por consola)
--
-- Requiere MySQL 8.0.16+ o MariaDB 10.2+ para que se APLIQUEN las
-- restricciones CHECK (en versiones anteriores se aceptan pero se ignoran).
-- ============================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS el_rapido
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE el_rapido;

-- ============================================================
-- USUARIOS (clientes del sitio)
-- password_cambiada_en: al cambiar/restablecer la clave se cierran
-- las demás sesiones abiertas de esa cuenta (Paso 4).
-- ============================================================
CREATE TABLE IF NOT EXISTS usuarios (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  nombre                VARCHAR(100) NOT NULL,
  email                 VARCHAR(150) NOT NULL UNIQUE,
  password_hash         VARCHAR(255) NOT NULL,
  password_cambiada_en  TIMESTAMP    NULL DEFAULT NULL,
  telefono              VARCHAR(30)  NULL, -- teléfono de contacto, se pide en el registro
  direccion_calle       VARCHAR(150) NULL, -- dirección de entrega guardada (opcional)
  direccion_numero      VARCHAR(20)  NULL,
  direccion_barrio      VARCHAR(100) NULL,
  direccion_referencia  VARCHAR(255) NULL,
  creado_en             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- RECUPERACIÓN DE CONTRASEÑA (solo se guarda el hash del token)
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT       NOT NULL,
  token_hash CHAR(64)  NOT NULL,
  expira_en  DATETIME  NOT NULL,
  usado_en   DATETIME  NULL,
  creado_en  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_password_resets_token (token_hash),
  INDEX idx_password_resets_usuario (usuario_id, creado_en),
  CONSTRAINT fk_password_resets_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- ADMINISTRADORES DEL PANEL (tabla aparte de los clientes)
-- ============================================================
CREATE TABLE IF NOT EXISTS administradores (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(100)  NOT NULL,
  email         VARCHAR(150)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  creado_en     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- CATEGORÍAS
-- ============================================================
CREATE TABLE IF NOT EXISTS categorias (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  nombre   VARCHAR(60)  NOT NULL,
  slug     VARCHAR(60)  NOT NULL UNIQUE,
  icono    VARCHAR(10)  NOT NULL DEFAULT '📦',
  orden    INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ============================================================
-- PRODUCTOS
-- stock NUNCA puede ser negativo (CHECK) y por defecto es 0.
-- ============================================================
CREATE TABLE IF NOT EXISTS productos (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  categoria_id  INT            NOT NULL,
  nombre        VARCHAR(150)   NOT NULL,
  precio        DECIMAL(10,2)  NOT NULL,
  descuento_pct TINYINT        NOT NULL DEFAULT 0,
  emoji         VARCHAR(10)    NOT NULL DEFAULT '📦',
  imagen        VARCHAR(255)   NULL DEFAULT NULL,
  stock         INT            NOT NULL DEFAULT 0,
  ventas        INT            NOT NULL DEFAULT 0,
  destacado     TINYINT(1)     NOT NULL DEFAULT 0,
  activo        TINYINT(1)     NOT NULL DEFAULT 1,
  creado_en     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  INDEX idx_productos_activo_categoria (activo, categoria_id),
  CONSTRAINT chk_productos_stock     CHECK (stock >= 0),
  CONSTRAINT chk_productos_precio    CHECK (precio >= 0),
  CONSTRAINT chk_productos_descuento CHECK (descuento_pct BETWEEN 0 AND 100)
) ENGINE=InnoDB;

-- ============================================================
-- PEDIDOS (se generan cuando un cliente hace "Finalizar compra")
-- ============================================================
CREATE TABLE IF NOT EXISTS pedidos (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id         INT            NOT NULL,
  telefono_contacto  VARCHAR(30)    NULL,
  entrega_nombre     VARCHAR(100)   NULL, -- nombre de quien recibe el pedido
  entrega_apellido   VARCHAR(100)   NULL, -- (sin uso actual; se conserva por compatibilidad)
  tipo_entrega       ENUM('retiro','delivery') NOT NULL DEFAULT 'retiro',
  direccion          VARCHAR(500)   NULL, -- dirección "de una línea", se arma sola a partir de calle/número/barrio/referencia
  entrega_calle      VARCHAR(150)   NULL,
  entrega_numero     VARCHAR(20)    NULL,
  entrega_barrio     VARCHAR(100)   NULL,
  entrega_referencia VARCHAR(255)   NULL, -- opcional: "portón negro", "al lado de la farmacia", etc.
  recargo_envio      DECIMAL(10,2)  NOT NULL DEFAULT 0,
  metodo_pago        ENUM('efectivo','mercadopago','tarjeta') NOT NULL DEFAULT 'efectivo',
  estado             ENUM('pendiente','preparando','listo_retiro','en_camino','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
  total              DECIMAL(10,2)  NOT NULL,
  creado_en          TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  INDEX idx_pedidos_creado_en (creado_en),
  INDEX idx_pedidos_estado (estado),
  INDEX idx_pedidos_usuario_fecha (usuario_id, creado_en)
) ENGINE=InnoDB;

-- ============================================================
-- PROMOCIONES (el banner NUNCA se guarda como imagen: se guarda
-- como datos y se renderiza con includes/promociones_funciones.php)
-- ============================================================
CREATE TABLE IF NOT EXISTS promociones (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  tipo              ENUM('oferta','combo','2x1','3x2','descuento_pct','segunda_unidad','oferta_dia') NOT NULL,
  titulo            VARCHAR(60)    NOT NULL,
  descripcion       VARCHAR(120)   NULL,
  precio_promo      DECIMAL(10,2)  NULL,
  descuento_pct     TINYINT        NULL,
  color_banner      ENUM('rojo','amarillo','negro','personalizado') NOT NULL DEFAULT 'rojo',
  color_hex         VARCHAR(7)     NULL,
  posicion          TINYINT        NOT NULL,           -- 1, 2 o 3 (Banner 1/2/3 en la portada)
  prioridad         TINYINT        NOT NULL DEFAULT 5, -- 1 = más alta, 10 = más baja
  fecha_inicio      DATE           NOT NULL,
  fecha_fin         DATE           NOT NULL,
  estado            ENUM('programada','activa','pausada','finalizada') NOT NULL DEFAULT 'programada',
  ventas_monto      DECIMAL(10,2)  NOT NULL DEFAULT 0,
  ventas_unidades   INT            NOT NULL DEFAULT 0,
  creado_en         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_promo_estado_posicion (estado, posicion, prioridad),
  CONSTRAINT chk_promo_fechas   CHECK (fecha_fin >= fecha_inicio),
  CONSTRAINT chk_promo_posicion CHECK (posicion BETWEEN 1 AND 3)
) ENGINE=InnoDB;

-- Productos de cada promoción (1 normalmente, 2 en Combo; nunca repetidos)
CREATE TABLE IF NOT EXISTS promocion_productos (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  promocion_id   INT NOT NULL,
  producto_id    INT NOT NULL,
  rol            ENUM('principal','secundario') NOT NULL DEFAULT 'principal',
  UNIQUE KEY uniq_promo_producto (promocion_id, producto_id),
  FOREIGN KEY (promocion_id) REFERENCES promociones(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id)  REFERENCES productos(id)   ON DELETE CASCADE,
  INDEX idx_promo_prod_producto (producto_id)
) ENGINE=InnoDB;

-- ============================================================
-- ITEMS DE CADA PEDIDO (detalle de qué se compró)
-- Guardamos nombre y precio "congelados" al momento de la compra,
-- para que un pedido viejo no cambie si después editás el producto.
-- promocion_id: si la fila salió de una promoción (2x1, combo, etc.);
-- NULL si fue una compra de producto normal.
-- ============================================================
CREATE TABLE IF NOT EXISTS pedido_items (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id        INT            NOT NULL,
  producto_id      INT            NULL,
  promocion_id     INT            NULL,
  nombre_producto  VARCHAR(150)   NOT NULL,
  emoji            VARCHAR(10)    NOT NULL DEFAULT '📦',
  precio_unitario  DECIMAL(10,2)  NOT NULL,
  cantidad         INT            NOT NULL,
  subtotal         DECIMAL(10,2)  NOT NULL,
  FOREIGN KEY (pedido_id)    REFERENCES pedidos(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id)  REFERENCES productos(id) ON DELETE SET NULL,
  FOREIGN KEY (promocion_id) REFERENCES promociones(id) ON DELETE SET NULL,
  CONSTRAINT chk_pedido_items_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB;

-- ============================================================
-- COMPRA SEMANAL
-- Cada usuario puede tener HASTA 3 listas (límite en MAX_COMPRAS_SEMANALES).
-- Guardamos SOLO producto_id + cantidad, NUNCA precios: el precio
-- se recalcula siempre desde "productos" al agregar la lista al carrito.
-- ============================================================
CREATE TABLE IF NOT EXISTS compra_semanal (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id      INT            NOT NULL,
  nombre          VARCHAR(100)   NOT NULL DEFAULT 'Compra semanal',
  dia_compra      ENUM('lunes','martes','miercoles','jueves','viernes','sabado','domingo') NULL,
  actualizado_en  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_en       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS compra_semanal_items (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  compra_semanal_id  INT  NOT NULL,
  producto_id        INT  NOT NULL,
  cantidad           INT  NOT NULL DEFAULT 1,
  UNIQUE KEY uniq_compra_semanal_producto (compra_semanal_id, producto_id),
  FOREIGN KEY (compra_semanal_id) REFERENCES compra_semanal(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
  CONSTRAINT chk_cs_items_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB;

-- ============================================================
-- FAVORITOS (productos marcados con el corazón por cada usuario)
-- ============================================================
CREATE TABLE IF NOT EXISTS favoritos (
  usuario_id   INT        NOT NULL,
  producto_id  INT        NOT NULL,
  creado_en    TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, producto_id),
  INDEX idx_favoritos_producto (producto_id),
  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- INTENTOS DE LOGIN / LÍMITE POR IP (ver includes/seguridad.php)
-- ambito: 'sitio' y 'admin' = login; 'registro' y 'recuperar' = límite por IP
-- ============================================================
CREATE TABLE IF NOT EXISTS intentos_login (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  ambito    ENUM('sitio','admin','registro','recuperar') NOT NULL,
  ip        VARCHAR(45)  NOT NULL,
  email     VARCHAR(150) NOT NULL,
  creado_en TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ambito_ip    (ambito, ip, creado_en),
  INDEX idx_ambito_email (ambito, email, creado_en)
) ENGINE=InnoDB;

-- ============================================================
-- NOTIFICACIONES (avisos al cliente cuando cambia el estado de su pedido)
-- ============================================================
CREATE TABLE IF NOT EXISTS notificaciones (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT           NOT NULL,
  pedido_id  INT           NULL,
  tipo       VARCHAR(30)   NOT NULL,   -- preparando | listo | en_camino | entregado | cancelado
  titulo     VARCHAR(120)  NOT NULL,
  mensaje    VARCHAR(255)  NOT NULL,
  vista      TINYINT(1)    NOT NULL DEFAULT 0,
  creada_en  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (pedido_id)  REFERENCES pedidos(id)  ON DELETE CASCADE,
  INDEX idx_notif_usuario_vista (usuario_id, vista, id)
) ENGINE=InnoDB;

-- ============================================================
-- CONTROL DE VERSIONES DEL ESQUEMA
-- ============================================================
CREATE TABLE IF NOT EXISTS schema_migrations (
  version      VARCHAR(60)  NOT NULL PRIMARY KEY,
  descripcion  VARCHAR(200) NOT NULL,
  aplicada_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO schema_migrations (version, descripcion) VALUES
  ('001_correcciones_paso2', 'Esquema consolidado: promocion_id en pedido_items, CHECKs, índices, direccion 500, estados'),
  ('002_seguridad_paso1',    'Tabla intentos_login (límite de intentos de login)'),
  ('003_paso4_usuarios',     'password_cambiada_en, password_resets y ámbitos registro/recuperar en intentos_login'),
  ('004_favoritos',          'Tabla favoritos (productos favoritos por usuario)'),
  ('006_notificaciones',     'Tabla notificaciones (avisos al cliente cuando cambia el estado de su pedido)');

-- ============================================================
-- DATOS DE EJEMPLO
-- ============================================================
INSERT INTO categorias (nombre, slug, icono, orden) VALUES
  ('Bebidas',          'bebidas',           '🍼', 1),
  ('Snacks',           'snacks',            '🍿', 2),
  ('Cigarrillos',      'cigarrillos',       '🚬', 3),
  ('Recargas',         'recargas',          '📱', 4),
  ('Golosinas',        'golosinas',         '🍬', 5),
  ('Lácteos',          'lacteos',           '🥛', 6),
  ('Almacén',          'almacen',           '🛒', 7),
  ('Otros',            'otros',             '⋯',  8);

INSERT INTO productos (categoria_id, nombre, precio, descuento_pct, emoji, destacado) VALUES
  ((SELECT id FROM categorias WHERE slug='bebidas'),     'Coca Cola 2.25L',              1800, 10, '🥤', 1),
  ((SELECT id FROM categorias WHERE slug='bebidas'),     'Sprite 2.25L',                 1600, 0,  '🍋', 0),
  ((SELECT id FROM categorias WHERE slug='bebidas'),     'Fanta Naranja 1.5L',           1400, 15, '🍊', 0),
  ((SELECT id FROM categorias WHERE slug='bebidas'),     'Monster Energy 473ml',         1200, 0,  '⚡', 1),
  ((SELECT id FROM categorias WHERE slug='bebidas'),     'Cerveza Heineken 473ml',       1700, 0,  '🍺', 0),
  ((SELECT id FROM categorias WHERE slug='bebidas'),     'Agua Mineral Villa del Sur 500ml', 500, 0, '💧', 0),

  ((SELECT id FROM categorias WHERE slug='snacks'),      'Papas Lay''s Clásicas 160g',   1200, 0,  '🍟', 1),
  ((SELECT id FROM categorias WHERE slug='snacks'),      'Doritos Queso 140g',           1100, 0,  '🌽', 0),
  ((SELECT id FROM categorias WHERE slug='snacks'),      'Palitos Salados 100g',          700, 0,  '🥨', 0),

  ((SELECT id FROM categorias WHERE slug='cigarrillos'), 'Marlboro Red Box',             2300, 0,  '🚬', 0),
  ((SELECT id FROM categorias WHERE slug='cigarrillos'), 'Philip Morris Box',            2200, 0,  '🚬', 0),

  ((SELECT id FROM categorias WHERE slug='recargas'),    'Recarga Personal $1.000',      1000, 0,  '📱', 0),
  ((SELECT id FROM categorias WHERE slug='recargas'),    'Recarga Personal $2.000',      2000, 0,  '📱', 0),

  ((SELECT id FROM categorias WHERE slug='golosinas'),   'KitKat 4 Finger',               750, 0,  '🍫', 0),
  ((SELECT id FROM categorias WHERE slug='golosinas'),   'Alfajor Tita',                  600, 0,  '🍪', 1),
  ((SELECT id FROM categorias WHERE slug='golosinas'),   'Chicles Beldent x3',            300, 0,  '🍬', 0),

  ((SELECT id FROM categorias WHERE slug='lacteos'),     'Leche La Serenísima 1L',       1050, 0,  '🥛', 0),
  ((SELECT id FROM categorias WHERE slug='lacteos'),     'Yogur Ser 190g',                550, 0,  '🥣', 0),

  ((SELECT id FROM categorias WHERE slug='almacen'),     'Ala Complete 3kg',             2900, 0,  '🧺', 0),
  ((SELECT id FROM categorias WHERE slug='almacen'),     'Lavandina Ayudín 1L',           650, 0,  '🧴', 0),
  ((SELECT id FROM categorias WHERE slug='almacen'),     'Jabón Dove 90g',                850, 0,  '🧼', 0),
  ((SELECT id FROM categorias WHERE slug='almacen'),     'Shampoo Sedal 350ml',          1400, 0,  '🧴', 0),

  ((SELECT id FROM categorias WHERE slug='otros'),       'Pilas Duracell AA x2',         1300, 0,  '🔋', 0),
  ((SELECT id FROM categorias WHERE slug='otros'),       'Encendedor Bic',                400, 0,  '🔥', 0);

-- Los productos de ejemplo arrancan con 100 unidades (el valor por defecto de la columna es 0).
UPDATE productos SET stock = 100;

-- ============================================================
-- ADMINISTRADOR  (crea la cuenta o, si ya existe, cambia su contraseña)
-- ============================================================
INSERT INTO administradores (nombre, email, password_hash)
VALUES ('Administrador', 'admin@elrapido.com', '$2y$10$.Yl4fcYka5GcHiXqPn.VfeWM9vVfF5483eiNAWE7XEXyDMEyAJULO')
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash);

-- Verificación: debe mostrar 1 fila con inicio_hash = $2y$10$.Yl4fcYk
SELECT id, email, LEFT(password_hash, 15) AS inicio_hash FROM administradores;

-- ============================================================
-- FOTOS DE LOS PRODUCTOS DE EJEMPLO (ya copiadas en assets/img/productos/)
-- Quitá los "-- " del bloque SOLO si los archivos prod_*.png/.webp/.jpg
-- ya están copiados en assets/img/productos/ (si no, se verían rotas).
-- ============================================================
UPDATE productos SET imagen = 'prod_e4a0b258ae881380.png'  WHERE nombre = 'Coca Cola 2.25L';
UPDATE productos SET imagen = 'prod_a781e2da37d099fe.png'  WHERE nombre = 'Sprite 2.25L';
UPDATE productos SET imagen = 'prod_d86d03c828fad83c.png'  WHERE nombre = 'Fanta Naranja 1.5L';
UPDATE productos SET imagen = 'prod_ee1a146ce7a9d3c3.webp' WHERE nombre = 'Monster Energy 473ml';
UPDATE productos SET imagen = 'prod_c772c4955fd7e30f.webp' WHERE nombre = 'Cerveza Heineken 473ml';
UPDATE productos SET imagen = 'prod_9043dd3f2c37469d.png'  WHERE nombre = 'Agua Mineral Villa del Sur 500ml';
UPDATE productos SET imagen = 'prod_23d52a0f4aff642d.webp' WHERE nombre = 'Papas Lay''s Clásicas 160g';
UPDATE productos SET imagen = 'prod_07b26668f3c7bb11.png'  WHERE nombre = 'Doritos Queso 140g';
UPDATE productos SET imagen = 'prod_90ed88f3f07246f1.webp' WHERE nombre = 'Palitos Salados 100g';
UPDATE productos SET imagen = 'prod_8d2154e61da2c16e.webp'  WHERE nombre = 'Marlboro Red Box';
UPDATE productos SET imagen = 'prod_d1eb4ecee92cd21b.webp'  WHERE nombre = 'Philip Morris Box';
UPDATE productos SET imagen = 'prod_340ef09749721ab8.webp' WHERE nombre = 'Recarga Personal $1.000';
UPDATE productos SET imagen = 'prod_c1e73ff74f9f7da3.webp' WHERE nombre = 'Recarga Personal $2.000';
UPDATE productos SET imagen = 'prod_fb1a67b3fdcede5c.png'  WHERE nombre = 'KitKat 4 Finger';
UPDATE productos SET imagen = 'prod_daa4df71e3f89919.png'  WHERE nombre = 'Alfajor Tita';
UPDATE productos SET imagen = 'prod_457a4fbe09d56603.png'  WHERE nombre = 'Chicles Beldent x3';
UPDATE productos SET imagen = 'prod_d30925eaa5a1b902.webp'  WHERE nombre = 'Leche La Serenísima 1L';
UPDATE productos SET imagen = 'prod_6c89e4680ac924dd.webp'  WHERE nombre = 'Yogur Ser 190g';
UPDATE productos SET imagen = 'prod_20d133e2725b0351.webp'  WHERE nombre = 'Ala Complete 3kg';
UPDATE productos SET imagen = 'prod_6e0a94114b77039a.png'  WHERE nombre = 'Lavandina Ayudín 1L';
UPDATE productos SET imagen = 'prod_d319097e7558ae8a.png'  WHERE nombre = 'Jabón Dove 90g';
UPDATE productos SET imagen = 'prod_55814a030aac66d2.webp' WHERE nombre = 'Shampoo Sedal 350ml';
UPDATE productos SET imagen = 'prod_63a279d5ab8e943d.webp' WHERE nombre = 'Pilas Duracell AA x2';
UPDATE productos SET imagen = 'prod_442fdf1aafc0c7ae.webp'  WHERE nombre = 'Encendedor Bic';
