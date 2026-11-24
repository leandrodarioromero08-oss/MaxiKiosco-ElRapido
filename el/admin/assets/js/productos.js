/* admin/assets/js/productos.js
 * Lógica de la sección "Productos" del panel admin: modal de
 * agregar/editar, previsualización de imagen y borrado con confirmación.
 * Los filtros actuales (búsqueda, categoría, estado, paginación) vienen
 * como data-attributes en el modal (ver admin/Seccion/productos.php),
 * así este archivo no depende de ningún valor inyectado por PHP.
 */

const modal = document.getElementById('modal-producto');
const RUTA_IMAGENES = '../../assets/img/productos/';

function resetearVistaPreviaImagen() {
  document.getElementById('imagen-preview-img').style.display = 'none';
  document.getElementById('imagen-preview-img').src = '';
  document.getElementById('imagen-preview-emoji').style.display = '';
  document.getElementById('opcion-quitar-imagen').style.display = 'none';
  document.getElementById('campo-quitar-imagen').checked = false;
  document.getElementById('campo-imagen-actual').value = '';
  document.getElementById('campo-imagen').value = '';
}

function previsualizarImagen(input) {
  if (!input.files || !input.files[0]) return;
  const lector = new FileReader();
  lector.onload = (e) => {
    document.getElementById('imagen-preview-img').src = e.target.result;
    document.getElementById('imagen-preview-img').style.display = '';
    document.getElementById('imagen-preview-emoji').style.display = 'none';
    document.getElementById('campo-quitar-imagen').checked = false;
  };
  lector.readAsDataURL(input.files[0]);
}

function abrirModalAgregar() {
  document.getElementById('modal-titulo').textContent = 'Agregar producto';
  document.getElementById('form-producto').reset();
  document.getElementById('campo-producto-id').value = '';
  document.getElementById('campo-stock-original').value = '';
  document.getElementById('campo-activo').checked = true;
  resetearVistaPreviaImagen();
  document.getElementById('imagen-preview-emoji').textContent = '📦';
  modal.classList.add('abierto');
}

/**
 * Carga el modal con los datos de un producto (o con lo que el admin había escrito si el
 * servidor rechazó el guardado). d = {id, nombre, categoria, emoji, precio, descuento, stock,
 * stockOriginal, destacado, activo, imagen}
 */
function cargarModalProducto(d) {
  document.getElementById('modal-titulo').textContent = d.id ? 'Editar producto' : 'Agregar producto';
  document.getElementById('form-producto').reset();
  document.getElementById('campo-producto-id').value = d.id || '';
  document.getElementById('campo-nombre').value = d.nombre || '';
  document.getElementById('campo-categoria').value = d.categoria || '';
  document.getElementById('campo-emoji').value = d.emoji || '';
  document.getElementById('campo-precio').value = d.precio ?? '';
  document.getElementById('campo-descuento').value = d.descuento ?? 0;
  document.getElementById('campo-stock').value = d.stock ?? 0;
  // Stock con el que se abrió el formulario: el servidor aplica solo la DIFERENCIA
  // (así una venta que entró mientras editabas no se pisa).
  document.getElementById('campo-stock-original').value = d.id ? (d.stockOriginal ?? d.stock ?? '') : '';
  document.getElementById('campo-destacado').checked = String(d.destacado) === '1';
  document.getElementById('campo-activo').checked = String(d.activo) === '1';

  resetearVistaPreviaImagen();
  document.getElementById('imagen-preview-emoji').textContent = d.emoji || '📦';

  if (d.imagen) {
    document.getElementById('campo-imagen-actual').value = d.imagen;
    document.getElementById('imagen-preview-img').src = RUTA_IMAGENES + d.imagen;
    document.getElementById('imagen-preview-img').style.display = '';
    document.getElementById('imagen-preview-emoji').style.display = 'none';
    document.getElementById('opcion-quitar-imagen').style.display = '';
  }
  modal.classList.add('abierto');
}

