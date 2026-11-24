<?php
/**
 * RECUPERACIÓN DE CONTRASEÑA (clientes del sitio)
 * ------------------------------------------------------------
 * Flujo: recuperar.php (pide el correo) → correo con un enlace de un solo
 * uso → restablecer.php?token=... (elige la contraseña nueva).
 *
 *  - El token son 32 bytes aleatorios; en la base se guarda SOLO su hash
 *    SHA-256 (tabla password_resets), así que un volcado de la base no
 *    sirve para restablecer cuentas.
 *  - Vence a los RECUPERACION_VALIDEZ_MIN minutos y se puede usar una vez.
 *  - Pedir un enlace nuevo invalida los anteriores.
 *  - La respuesta de recuperar.php es siempre la misma, exista o no el correo.
 *
 * Requiere database/el_rapido.sql.
 */

const RECUPERACION_VALIDEZ_MIN  = 60; // minutos de vida del enlace
const RECUPERACION_MAX_POR_HORA = 3;  // enlaces por cuenta y por hora (evita el spam al dueño del correo)

/**
 * Crea el token y manda el correo si el correo corresponde a una cuenta.
 * Devuelve el enlace generado (solo para mostrarlo en desarrollo) o null.
 * Nunca lanza excepciones: cualquier falla queda en el log de PHP.
 */
function solicitarRecuperacion(PDO $pdo, string $email): ?string {
  try {
    $stmt = $pdo->prepare('SELECT id, nombre, email FROM usuarios WHERE email = :e LIMIT 1');
    $stmt->execute([':e' => $email]);
    $usuario = $stmt->fetch();
    if (!$usuario) {
      return null;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE usuario_id = :u AND creado_en > (NOW() - INTERVAL 1 HOUR)');
    $stmt->execute([':u' => $usuario['id']]);
    if ((int) $stmt->fetchColumn() >= RECUPERACION_MAX_POR_HORA) {
      return null;
    }

    $token = bin2hex(random_bytes(32));

    $pdo->prepare('UPDATE password_resets SET usado_en = NOW() WHERE usuario_id = :u AND usado_en IS NULL')
        ->execute([':u' => $usuario['id']]);
    $pdo->prepare('INSERT INTO password_resets (usuario_id, token_hash, expira_en)
                   VALUES (:u, :h, NOW() + INTERVAL ' . RECUPERACION_VALIDEZ_MIN . ' MINUTE)')
        ->execute([':u' => $usuario['id'], ':h' => hash('sha256', $token)]);

    $enlace = rtrim(SITE_URL, '/') . '/restablecer.php?token=' . $token;

    if (!enviarCorreoRecuperacion($usuario['email'], $usuario['nombre'], $enlace)) {
      error_log('[el-rapido] recuperación: mail() no pudo enviar el correo (¿SMTP sin configurar?).');
    }
    if (ENV === 'development') {
      error_log('[el-rapido] enlace de recuperación (solo desarrollo): ' . $enlace);
    }
    return $enlace;
  } catch (Throwable $e) {
    error_log('[el-rapido] solicitarRecuperacion: ' . $e->getMessage());
    return null;
  }
}

/** Envía el correo con el enlace. $para ya fue validado con FILTER_VALIDATE_EMAIL. */
function enviarCorreoRecuperacion(string $para, string $nombre, string $enlace): bool {
  $asunto = 'Restablecé tu contraseña - ' . SITE_NAME;
  $cuerpo = "Hola " . str_replace(["\r", "\n"], ' ', $nombre) . ",\n\n"
          . "Recibimos un pedido para restablecer la contraseña de tu cuenta en " . SITE_NAME . ".\n"
          . "Entrá a este enlace para elegir una nueva (vale " . RECUPERACION_VALIDEZ_MIN . " minutos y se puede usar una sola vez):\n\n"
          . $enlace . "\n\n"
          . "Si no fuiste vos, ignorá este mensaje: tu contraseña actual no se modificó.\n";

  return @mail($para, mb_encode_mimeheader($asunto, 'UTF-8'), $cuerpo, [
    'From'         => SITE_EMAIL,
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=UTF-8',
  ]);
}

/**
 * Busca un token vigente (no usado y no vencido). Devuelve
 * ['id' => ..., 'usuario_id' => ..., 'email' => ...] o null.
 * Con $bloquear = true usa FOR UPDATE (dentro de una transacción).
 */
function buscarRecuperacionValida(PDO $pdo, string $token, bool $bloquear = false): ?array {
  if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    return null;
  }
  try {
    $stmt = $pdo->prepare('
      SELECT r.id, r.usuario_id, u.email
      FROM password_resets r
      JOIN usuarios u ON u.id = r.usuario_id
      WHERE r.token_hash = :h AND r.usado_en IS NULL AND r.expira_en > NOW()
      LIMIT 1' . ($bloquear ? ' FOR UPDATE' : ''));
    $stmt->execute([':h' => hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
  } catch (Throwable $e) {
    error_log('[el-rapido] buscarRecuperacionValida: ' . $e->getMessage());
    return null;
  }
}
