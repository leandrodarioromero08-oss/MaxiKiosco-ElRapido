/* admin/assets/js/categorias.js
 * Modal de nueva/editar categoría y confirmación de borrado.
 * Los datos de cada fila viajan en data-attributes (no dentro de un onclick), así un
 * nombre con apóstrofo o comillas no rompe el JavaScript.
 */
const modalCategoria = document.getElementById('modal-categoria');

function abrirModalCategoria(btn, datos) {
  const d = datos || (btn ? {
    id: btn.dataset.id, nombre: btn.dataset.nombre, icono: btn.dataset.icono,
    orden: btn.dataset.orden, slug: btn.dataset.slug
  } : null);
  const ayudaSlug = document.getElementById('campo-cat-slug-ayuda');

  document.getElementById('form-categoria').reset();
  document.getElementById('campo-categoria-id').value = d && d.id ? d.id : '';
  document.getElementById('modal-categoria-titulo').textContent = d && d.id ? 'Editar categoría' : 'Nueva categoría';

  if (d) {
    document.getElementById('campo-cat-nombre').value = d.nombre || '';
    document.getElementById('campo-cat-icono').value = d.icono || '';
    document.getElementById('campo-cat-orden').value = d.orden ?? 0;
  }
  if (d && d.slug) {
    ayudaSlug.textContent = 'Dirección en la tienda: ?cat=' + d.slug + ' (no cambia al renombrar, para no romper links ni la imagen de la categoría).';
    ayudaSlug.style.display = '';
  } else {
    ayudaSlug.style.display = 'none';
  }
  modalCategoria.classList.add('abierto');
  document.getElementById('campo-cat-nombre').focus();
}

function cerrarModalCategoria() {
  modalCategoria.classList.remove('abierto');
}
modalCategoria.addEventListener('click', (e) => { if (e.target === modalCategoria) cerrarModalCategoria(); });

function confirmarEliminarCategoria(btn) {
  const productos = parseInt(btn.dataset.productos, 10) || 0;
  if (productos > 0) {
    alert('No se puede eliminar "' + btn.dataset.nombre + '": tiene ' + productos + ' producto(s). Movelos a otra categoría primero.');
    return;
  }
  if (confirm('¿Seguro que querés eliminar la categoría "' + btn.dataset.nombre + '"? Esta acción no se puede deshacer.')) {
    const f = document.getElementById('form-eliminar-categoria');
    f.querySelector('[name=categoria_id]').value = btn.dataset.id;
    f.submit();
  }
}

// Reabre el modal con lo que había escrito el admin si el servidor rechazó el guardado
if (window.CATEGORIA_REABRIR) {
  abrirModalCategoria(null, window.CATEGORIA_REABRIR);
  // El motivo se muestra DENTRO del modal (el aviso de la página queda tapado por el fondo oscuro)
  if (window.CATEGORIA_ERROR) {
    const aviso = document.createElement('div');
    aviso.className = 'alerta alerta-error';
    aviso.style.margin = '0 0 12px';
    aviso.textContent = window.CATEGORIA_ERROR;
    const cuerpo = document.querySelector('#form-categoria .modal-body');
    cuerpo.insertBefore(aviso, cuerpo.firstChild);
  }
}
