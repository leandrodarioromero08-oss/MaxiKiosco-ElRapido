-- ============================================================
-- MIGRACIÓN 004 — Productos favoritos por cuenta
-- Correr UNA vez en phpMyAdmin sobre la base "el_rapido" ya existente.
-- Es seguro repetirla: usa IF NOT EXISTS / INSERT IGNORE.
--
-- Cada fila = "este usuario marcó este producto como favorito".
-- La clave primaria (usuario_id, producto_id) impide duplicados.
-- Si se borra el usuario o el producto, su favorito se borra solo.
-- ============================================================
USE el_rapido;

CREATE TABLE IF NOT EXISTS favoritos (
  usuario_id   INT        NOT NULL,
  producto_id  INT        NOT NULL,
  creado_en    TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, producto_id),
  INDEX idx_favoritos_producto (producto_id),
  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO schema_migrations (version, descripcion) VALUES
  ('004_favoritos', 'Tabla favoritos (productos favoritos por usuario)');
