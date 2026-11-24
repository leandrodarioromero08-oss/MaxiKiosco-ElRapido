-- ============================================================
-- Migración 006 — Notificaciones al cliente (pedidos)
-- ------------------------------------------------------------
-- Cuando el admin cambia el estado de un pedido (preparando, listo
-- para retirar, en camino, entregado, cancelado) se guarda un aviso
-- acá. La página pública lo consulta cada ~20 s y lo muestra abajo
-- a la derecha; al mostrarlo lo marca como visto.
-- Se puede correr más de una vez (IF NOT EXISTS / INSERT IGNORE).
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

INSERT IGNORE INTO schema_migrations (version, descripcion) VALUES
  ('006_notificaciones', 'Tabla notificaciones (avisos al cliente cuando cambia el estado de su pedido)');
