/* admin/assets/js/layout.js
 * Comportamiento compartido del layout del admin (sidebar + topbar).
 * Se carga una sola vez desde admin/Template/topbar.php, que se incluye
 * en TODAS las páginas del panel — así este archivo no se repite.
 */

/* --- Colapsar/expandir el sidebar (botón hamburguesa) ---
 * Guardamos la preferencia en localStorage para que, si el admin lo deja
 * colapsado, siga así al navegar a otra sección (cada página es una
 * carga de página nueva, no una SPA). */
const CLAVE_SIDEBAR_COLAPSADO = 'admin_sidebar_colapsado';

function aplicarSidebarColapsado(colapsado) {
  document.body.classList.toggle('sidebar-colapsado', colapsado);
}

function togglearSidebarAdmin() {
  const colapsado = !document.body.classList.contains('sidebar-colapsado');
  aplicarSidebarColapsado(colapsado);
  try {
    localStorage.setItem(CLAVE_SIDEBAR_COLAPSADO, colapsado ? '1' : '0');
  } catch (e) {
    // localStorage puede fallar (modo privado, etc.); no es crítico, seguimos sin guardar.
  }
}

(function restaurarSidebarColapsado() {
  let guardado = null;
  try {
    guardado = localStorage.getItem(CLAVE_SIDEBAR_COLAPSADO);
  } catch (e) {
    guardado = null;
  }
  if (guardado === '1') aplicarSidebarColapsado(true);
})();

/* --- Menú desplegable del usuario (topbar) --- */
function togglearMenuUsuario(evento) {
  evento.stopPropagation();
  document.getElementById('topbar-user-menu')?.classList.toggle('abierto');
}

document.addEventListener('click', () => {
  document.getElementById('topbar-user-menu')?.classList.remove('abierto');
});

/* Elementos con role="button" que no son <button> (tarjetas del wizard, "Ver detalle"):
 * se activan también con Enter o Espacio. */
document.addEventListener('keydown', (e) => {
  if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('[role="button"][tabindex="0"]')) {
    e.preventDefault();
    e.target.click();
  }
});
