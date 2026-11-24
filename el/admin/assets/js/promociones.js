/* Escapa texto antes de meterlo en innerHTML (evita inyectar HTML desde nombres de productos) */
function escaparHtml(t) {
  return String(t ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

/* admin/assets/js/promociones.js
 * Lógica completa del wizard de "Nueva promoción / Editar promoción":
 * pasos, buscador de productos, preview en vivo del banner y guardado.
 *
 * Depende de dos cosas que vienen desde PHP, y que se inyectan ANTES de
 * cargar este archivo (ver admin/Seccion/promociones.php):
 *   - window.TIPOS_PROMO / window.TIPOS_KEYS: catálogo de tipos de
 *     promoción (viene de etiquetasTipoPromo() en el backend).
 *   - data-filtro-* en el modal (#modal-promo): filtros de búsqueda
 *     actuales, usados solo para armar la URL de "eliminar".
 */

const modalPromo = document.getElementById('modal-promo');
const RUTA_IMG_PRODUCTOS = '../../assets/img/productos/';

/* Mismo trazo que icono('x') en Config/iconos.php, copiado acá porque
 * este archivo arma el botón "quitar producto" del wizard con JS puro. */
const ICONO_X = '<svg class="icono-svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';

const TIPOS_PROMO = window.TIPOS_PROMO;
const TIPOS_KEYS = window.TIPOS_KEYS;

let wEstado = {
  id: null, tipo: null, prod1: null, prod2: null,
  titulo: '', desc: '', precio: '', descuento: '',
  color: 'rojo', hex: '#D91F26', pos: 1, prioridad: 5,
  inicio: '', fin: ''
};
let wPaso = 1;

/* --- render tipos --- */
const wizardTiposGrid = document.getElementById('wizardTiposGrid');
function renderTiposWizard() {
  wizardTiposGrid.innerHTML = '';
  TIPOS_KEYS.forEach(key => {
    const t = TIPOS_PROMO[key];
    if (!t) return; // tipo sin datos: lo salteamos en vez de romper todo el script
    const d = document.createElement('div');
    d.className = 'tipo-card' + (wEstado.tipo === key ? ' sel' : '');
    d.dataset.tipo = key;
    d.setAttribute('role', 'button');
    d.tabIndex = 0;
    d.innerHTML = `<span class="ic">${t.ic}</span>${t.label}`;
    d.onclick = () => {
      wEstado.tipo = key;
      document.getElementById('campo-tipo').value = key;
      document.querySelectorAll('#wizardTiposGrid .tipo-card').forEach(x => x.classList.remove('sel'));
      d.classList.add('sel');
      prepararCamposPorTipoWizard();
      actualizarPreviewWizard();
    };
    wizardTiposGrid.appendChild(d);
  });
}
renderTiposWizard();

function prepararCamposPorTipoWizard() {
  document.getElementById('wSegundoProductoWrap').style.display = wEstado.tipo === 'combo' ? 'block' : 'none';
  const usaDescuento = ['2x1', '3x2', 'descuento_pct', 'segunda_unidad'].includes(wEstado.tipo);
  document.getElementById('wWrapPrecio').style.display = usaDescuento ? 'none' : 'block';
  document.getElementById('wWrapDescuento').style.display = usaDescuento ? 'block' : 'none';
  document.getElementById('wLblPrecio').textContent = wEstado.tipo === 'oferta_dia' ? 'Precio del día' : 'Precio promocional';
}

/* --- buscador de productos (AJAX real contra el catálogo) ---
   Maneja los fallos del fetch (sesión vencida, error del servidor, sin conexión,
   respuesta que no es JSON) mostrando un aviso en la propia lista de resultados,
   en vez de dejar el autocompletado muerto sin explicación. */
function armarBuscadorWizard(inputId, resultId, esSegundo) {
  const input = document.getElementById(inputId);
  const cont = document.getElementById(resultId);
  let debounceTimer = null;   // un temporizador POR campo (antes lo compartían los dos buscadores)
  let controlador = null;     // cancela la búsqueda anterior si todavía no respondió
  let ultimaBusqueda = 0;     // descarta respuestas viejas que lleguen fuera de orden

  function ocultarResultados() {
    cont.style.display = 'none';
    cont.innerHTML = '';
  }

  // Línea de aviso no clicable dentro de la lista de resultados
  function mostrarAviso(texto, esError) {
    cont.innerHTML = '';
    const it = document.createElement('div');
    it.className = 'prod-item';
    it.style.cursor = 'default';
    it.style.color = esError ? 'var(--rojo)' : 'var(--gris-texto)';
    it.textContent = texto;
    cont.appendChild(it);
    cont.style.display = 'block';
  }

  function mostrarResultados(lista) {
    cont.innerHTML = '';
    if (lista.length === 0) {
      mostrarAviso('No encontramos productos con ese nombre.', false);
      return;
    }
    cont.style.display = 'block';
    lista.forEach(p => {
      const it = document.createElement('div');
      it.className = 'prod-item';
      const imgHtml = p.imagen ? `<img src="${RUTA_IMG_PRODUCTOS}${escaparHtml(p.imagen)}">` : escaparHtml(p.emoji);
      it.innerHTML = `<span class="emoji">${imgHtml}</span><span><b>${escaparHtml(p.nombre)}</b><br><span style="color:var(--gris-texto)">$${escaparHtml(p.precio)}</span></span>`;
      it.onclick = () => {
        if (esSegundo) { wEstado.prod2 = p; document.getElementById('campo-producto-id-2').value = p.id; renderElegidoWizard('wProductoElegido2', p, true); sugerirTituloWizard(); }
        else { wEstado.prod1 = p; document.getElementById('campo-producto-id').value = p.id; renderElegidoWizard('wProductoElegido', p, false); sugerirTituloWizard(); }
        input.value = ''; ocultarResultados();
        actualizarPreviewWizard();
      };
      cont.appendChild(it);
    });
  }

  input.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    if (controlador) controlador.abort();
    const q = input.value.trim();
    if (!q) { ultimaBusqueda++; ocultarResultados(); return; }

    debounceTimer = setTimeout(() => {
      const numero = ++ultimaBusqueda;
      controlador = ('AbortController' in window) ? new AbortController() : null;

      fetch('promociones_buscar_productos.php?q=' + encodeURIComponent(q), {
        credentials: 'same-origin',
        signal: controlador ? controlador.signal : undefined
      })
        .then(r => {
          if (r.status === 401) throw new Error('sesion');
          if (!r.ok) throw new Error('http ' + r.status);
          return r.json(); // si el servidor devolvió HTML (error 500), esto falla y cae al catch
        })
        .then(lista => {
          if (numero !== ultimaBusqueda) return; // llegó una respuesta vieja: se ignora
          if (!Array.isArray(lista)) throw new Error('formato');
          mostrarResultados(lista);
        })
        .catch(err => {
          if (err && err.name === 'AbortError') return; // la cancelamos nosotros: no es un error
          if (numero !== ultimaBusqueda) return;
          if (err && err.message === 'sesion') {
            mostrarAviso('Tu sesión de administrador venció. Recargá la página e iniciá sesión de nuevo.', true);
          } else {
            mostrarAviso('No se pudo buscar productos. Revisá tu conexión e intentá de nuevo.', true);
          }
        });
    }, 250);
  });
}
function renderElegidoWizard(contId, p, esSegundo) {
  const imgHtml = p.imagen ? `<img src="${RUTA_IMG_PRODUCTOS}${escaparHtml(p.imagen)}">` : escaparHtml(p.emoji);
  document.getElementById(contId).innerHTML = `<div class="prod-elegido">
      <div class="emoji">${imgHtml}</div>
      <div class="info"><b>${escaparHtml(p.nombre)}</b><span>$${escaparHtml(p.precio)}</span></div>
      <button type="button" class="quitar" onclick="quitarProdWizard(${esSegundo})">${ICONO_X}</button>
    </div>`;
}
function quitarProdWizard(esSegundo) {
  if (esSegundo) { wEstado.prod2 = null; document.getElementById('campo-producto-id-2').value=''; document.getElementById('wProductoElegido2').innerHTML=''; }
  else { wEstado.prod1 = null; document.getElementById('campo-producto-id').value=''; document.getElementById('wProductoElegido').innerHTML=''; }
  actualizarPreviewWizard();
}
armarBuscadorWizard('wBuscadorProd', 'wResultadosProd', false);
armarBuscadorWizard('wBuscadorProd2', 'wResultadosProd2', true);

