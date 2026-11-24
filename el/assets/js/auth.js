/* auth.js
 * JS compartido por las pantallas de autenticación (login.php y
 * registro.php): mostrar/ocultar la contraseña con el botón del ojito.
 * Antes estaba duplicado igual en los dos archivos.
 */

const SVG_OJO = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="icono-svg icono-ojo" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>';
const SVG_OJO_TACHADO = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="icono-svg icono-ojo" aria-hidden="true"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>';

function togglePassword(btn) {
  const input = btn.previousElementSibling;
  const showing = input.type === 'text';
  input.type = showing ? 'password' : 'text';
  btn.innerHTML = showing ? SVG_OJO : SVG_OJO_TACHADO;
  // Accesibilidad: el botón anuncia su estado actual
  btn.setAttribute('aria-pressed', showing ? 'false' : 'true');
  btn.setAttribute('aria-label', showing ? 'Mostrar contraseña' : 'Ocultar contraseña');
}
