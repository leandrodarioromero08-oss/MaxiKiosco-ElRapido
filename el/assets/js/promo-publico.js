/* Escapa texto antes de meterlo en innerHTML (títulos y nombres vienen de la base de datos) */
function escHtmlPub(t) {
  return String(t ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

/**
 * PROMO-PUBLICO.JS
 * Maneja el modal de detalle de una promoción (banners del inicio y
 * tarjetas de la categoría "Promociones"): arma el contenido a partir
 * de los datos embebidos en la página (window.PROMOS_DATA), calcula el
 * precio y el ahorro en vivo según la cantidad elegida, y agrega al
 * carrito mandando un form real a carrito_agregar_promo.php (el server
 * siempre vuelve a validar stock y vigencia antes de guardar nada).
 */

/* Formato de precio igual al PHP (es-AR: $1.500,5) */
function fmtARSPub(n) {
  return '$' + Number(n).toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

const RUTA_IMG_PRODUCTOS_PUB = 'assets/img/productos/';

const BADGE_TXT_PUB = {
  oferta: 'OFERTA', combo: 'COMBO', '2x1': '2X1', '3x2': '3X2',
  descuento_pct: '% OFF', segunda_unidad: '2DA UNIDAD', oferta_dia: 'OFERTA DEL DÍA',
};

function colorHexPromoPub(promo) {
  if (promo.color_banner === 'personalizado' && promo.color_hex) return promo.color_hex;
  if (promo.color_banner === 'rojo') return '#D91F26';
  if (promo.color_banner === 'amarillo') return '#FFD600';
  return '#1a1a2e';
}

function pasoCantidadPub(tipo) {
  if (tipo === '2x1') return 2;
  if (tipo === '3x2') return 3;
  return 1;
}

function cantidadMaximaPub(promo) {
  if (!promo.productos.length) return 0;
  if (promo.tipo === 'combo' && promo.productos.length < 2) return 0;
  const stockMinimo = Math.min(...promo.productos.map(p => p.stock ?? 0));
  if (promo.tipo === 'combo') return Math.max(0, stockMinimo);
  const paso = pasoCantidadPub(promo.tipo);
  return Math.max(0, Math.floor(stockMinimo / paso) * paso);
}

function calcularLineaPub(promo, cantidad) {
  let precioLinea = 0, ahorroLinea = 0, notaTipo = '';
  if (promo.tipo === 'combo') {
    const precioListaCombo = promo.productos.reduce((a, p) => a + p.precio, 0);
    precioLinea = promo.precio_promo * cantidad;
    ahorroLinea = (precioListaCombo - promo.precio_promo) * cantidad;
    notaTipo = 'Combo: ' + promo.productos.map(p => p.nombre).join(' + ');
  } else if (promo.tipo === '2x1') {
    const pu = promo.productos[0].precio, pares = Math.floor(cantidad / 2);
    precioLinea = pu * pares; ahorroLinea = pu * pares;
    notaTipo = pares + ' pagás, ' + pares + ' gratis';
  } else if (promo.tipo === '3x2') {
    const pu = promo.productos[0].precio, trios = Math.floor(cantidad / 3);
    precioLinea = pu * trios * 2; ahorroLinea = pu * trios;
    notaTipo = (trios * 2) + ' pagás, ' + trios + ' gratis';
  } else if (promo.tipo === 'descuento_pct') {
    const pu = promo.productos[0].precio;
    const puFinal = Math.round(pu * (1 - promo.descuento_pct / 100) * 100) / 100;
    precioLinea = puFinal * cantidad; ahorroLinea = (pu - puFinal) * cantidad;
    notaTipo = '-' + promo.descuento_pct + '% aplicado';
  } else if (promo.tipo === 'segunda_unidad') {
    // Mismo criterio que calcularLineaPromo() en PHP: esta promo usa
    // descuento_pct (precio_promo queda vacío para este tipo) y el
    // descuento es solo sobre la 2da unidad de cada par.
    const pu = promo.productos[0].precio;
    const pctOff = promo.descuento_pct;
    const precioSegunda = Math.round(pu * (1 - pctOff / 100) * 100) / 100;
    const pares = Math.floor(cantidad / 2);
    const sinPar = cantidad % 2;
    precioLinea = (pares * (pu + precioSegunda)) + (sinPar * pu);
    ahorroLinea = pares * (pu - precioSegunda);
    notaTipo = 'Cada 2da unidad con -' + pctOff + '% OFF';
  } else {
    const pu = promo.productos[0].precio;
    precioLinea = promo.precio_promo * cantidad;
    ahorroLinea = (pu - promo.precio_promo) * cantidad;
    notaTipo = 'Precio promocional aplicado';
  }
  const r2 = n => Math.round(n * 100) / 100;
  return { precioLinea: r2(precioLinea), ahorroLinea: r2(Math.max(0, ahorroLinea)), notaTipo };
}

function precioTextoModalPub(promo) {
  if (promo.tipo === 'descuento_pct' || promo.tipo === 'segunda_unidad') return '-' + promo.descuento_pct + '% OFF';
  if (promo.tipo === '2x1' || promo.tipo === '3x2') return fmtARSPub(promo.productos[0].precio) + ' c/u';
  return fmtARSPub(promo.precio_promo);
}

let modalPromoActual = null;
let modalPromoCantidad = 1;
let modalPromoFocoPrevio = null; // elemento que tenía el foco antes de abrir (se le devuelve al cerrar)

function abrirModalPromoDetalle(id) {
  const datos = (window.PROMOS_DATA || []).find(p => p.id === id);
  if (!datos) return;
  modalPromoActual = datos;
  modalPromoCantidad = pasoCantidadPub(datos.tipo);
  modalPromoFocoPrevio = document.activeElement;
  renderModalPromoDetalle();
  document.getElementById('modalPromoDetalle').classList.add('abierto');
  // Accesibilidad: el foco pasa al modal (botón de cerrar) para que teclado y lector de pantalla trabajen dentro de él
  const cerrar = document.querySelector('#modalPromoDetalle .modal-promo-cerrar');
  if (cerrar) cerrar.focus();
}

function cerrarModalPromoDetalle() {
  document.getElementById('modalPromoDetalle').classList.remove('abierto');
  if (modalPromoFocoPrevio && typeof modalPromoFocoPrevio.focus === 'function') {
    modalPromoFocoPrevio.focus();
  }
  modalPromoFocoPrevio = null;
}

function cambiarCantidadModalPromo(dir) {
  const p = modalPromoActual;
  const paso = pasoCantidadPub(p.tipo);
  const max = cantidadMaximaPub(p);
  const nueva = modalPromoCantidad + dir * paso;
  if (nueva >= paso && nueva <= max) {
    modalPromoCantidad = nueva;
    renderModalPromoDetalle();
    // renderModalPromoDetalle() vuelve a dibujar los botones: se devuelve el foco al que se apretó
    const destino = document.querySelector('#modalPromoBody .modal-promo-qty-btn[data-dir="' + dir + '"]:not([disabled])')
      || document.querySelector('#modalPromoBody .modal-promo-qty-btn:not([disabled])');
    if (destino) destino.focus();
  }
}

function renderModalPromoDetalle() {
  const p = modalPromoActual;
  const claseColor = p.color_banner === 'rojo' ? 'promo-card-roja' : p.color_banner === 'amarillo' ? 'promo-card-amarilla' : 'promo-card-verde';
  const bg = colorHexPromoPub(p);
  const textColor = p.color_banner === 'amarillo' ? '#1a1a1a' : '#fff';

  // Combo: si el título guardado es solo el del 1.º producto, se muestran los dos
  let tituloModal = p.titulo;
  if (p.tipo === 'combo' && p.productos.length >= 2 &&
      String(p.titulo).trim().toUpperCase() === p.productos[0].nombre.trim().toUpperCase()) {
    tituloModal = (p.productos[0].nombre + ' + ' + p.productos[1].nombre).toUpperCase();
  }

  const banner = document.getElementById('modalPromoBanner');
  banner.className = 'modal-promo-banner ' + claseColor;
  banner.style.background = bg;
  banner.style.color = textColor;
  banner.innerHTML = `
    <span class="promo-badge promo-badge-blanco" style="color:${bg};">${p.tipo === 'descuento_pct' ? p.descuento_pct + '% OFF' : (BADGE_TXT_PUB[p.tipo] || 'PROMO')}</span>
    <div class="promo-titulo" id="modalPromoTitulo">${escHtmlPub(tituloModal)}</div>
    ${p.descripcion ? `<div class="promo-subtitulo" style="opacity:.9;font-size:13px;">${escHtmlPub(p.descripcion)}</div>` : ''}
  `;

  const max = cantidadMaximaPub(p);
  const paso = pasoCantidadPub(p.tipo);
  const { precioLinea, ahorroLinea, notaTipo } = calcularLineaPub(p, modalPromoCantidad);
  const etiquetaUnidad = p.tipo === 'combo'
    ? (modalPromoCantidad === 1 ? '1 combo' : modalPromoCantidad + ' combos')
    : (modalPromoCantidad === 1 ? '1 unidad' : modalPromoCantidad + ' unidades');

  const hint = p.tipo === '2x1' ? 'Se agrega de a pares para que el 2x1 se aplique completo.'
    : p.tipo === '3x2' ? 'Se agrega de a tríos para que el 3x2 se aplique completo.'
    : p.tipo === 'combo' ? 'Cada combo incluye 1 unidad de cada producto.' : '';

  const body = document.getElementById('modalPromoBody');

  if (max <= 0) {
    body.innerHTML = `
      <div class="modal-promo-nota">${escHtmlPub(p.productos.map(x => x.nombre).join(' + '))}</div>
      <div class="aviso-carrito-item">Esta promoción ya no está disponible (sin stock).</div>`;
    return;
  }

  const precioListaTotal = p.productos.reduce((a, x) => a + x.precio, 0);

  body.innerHTML = `
    <div class="modal-promo-precio-row">
      <span class="modal-promo-precio-final">${precioTextoModalPub(p)}</span>
      ${(p.tipo !== 'descuento_pct' && p.precio_promo) ? `<span class="modal-promo-precio-orig">${fmtARSPub(precioListaTotal)}</span>` : ''}
    </div>
    <div class="modal-promo-nota">${escHtmlPub(p.productos.map(x => x.nombre).join(' + '))}</div>

    <div class="modal-promo-selector">
      <span style="font-size:13px;font-weight:600;">${etiquetaUnidad}</span>
      <div class="qty-controles">
        <button type="button" class="modal-promo-qty-btn" data-dir="-1" aria-label="Quitar ${paso === 1 ? 'una unidad' : paso + ' unidades'}" onclick="cambiarCantidadModalPromo(-1)" ${modalPromoCantidad <= paso ? 'disabled' : ''}>−</button>
        <span class="modal-promo-qty-num" aria-live="polite">${modalPromoCantidad}</span>
        <button type="button" class="modal-promo-qty-btn" data-dir="1" aria-label="Sumar ${paso === 1 ? 'una unidad' : paso + ' unidades'}" onclick="cambiarCantidadModalPromo(1)" ${modalPromoCantidad + paso > max ? 'disabled' : ''}>+</button>
      </div>
    </div>
    ${hint ? `<div class="modal-promo-hint">${hint}</div>` : '<div style="margin-bottom:10px;"></div>'}

    <div class="modal-promo-ahorro"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg> Ahorrás ${fmtARSPub(ahorroLinea)} con esta promo</div>

    ${window.USUARIO_LOGUEADO
      ? `<button type="button" class="btn-agregar-promo-grande" onclick="agregarPromoDesdeModal()">Agregar promoción al carrito — ${fmtARSPub(precioLinea)}</button>`
      : `<a href="login.php" class="modal-promo-login">Iniciá sesión para agregar</a>`
    }
    <div class="modal-promo-vigencia">Válido hasta ${escHtmlPub(p.fecha_fin_fmt)}</div>
  `;
}

function agregarPromoDesdeModal() {
  document.getElementById('inputPromoId').value = modalPromoActual.id;
  document.getElementById('inputPromoCantidad').value = modalPromoCantidad;
  document.getElementById('formAgregarPromo').submit();
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-abrir-promo]').forEach(el => {
    const id = parseInt(el.dataset.abrirPromo, 10);
    el.addEventListener('click', () => abrirModalPromoDetalle(id));
    // Accesibilidad: si el elemento clickeable no es un botón/enlace ni contiene uno (banner del inicio),
    // se lo vuelve operable con teclado: se puede enfocar y se abre con Enter o Espacio.
    if (!/^(BUTTON|A)$/.test(el.tagName) && !el.querySelector('button, a')) {
      const promo = (window.PROMOS_DATA || []).find(p => p.id === id);
      el.setAttribute('role', 'button');
      el.setAttribute('tabindex', '0');
      el.setAttribute('aria-label', 'Ver detalle de la promoción' + (promo ? ': ' + promo.titulo : ''));
      el.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); abrirModalPromoDetalle(id); }
      });
    }
  });
  const overlay = document.getElementById('modalPromoDetalle');
  if (overlay) {
    overlay.addEventListener('click', e => { if (e.target === overlay) cerrarModalPromoDetalle(); });
  }
  // Escape también cierra el modal
  document.addEventListener('keydown', e => {
    if (!overlay || !overlay.classList.contains('abierto')) return;
    if (e.key === 'Escape') { cerrarModalPromoDetalle(); return; }
    // Accesibilidad: el foco con Tab queda dentro del modal mientras está abierto
    if (e.key === 'Tab') {
      const enfocables = overlay.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled])');
      if (!enfocables.length) return;
      const primero = enfocables[0], ultimo = enfocables[enfocables.length - 1];
      if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
      else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
      else if (!overlay.contains(document.activeElement)) { e.preventDefault(); primero.focus(); }
    }
  });
});