function tituloAutoWizard() {
  if (!wEstado.prod1) return '';
  let t = wEstado.prod1.nombre.toUpperCase();
  if (wEstado.tipo === 'combo' && wEstado.prod2) t += ' + ' + wEstado.prod2.nombre.toUpperCase();
  return t.slice(0, 60);
}
/* Sugiere el título a partir de los productos. Solo lo pisa si está vacío o si es
   un título automático anterior (solo el 1.º producto, o el 1.º + 2.º): si el admin
   escribió uno propio, no se toca. */
function sugerirTituloWizard() {
  if (!wEstado.prod1) return;
  const actual = (wEstado.titulo || '').trim().toUpperCase();
  const soloP1 = wEstado.prod1.nombre.toUpperCase().slice(0, 60);
  const esAuto = actual === '' || actual === soloP1 || actual === wEstado.tituloAuto;
  if (!esAuto) return;
  const t = tituloAutoWizard();
  document.getElementById('wTitulo').value = t;
  wEstado.titulo = t;
  wEstado.tituloAuto = t;
}

['wTitulo','wDesc','wPrecio','wDescuento','wPrioridad','wInicio','wFin'].forEach(id => {
  document.getElementById(id).addEventListener('input', e => {
    const map = {wTitulo:'titulo', wDesc:'desc', wPrecio:'precio', wDescuento:'descuento', wPrioridad:'prioridad', wInicio:'inicio', wFin:'fin'};
    wEstado[map[id]] = e.target.value;
    actualizarPreviewWizard();
  });
});

