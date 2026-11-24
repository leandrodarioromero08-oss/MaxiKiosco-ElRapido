<?php

require_once '../Config/funciones.php';
require_once '../Config/auth.php';
requerirAdmin();
$pdo = obtenerConexion();

/*
 * CONFIGURACIÓN — versión de SOLO LECTURA con datos REALES.
 *
 * Antes esta pantalla mostraba datos de ejemplo fijos (dirección, "3 usuarios", "8 roles",
 * "Última copia: Hoy 02:00") y botones sin acción, lo que hacía creer que había ajustes
 * editables y copias de seguridad automáticas que NO existen. Ahora:
 *  - los valores del negocio se leen de las constantes reales de includes/config.php
 *    (lo que de verdad usa la tienda), indicando dónde se cambian;
 *  - los contadores salen de la base de datos;
 *  - lo que todavía no existe se dice claramente en lugar de simularse.
 */

$clientes    = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$admins      = (int) $pdo->query('SELECT COUNT(*) FROM administradores')->fetchColumn();
$categorias  = (int) $pdo->query('SELECT COUNT(*) FROM categorias')->fetchColumn();
$prodActivos = (int) $pdo->query('SELECT COUNT(*) FROM productos WHERE activo = 1')->fetchColumn();
$promosAct   = (int) $pdo->query("SELECT COUNT(*) FROM promociones WHERE estado = 'activa'")->fetchColumn();

// El número de WhatsApp de config.php viene con un valor de relleno hasta que se cargue el real
$whatsappPendiente = strpos(WP_NUMBER, 'X') !== false;

$CSSpage       = 'configuracion.css';
$tituloPagina  = 'Configuración';
$seccionActiva = 'configuracion';
include('../Template/sidebar.php');
include('../Template/topbar.php');

/** Fila de datos de solo lectura */
function filaConfig(string $icono, string $label, string $valor, bool $pendiente = false): void { ?>
            <li class="panel-fila">
              <span class="panel-fila-label"><span class="panel-fila-icono"><?= icono($icono, '', 15) ?></span> <?= htmlspecialchars($label) ?></span>
              <span class="panel-fila-valor"<?= $pendiente ? ' style="color:#E65100;"' : '' ?>><?= htmlspecialchars($valor) ?><?= $pendiente ? ' (falta cargar el real)' : '' ?></span>
            </li>
<?php }
?>

    <main class="page-content">

      <h1 class="page-title">Configuración</h1>
      <p class="page-subtitle">Datos del kiosco y de la cuenta · <a href="cambiar_password.php">Cambiar mi contraseña</a></p>

      <div class="resumen-grid">
        <div class="resumen-card">
          <div class="resumen-icon tono-verde"><?= icono('usuario', '', 22) ?></div>
          <div class="resumen-info">
            <p class="resumen-titulo">Clientes registrados</p>
            <p class="resumen-valor"><?= $clientes ?></p>
            <p class="resumen-caption">Cuentas en la tienda</p>
          </div>
        </div>
        <div class="resumen-card">
          <div class="resumen-icon tono-naranja"><?= icono('escudo', '', 22) ?></div>
          <div class="resumen-info">
            <p class="resumen-titulo">Administradores</p>
            <p class="resumen-valor"><?= $admins ?></p>
            <p class="resumen-caption">Con acceso a este panel</p>
          </div>
        </div>
        <div class="resumen-card">
          <div class="resumen-icon tono-azul"><?= icono('paquete', '', 22) ?></div>
          <div class="resumen-info">
            <p class="resumen-titulo">Catálogo</p>
            <p class="resumen-valor"><?= $prodActivos ?></p>
            <p class="resumen-caption">Productos activos · <?= $categorias ?> categorías</p>
          </div>
        </div>
        <div class="resumen-card">
          <div class="resumen-icon tono-amarillo"><?= icono('etiqueta', '', 22) ?></div>
          <div class="resumen-info">
            <p class="resumen-titulo">Promociones activas</p>
            <p class="resumen-valor"><?= $promosAct ?></p>
            <p class="resumen-caption">Visibles hoy en la tienda</p>
          </div>
        </div>
      </div>

      <div class="paneles-grid" style="margin-bottom: 20px;">

        <div class="card">
          <div class="panel-header">
            <h2 class="panel-titulo">Información del kiosco</h2>
          </div>
          <ul class="panel-lista">
<?php
  filaConfig('lapiz', 'Nombre', NEGOCIO_NOMBRE);
  filaConfig('mapa', 'Dirección', NEGOCIO_DIRECCION);
  filaConfig('reloj', 'Horario', NEGOCIO_HORARIO);
  filaConfig('telefono', 'Teléfono', NEGOCIO_TELEFONO);
  filaConfig('correo', 'Email', SITE_EMAIL);
  filaConfig('telefono', 'WhatsApp de pedidos', WP_NUMBER, $whatsappPendiente);
  filaConfig('camion', 'Recargo de delivery', formatoMoneda((float) RECARGO_DELIVERY));
?>
          </ul>
          <p class="resumen-caption" style="padding:12px 4px 0;">
            Estos valores se leen de <code>includes/config.php</code> (los mismos que usa la tienda).
            Para cambiarlos hay que editar ese archivo en el servidor; todavía no se pueden modificar desde el panel.
          </p>
        </div>

        <div class="card">
          <div class="panel-header">
            <h2 class="panel-titulo">Cuenta y datos</h2>
          </div>
          <ul class="panel-lista">
            <li class="panel-fila">
              <span class="panel-fila-label"><span class="panel-fila-icono"><?= icono('candado', '', 15) ?></span> Contraseña de administrador</span>
              <span class="panel-fila-valor"><a href="cambiar_password.php">Cambiar</a></span>
            </li>
            <li class="panel-fila">
              <span class="panel-fila-label"><span class="panel-fila-icono"><?= icono('descargar', '', 15) ?></span> Exportar productos (CSV)</span>
              <span class="panel-fila-valor"><a href="productos_exportar.php">Descargar</a></span>
            </li>
            <li class="panel-fila">
              <span class="panel-fila-label"><span class="panel-fila-icono"><?= icono('descargar', '', 15) ?></span> Exportar pedidos (CSV)</span>
              <span class="panel-fila-valor"><a href="pedidos_exportar.php">Descargar</a></span>
            </li>
            <li class="panel-fila">
              <span class="panel-fila-label"><span class="panel-fila-icono"><?= icono('info', '', 15) ?></span> Copias de seguridad</span>
              <span class="panel-fila-valor" style="color:#C62828;">No configuradas</span>
            </li>
          </ul>
          <p class="resumen-caption" style="padding:12px 4px 0;">
            <strong>Importante:</strong> este sistema NO hace copias de seguridad automáticas de la base de datos.
            Programá una en el servidor (por ejemplo un <code>mysqldump</code> diario desde cron o desde el panel del hosting)
            y guardala fuera del servidor. Los CSV de arriba sirven de respaldo parcial, no reemplazan una copia de la base.
          </p>
        </div>

      </div>

    </main>

  </div>
</div>

</body>
</html>