function abrirModalEditar(btn) {
  cargarModalProducto({
    id: btn.dataset.id, nombre: btn.dataset.nombre, categoria: btn.dataset.categoria,
    emoji: btn.dataset.emoji, precio: btn.dataset.precio, descuento: btn.dataset.descuento,
    stock: btn.dataset.stock, destacado: btn.dataset.destacado, activo: btn.dataset.activo,
    imagen: btn.dataset.imagen
  });
}

function cerrarModal() {
  modal.classList.remove('abierto');
}

modal.addEventListener('click', (e) => {
  if (e.target === modal) cerrarModal();
});

/**
 * Confirmación de borrado. El texto cambia según lo que va a pasar de verdad:
 * si el producto tiene ventas, está en promociones o en listas de clientes, NO se borra,
 * se desactiva (así lo avisamos antes de que el admin confirme).
 * Los datos vienen en data-attributes del botón, no en el onclick, para que un nombre con
 * apóstrofo o comillas no rompa el JavaScript.
 */
function confirmarEliminar(btn) {
  const nombre = btn.dataset.nombre;
  const ventas = parseInt(btn.dataset.ventas, 10) || 0;
  const promos = parseInt(btn.dataset.promos, 10) || 0;
  const listas = parseInt(btn.dataset.listas, 10) || 0;

  let texto;
  if (ventas + promos + listas > 0) {
    const motivos = [];
    if (ventas) motivos.push(ventas + ' venta(s) en pedidos');
    if (promos) motivos.push('está en ' + promos + ' promoción(es)');
    if (listas) motivos.push('está en ' + listas + ' lista(s) de Compra Semanal de clientes');
    texto = '"' + nombre + '" no se puede borrar del todo (' + motivos.join(', ') + ').\n\n' +
            'Se va a DESACTIVAR: deja de verse en la tienda y se conserva su historial. ¿Continuar?';
  } else {
    texto = '¿Seguro que querés eliminar "' + nombre + '"? No tiene ventas ni promociones asociadas. Esta acción no se puede deshacer.';
  }

  if (confirm(texto)) {
    // Se elimina por POST (con token CSRF) usando el formulario oculto, no por un link GET.
    const f = document.getElementById('form-eliminar-producto');
    f.querySelector('[name=producto_id]').value = btn.dataset.id;
    f.querySelector('[name=q_actual]').value = modal.dataset.filtroQ || '';
    f.querySelector('[name=cat_actual]').value = modal.dataset.filtroCat || '';
    f.querySelector('[name=estado_actual]').value = modal.dataset.filtroEstado || '';
    f.querySelector('[name=pagina_actual]').value = modal.dataset.filtroPagina || '1';
    f.querySelector('[name=por_pagina_actual]').value = modal.dataset.filtroPorPagina || '';
    f.submit();
  }
}

function actualizarPorPagina(valor) {
  const url = new URL(window.location.href);
  url.searchParams.set('por_pagina', valor);
  url.searchParams.set('pagina', 1);
  return url.toString();
}

// Si el servidor rechazó el guardado, se reabre el modal con lo que había escrito el admin
// (la foto no se puede reponer por seguridad del navegador: hay que volver a elegirla).
if (window.PRODUCTO_REABRIR) {
  cargarModalProducto(window.PRODUCTO_REABRIR);
  // El motivo se muestra DENTRO del modal (el aviso de la página queda tapado por el fondo oscuro)
  if (window.PRODUCTO_ERROR) {
    const aviso = document.createElement('div');
    aviso.className = 'alerta alerta-error';
    aviso.style.margin = '0 0 12px';
    aviso.textContent = window.PRODUCTO_ERROR;
    const cuerpo = document.querySelector('#form-producto .modal-body') || document.getElementById('form-producto');
    cuerpo.insertBefore(aviso, cuerpo.firstChild);
  }
}
// ?nuevo=1 (acción rápida desde el Inicio)
if (window.PRODUCTO_ABRIR_NUEVO && !window.PRODUCTO_REABRIR) {
  abrirModalAgregar();
}