function elegirColorWizard(el) {
  document.querySelectorAll('.color-op').forEach(x => x.classList.remove('sel'));
  el.classList.add('sel');
  wEstado.color = el.dataset.color; wEstado.hex = el.dataset.hex;
  document.getElementById('campo-color-banner').value = wEstado.color;
  document.getElementById('campo-color-hex').value = wEstado.hex;
  actualizarPreviewWizard();
}
function elegirPosWizard(el) {
  document.querySelectorAll('.pos-op').forEach(x => x.classList.remove('sel'));
  el.classList.add('sel');
  wEstado.pos = parseInt(el.dataset.pos);
  document.getElementById('campo-posicion').value = wEstado.pos;
  actualizarPreviewWizard();
}

function badgeTextoWizard() {
  switch (wEstado.tipo) {
    case 'oferta': return 'OFERTA';
    case 'combo': return 'COMBO';
    case '2x1': return '2X1';
    case '3x2': return '3X2';
    case 'descuento_pct': return (wEstado.descuento || 'X') + '% OFF';
    case 'segunda_unidad': return '2DA UNIDAD';
    case 'oferta_dia': return 'OFERTA DEL DÍA';
    default: return '—';
  }
}
function subTextoWizard() {
  if (wEstado.desc) return wEstado.desc;
  switch (wEstado.tipo) {
    case '2x1': return 'Llevá 2 y pagá 1';
    case '3x2': return 'Llevá 3 y pagá 2';
    case 'segunda_unidad': return `2da unidad con ${wEstado.descuento || '--'}% OFF`;
    case 'oferta_dia': return 'Válido solo hoy';
    default: return '';
  }
}
function precioTextoWizard() {
  if (['2x1','3x2','descuento_pct','segunda_unidad'].includes(wEstado.tipo)) {
    return wEstado.descuento ? `${wEstado.descuento}% OFF` : '';
  }
  return wEstado.precio ? `$${wEstado.precio}` : '';
}
function calcularEstadoWizard() {
  if (!wEstado.inicio || !wEstado.fin) return 'programada';
  const hoy = new Date(); hoy.setHours(0,0,0,0);
  const ini = new Date(wEstado.inicio+'T00:00:00');
  const fin = new Date(wEstado.fin+'T00:00:00');
  if (hoy < ini) return 'programada';
  if (hoy > fin) return 'finalizada';
  return 'activa';
}

