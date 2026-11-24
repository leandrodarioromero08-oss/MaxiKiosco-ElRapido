/* admin/assets/js/pedidos.js
 * Lógica de la sección "Pedidos" del panel admin: menú de "más opciones"
 * por fila, modal de detalle del pedido y el selector de "por página".
 */

const modalDetalle = document.getElementById('modal-detalle');

/* Mismos trazos que usa Config/iconos.php (icono('camion') / icono('casa')),
 * copiados acá porque este archivo arma el texto de "Entrega" con JS puro,
 * sin pasar por PHP. */
const ICONO_CAMION = '<svg class="icono-svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>';
const ICONO_CASA = '<svg class="icono-svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>';

function toggleMenuMas(event, id) {
  event.stopPropagation();
  const menu = document.getElementById('menu-mas-' + id);
  const yaAbierto = menu.classList.contains('abierto');
  // Cerramos cualquier otro menú abierto antes de abrir este
  document.querySelectorAll('.menu-mas-lista.abierto').forEach(m => m.classList.remove('abierto'));
  if (!yaAbierto) {
    menu.classList.add('abierto');
  }
}

document.addEventListener('click', () => {
  document.querySelectorAll('.menu-mas-lista.abierto').forEach(m => m.classList.remove('abierto'));
});

/**
 * Arma la URL de Google Maps a partir de calle/número/barrio, igual
 * que generarUrlMapsDireccion() en includes/funciones.php (sin usar
 * la API de Maps: solo abre una búsqueda de texto en Google Maps).
 */
function urlGoogleMaps(calle, numero, barrio) {
  const direccion = [calle, numero].filter(Boolean).join(' ') +
    (barrio ? ', ' + barrio : '') + ', Monte Caseros, Corrientes, Argentina';
  return 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(direccion.trim());
}

function mostrarFila(idFila, mostrar) {
  document.getElementById(idFila).style.display = mostrar ? 'flex' : 'none';
}

/**
 * Abre el modal de detalle. Los datos del pedido viajan en el atributo data-pedido de la
 * fila (<tr>), NO dentro del onclick: así un apóstrofo, una comilla o una barra invertida en
 * el nombre de un producto, cliente o dirección ("Papas Lay's", "D'Angelo") no rompe el JavaScript.
 */
function verDetalle(fila) {
  let p;
  try {
    p = JSON.parse(fila.dataset.pedido);
  } catch (e) {
    alert('No se pudo abrir el detalle de este pedido. Recargá la página e intentá de nuevo.');
    return;
  }

  document.getElementById('detalle-titulo').textContent = 'Pedido #' + p.id;
  document.getElementById('detalle-cliente').textContent = p.cliente;
  document.getElementById('detalle-fecha').textContent = p.fecha;
  document.getElementById('detalle-total').textContent = '$' + p.total.toLocaleString('es-AR');

  // Nombre y teléfono de contacto de la entrega (pedidos viejos pueden no tenerlo)
  if (p.contacto) {
    document.getElementById('detalle-contacto').textContent = p.contacto;
    mostrarFila('detalle-contacto-fila', true);
  } else {
    mostrarFila('detalle-contacto-fila', false);
  }
  if (p.telefono) {
    document.getElementById('detalle-telefono').textContent = p.telefono;
    mostrarFila('detalle-telefono-fila', true);
  } else {
    mostrarFila('detalle-telefono-fila', false);
  }

  if (p.esDelivery) {
    document.getElementById('detalle-entrega').innerHTML = ICONO_CAMION + ' Delivery (+$' + p.recargo.toLocaleString('es-AR') + ')';
    document.getElementById('detalle-direccion').textContent = p.direccion;
    mostrarFila('detalle-direccion-fila', true);

    if (p.referencia) {
      document.getElementById('detalle-referencia').textContent = p.referencia;
      mostrarFila('detalle-referencia-fila', true);
    } else {
      mostrarFila('detalle-referencia-fila', false);
    }

    // Si el pedido tiene calle/barrio estructurados los usamos para el
    // mapa; si es un pedido viejo (solo tiene el campo "dirección" de
    // una línea), buscamos directamente ese texto en Maps.
    const consultaMaps = p.calle
      ? urlGoogleMaps(p.calle, p.numero, p.barrio)
      : (p.direccion ? urlGoogleMaps(p.direccion, '', '') : null);

    if (consultaMaps) {
      document.getElementById('detalle-maps-link').href = consultaMaps;
      mostrarFila('detalle-maps-fila', true);
    } else {
      mostrarFila('detalle-maps-fila', false);
    }
  } else {
    document.getElementById('detalle-entrega').innerHTML = ICONO_CASA + ' Retiro en el local';
    mostrarFila('detalle-direccion-fila', false);
    mostrarFila('detalle-referencia-fila', false);
    mostrarFila('detalle-maps-fila', false);
  }

  const tbody = document.getElementById('detalle-items-body');
  tbody.innerHTML = '';
  p.items.forEach(item => {
    const tr = document.createElement('tr');
    // textContent (no innerHTML): el nombre del producto viene de la base y no debe interpretarse como HTML
    const tdNombre = document.createElement('td');
    tdNombre.textContent = (item.emoji || '') + ' ' + item.nombre_producto;
    const tdCant = document.createElement('td');
    tdCant.textContent = item.cantidad;
    const tdSub = document.createElement('td');
    tdSub.textContent = '$' + Number(item.subtotal).toLocaleString('es-AR');
    tr.append(tdNombre, tdCant, tdSub);
    tbody.appendChild(tr);
  });

  modalDetalle.classList.add('abierto');
}

/**
 * Selector de estado de cada fila: pasar a "Cancelado" es irreversible (devuelve el stock y el
 * pedido no se puede reabrir), así que pide confirmación igual que el botón "Cancelar pedido".
 * Si el admin se arrepiente, el selector vuelve al estado que tenía.
 */
function cambiarEstadoDesdeSelect(select) {
  if (select.value === 'cancelado' &&
      !confirm('¿Cancelar este pedido? El stock de los productos se devolverá al catálogo y el pedido no se podrá reabrir.')) {
    select.value = select.dataset.actual;
    return;
  }
  select.form.submit();
}

function cerrarModalDetalle() {
  modalDetalle.classList.remove('abierto');
}

modalDetalle.addEventListener('click', (e) => {
  if (e.target === modalDetalle) cerrarModalDetalle();
});

function actualizarPorPaginaPedidos(valor) {
  const url = new URL(window.location.href);
  url.searchParams.set('por_pagina', valor);
  url.searchParams.set('pagina', 1);
  return url.toString();
}