function actualizarPreviewWizard() {
  const bg = wEstado.color === 'personalizado' ? wEstado.hex :
    (wEstado.color === 'rojo' ? '#D91F26' : wEstado.color === 'amarillo' ? '#FFD600' : '#1a1a2e');
  const claseColor = wEstado.color === 'rojo' ? 'promo-card-roja' : wEstado.color === 'amarillo' ? 'promo-card-amarilla' : 'promo-card-verde';
  const color = wEstado.color === 'amarillo' ? '#1a1a1a' : '#fff';

  let imgHtml = '🛍️';
  if (wEstado.prod1) {
    imgHtml = wEstado.prod1.imagen ? `<img src="${RUTA_IMG_PRODUCTOS}${escaparHtml(wEstado.prod1.imagen)}" alt="" style="width:52px;height:52px;object-fit:cover;border-radius:8px;">` : escaparHtml(wEstado.prod1.emoji || '');
  }

  // Todo lo que escribe el admin se escapa antes de ir a innerHTML (un título como "Llevá >2" o "<b>" no debe romper la vista previa)
  const titulo = escaparHtml(wEstado.titulo || (wEstado.prod1 ? wEstado.prod1.nombre.toUpperCase() : 'ELEGÍ UN PRODUCTO'));
  const sub = escaparHtml(subTextoWizard());
  const precio = escaparHtml(precioTextoWizard());

  document.getElementById('wBannerPreview').innerHTML = `
    <div class="promo-card ${claseColor}" style="background:${bg};color:${color};">
      <div>
        <span class="promo-badge promo-badge-blanco" style="color:${bg};">${escaparHtml(wEstado.tipo ? badgeTextoWizard() : 'OFERTA')}</span>
        <div class="promo-titulo">${titulo}</div>
        ${sub ? `<div class="promo-subtitulo">${sub}</div>` : ''}
        ${precio ? `<div class="promo-precio">${precio}</div>` : ''}
      </div>
      <div class="promo-imagen">${imgHtml}</div>
    </div>`;

  document.querySelectorAll('.mini-slot-wizard').forEach(s => s.classList.remove('activo'));
  const slot = document.getElementById('wSlot' + wEstado.pos);
  if (slot) slot.classList.add('activo');

  const est = calcularEstadoWizard();
  const chip = document.getElementById('wChipEstado');
  chip.textContent = est === 'activa' ? 'Activa' : est === 'finalizada' ? 'Finalizada' : 'Programada';
  chip.style.background = est === 'activa' ? '#E8F5E9' : '#E3F2FD';
  chip.style.color = est === 'activa' ? '#2E7D32' : '#1565C0';
}

/* --- navegación de pasos --- */
function mostrarPasoWizard(n) {
  wPaso = n;
  document.querySelectorAll('.wizard-panel').forEach(p => p.classList.remove('on'));
  document.getElementById('wp' + n).classList.add('on');
  document.querySelectorAll('.wizard-step').forEach((s, i) => {
    s.classList.toggle('on', i + 1 === n);
    s.classList.toggle('done', i + 1 < n);
  });
  document.getElementById('wBtnAtras').disabled = n === 1;
  document.getElementById('wBtnSiguiente').textContent = n === 4 ? (wEstado.id ? 'Guardar cambios' : 'Guardar promoción') : 'Siguiente →';
  if (n === 4) armarResumenWizard();
}
/* Valida TODOS los pasos anteriores a n (no solo el actual): antes se podía saltar del paso 2 al 4
   haciendo clic en la pestaña y mandar la promo sin título, fechas o precio. Si algún paso falla,
   se vuelve a ese paso para que el admin vea qué corregir. */
function validarHastaPasoWizard(n) {
  for (let k = 1; k < n; k++) {
    if (!validarPasoWizard(k)) {
      if (k !== wPaso) mostrarPasoWizard(k);
      return false;
    }
  }
  return true;
}
function irPasoWizard(n) {
  if (n > wPaso && !validarHastaPasoWizard(n)) return;
  mostrarPasoWizard(n);
}
function cambiarPasoWizard(dir) {
  if (dir > 0 && wPaso === 4) {
    if (!validarHastaPasoWizard(4)) return;
    document.getElementById('form-promo').submit();
    return;
  }
  irPasoWizard(wPaso + dir);
}
function validarPasoWizard(n) {
  if (n === 1 && !wEstado.tipo) { alert('Elegí un tipo de promoción.'); return false; }
  if (n === 2 && !wEstado.prod1) { alert('Elegí al menos un producto.'); return false; }
  if (n === 2 && wEstado.tipo === 'combo' && !wEstado.prod2) { alert('El combo necesita un segundo producto.'); return false; }
  if (n === 3) {
    if (!wEstado.titulo) { alert('Ingresá un título para el banner.'); return false; }
    if (wEstado.titulo.length > 60) { alert('El título admite hasta 60 caracteres.'); return false; }
    if (!wEstado.inicio || !wEstado.fin) { alert('Completá fecha de inicio y fin.'); return false; }
    if (wEstado.fin < wEstado.inicio) { alert('La fecha de fin no puede ser anterior a la de inicio.'); return false; }
    // Precio o descuento según el tipo (antes el paso 3 no los controlaba y el error recién aparecía en el servidor)
    const usaDescuento = ['2x1', '3x2', 'descuento_pct', 'segunda_unidad'].includes(wEstado.tipo);
    if (usaDescuento) {
      const d = parseInt(wEstado.descuento, 10);
      if (!d || d < 1 || d > 90) { alert('Ingresá un descuento entre 1% y 90%.'); return false; }
    } else {
      const precio = parseFloat(String(wEstado.precio).replace(',', '.'));
      if (!precio || precio <= 0) { alert('Ingresá un precio promocional mayor a 0.'); return false; }
    }
  }
  return true;
}
function armarResumenWizard() {
  document.getElementById('wResumen').innerHTML = `
    <b>Tipo:</b> ${escaparHtml(TIPOS_PROMO[wEstado.tipo]?.label || '')}<br>
    <b>Producto:</b> ${escaparHtml(wEstado.prod1?.nombre || '')}${wEstado.prod2 ? ' + ' + escaparHtml(wEstado.prod2.nombre) : ''}<br>
    <b>Título:</b> ${escaparHtml(wEstado.titulo)}<br>
    <b>Posición:</b> Banner ${escaparHtml(String(wEstado.pos))} — Prioridad ${escaparHtml(String(wEstado.prioridad))}<br>
    <b>Vigencia:</b> ${escaparHtml(wEstado.inicio)} al ${escaparHtml(wEstado.fin)}`;
}

/* --- abrir / cerrar modal --- */
function resetearWizard() {
  wEstado = {id:null, tipo:null, prod1:null, prod2:null, titulo:'', desc:'', precio:'', descuento:'', color:'rojo', hex:'#D91F26', pos:1, prioridad:5, inicio:'', fin:''};
  document.getElementById('form-promo').reset();
  document.getElementById('campo-promo-id').value = '';
  document.getElementById('campo-tipo').value = '';
  document.getElementById('campo-producto-id').value = '';
  document.getElementById('campo-producto-id-2').value = '';
  document.getElementById('campo-color-banner').value = 'rojo';
  document.getElementById('campo-color-hex').value = '#D91F26';
  document.getElementById('campo-posicion').value = '1';
  document.getElementById('wProductoElegido').innerHTML = '';
  document.getElementById('wProductoElegido2').innerHTML = '';
  document.querySelectorAll('.color-op').forEach((x,i) => x.classList.toggle('sel', i===0));
  document.querySelectorAll('.pos-op').forEach((x,i) => x.classList.toggle('sel', i===0));
  document.getElementById('wPrioridad').value = 5;
  renderTiposWizard();
  prepararCamposPorTipoWizard();
  actualizarPreviewWizard();
}

function abrirWizardNuevo() {
  resetearWizard();
  document.getElementById('modal-promo-titulo').textContent = 'Nueva promoción';
  irPasoWizard(1);
  modalPromo.classList.add('abierto');
}

/**
 * Carga el wizard con los datos de una promoción (para editarla) o con lo que el admin había
 * escrito cuando el servidor rechazó el guardado (p.id puede ser null = promoción nueva).
 */
function cargarWizardDesdeDatos(p) {
  resetearWizard();
  wEstado.id = p.id || null;
  wEstado.tipo = p.tipo;
  wEstado.titulo = p.titulo;
  wEstado.desc = p.descripcion || '';
  wEstado.precio = p.precio_promo || '';
  wEstado.descuento = p.descuento_pct || '';
  wEstado.color = p.color_banner;
  wEstado.hex = p.color_hex || '#D91F26';
  wEstado.pos = parseInt(p.posicion);
  wEstado.prioridad = parseInt(p.prioridad);
  wEstado.inicio = p.fecha_inicio;
  wEstado.fin = p.fecha_fin;

  document.getElementById('campo-promo-id').value = p.id || '';
  document.getElementById('campo-tipo').value = p.tipo;
  document.getElementById('campo-color-banner').value = p.color_banner;
  document.getElementById('campo-color-hex').value = p.color_hex || '';
  document.getElementById('campo-posicion').value = p.posicion;
  document.getElementById('wTitulo').value = p.titulo;
  document.getElementById('wDesc').value = p.descripcion || '';
  document.getElementById('wPrecio').value = p.precio_promo || '';
  document.getElementById('wDescuento').value = p.descuento_pct || '';
  document.getElementById('wPrioridad').value = p.prioridad;
  document.getElementById('wInicio').value = p.fecha_inicio;
  document.getElementById('wFin').value = p.fecha_fin;

  document.querySelectorAll('#wizardTiposGrid .tipo-card').forEach(x => x.classList.toggle('sel', x.dataset.tipo === p.tipo));
  document.querySelectorAll('.color-op').forEach(x => x.classList.toggle('sel', x.dataset.color === p.color_banner));
  document.querySelectorAll('.pos-op').forEach(x => x.classList.toggle('sel', parseInt(x.dataset.pos) === parseInt(p.posicion)));

  if (p.productos && p.productos[0]) {
    const prod1 = p.productos.find(x => x.rol === 'principal') || p.productos[0];
    wEstado.prod1 = prod1;
    document.getElementById('campo-producto-id').value = prod1.id;
    renderElegidoWizard('wProductoElegido', prod1, false);
    const prod2 = p.productos.find(x => x.rol === 'secundario');
    if (prod2) {
      wEstado.prod2 = prod2;
      document.getElementById('campo-producto-id-2').value = prod2.id;
      renderElegidoWizard('wProductoElegido2', prod2, true);
    }
  }

  prepararCamposPorTipoWizard();
  actualizarPreviewWizard();
  document.getElementById('modal-promo-titulo').textContent = p.id ? 'Editar promoción' : 'Nueva promoción';
  irPasoWizard(1);
  modalPromo.classList.add('abierto');
}

function abrirWizardEditar(btn) {
  let p;
  try {
    p = JSON.parse(btn.dataset.json);
  } catch (e) {
    alert('No se pudo abrir la promoción. Recargá la página e intentá de nuevo.');
    return;
  }
  cargarWizardDesdeDatos(p);
}

function cerrarModalPromo() {
  modalPromo.classList.remove('abierto');
}
modalPromo.addEventListener('click', e => { if (e.target === modalPromo) cerrarModalPromo(); });

/* Eliminar / pausar / duplicar: se envían por POST (con token CSRF) usando el
   formulario oculto #form-accion-promo, conservando los filtros actuales. */
function accionPromo(accion, id) {
  const f = document.getElementById('form-accion-promo');
  f.querySelector('[name=accion]').value = accion;
  f.querySelector('[name=promocion_id]').value = id;
  f.querySelector('[name=q_actual]').value = modalPromo.dataset.filtroQ || '';
  f.querySelector('[name=estado_actual]').value = modalPromo.dataset.filtroEstado || '';
  f.querySelector('[name=tipo_actual]').value = modalPromo.dataset.filtroTipo || '';
  f.querySelector('[name=posicion_actual]').value = modalPromo.dataset.filtroPosicion || '';
  f.querySelector('[name=pagina_actual]').value = modalPromo.dataset.filtroPagina || '1';
  f.querySelector('[name=por_pagina_actual]').value = modalPromo.dataset.filtroPorPagina || '';
  f.submit();
}

function confirmarEliminarPromo(btn) {
  if (confirm('¿Seguro que querés eliminar "' + btn.dataset.titulo + '"? Esta acción no se puede deshacer.')) {
    accionPromo('eliminar', btn.dataset.id);
  }
}

function actualizarPorPaginaPromos(valor) {
  const url = new URL(window.location.href);
  url.searchParams.set('por_pagina', valor);
  url.searchParams.set('pagina', 1);
  return url.toString();
}

actualizarPreviewWizard();

/* Si el servidor rechazó el guardado, se reabre el wizard con lo que había escrito el admin y se
   muestra el motivo DENTRO del modal (el aviso de la página queda tapado por el fondo oscuro). */
if (window.PROMO_REABRIR) {
  cargarWizardDesdeDatos(window.PROMO_REABRIR);
  if (window.PROMO_ERROR) {
    const aviso = document.createElement('div');
    aviso.className = 'alerta alerta-error';
    aviso.style.margin = '0 0 12px';
    aviso.textContent = window.PROMO_ERROR;
    const form = document.getElementById('form-promo');
    form.insertBefore(aviso, form.firstChild);
  }
}
