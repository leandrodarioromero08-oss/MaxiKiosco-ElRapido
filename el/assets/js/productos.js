/**
 * PRODUCTOS.JS
 * Scripts de la página productos.php (catálogo público).
 * Se encarga de: marcar/desmarcar favoritos (se guardan en la cuenta), mostrar el control
 * +/- al presionar "Agregar", sumar y restar la cantidad de cada
 * tarjeta antes de agregar al carrito (sin pasarse del stock
 * disponible), recargar la página cuando se cambia el criterio
 * de "Ordenar por" y el buscador de productos (botón de borrar, atajo "/").
 */

document.addEventListener('DOMContentLoaded', () => {

  /**
   * FAVORITOS
   * Al tocar el corazón se guarda (o se quita) el favorito en la cuenta del
   * cliente llamando a favorito_toggle.php. El corazón cambia al instante y,
   * si el servidor no pudo guardar, vuelve a como estaba. Los favoritos
   * quedan primero en el catálogo la próxima vez que se carga la página.
   * Si no hay sesión iniciada, se lleva al cliente a iniciar sesión y vuelve acá.
   */
  const ICONO_CORAZON = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="icono-svg" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>';
  const pintarFavorito = (btn, activo) => {
    const nombre = btn.closest('.product-card')?.querySelector('.product-name')?.textContent.trim() || 'este producto';
    btn.innerHTML = ICONO_CORAZON; // el relleno rojo lo pone el CSS según aria-pressed
    btn.setAttribute('aria-pressed', activo ? 'true' : 'false');
    btn.setAttribute('aria-label', (activo ? 'Quitar ' : 'Marcar ') + nombre + (activo ? ' de favoritos' : ' como favorito'));
    btn.closest('.product-card')?.classList.toggle('product-card-favorito', activo);
  };

  document.querySelectorAll('.fav-btn').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!window.USUARIO_LOGUEADO) {
        const destino = (window.location.pathname.split('/').pop() || 'productos.php') + window.location.search;
        window.location.href = 'login.php?volver=' + encodeURIComponent(destino);
        return;
      }
      const card = btn.closest('.product-card');
      const productoId = card ? card.dataset.productoId : '';
      if (!productoId || btn.disabled) {
        return;
      }

      const quiereActivar = btn.getAttribute('aria-pressed') !== 'true';
      pintarFavorito(btn, quiereActivar); // cambio inmediato; si falla, se revierte abajo
      btn.disabled = true;
      btn.classList.remove('fav-pop');
      void btn.offsetWidth; // reflow para repetir la animación en clics seguidos
      btn.classList.add('fav-pop');

      try {
        const datos = new FormData();
        datos.append('producto_id', productoId);
        datos.append('favorito', quiereActivar ? '1' : '0');
        datos.append('csrf_token', window.CSRF_TOKEN || '');
        const resp = await fetch('favorito_toggle.php', { method: 'POST', body: datos, credentials: 'same-origin' });
        const json = await resp.json().catch(() => ({}));
        if (resp.status === 401 && json.login) {
          window.location.href = 'login.php?volver=' + encodeURIComponent('productos.php' + window.location.search);
          return;
        }
        if (!resp.ok || !json.ok) {
          throw new Error(json.error || 'No pudimos guardar el favorito.');
        }
      } catch (err) {
        pintarFavorito(btn, !quiereActivar);
        window.alert(err.message || 'No pudimos guardar el favorito. Intentá de nuevo.');
      } finally {
        btn.disabled = false;
      }
    });
  });

  /**
   * BOTÓN "AGREGAR" → CONTROL +/-
   * Cada tarjeta arranca mostrando solo el botón "Agregar". Al
   * presionarlo, se oculta ese botón y aparece en su lugar el
   * control de cantidad (−  N  +). El botón del carrito 🛒 está
   * siempre visible: es el que realmente envía el formulario y
   * agrega el producto (con la cantidad que se haya elegido).
   */
  document.querySelectorAll('.card-actions').forEach((form) => {
    if (form.classList.contains('card-actions--ajax')) return; // MEJORA: lo maneja mejoras.js (agregar sin recargar)
    const btnAgregar = form.querySelector('.btn-agregar');
    const qtyControl = form.querySelector('.qty-control');
    if (!btnAgregar || !qtyControl) {
      return; // esta tarjeta no tiene este flujo (ej: "Sin stock")
    }

    btnAgregar.addEventListener('click', () => {
      btnAgregar.style.display = 'none';
      qtyControl.classList.remove('qty-control-oculto');
      form.classList.add('card-actions--abierto'); // muestra el aviso "tocá el carrito" (solo presentación)
    });
  });

  /**
   * CONTROL DE CANTIDAD
   * Cada tarjeta de producto tiene un contador (−  N  +) y un
   * input oculto ("qty-input") que es el que realmente se manda
   * al servidor cuando se aprieta el botón del carrito 🛒. Esta
   * función mantiene sincronizados el número que se ve con el
   * valor que se envía, y no deja subir la cantidad por encima
   * del stock disponible (dato que viene en data-stock de la
   * tarjeta).
   */
  document.querySelectorAll('.product-card').forEach((card) => {
    if (card.querySelector('.card-actions--ajax')) return; // MEJORA: el stepper lo maneja mejoras.js
    const numEl   = card.querySelector('.qty-num');
    const inputEl = card.querySelector('.qty-input');
    const menos   = card.querySelector('.qty-menos');
    const mas     = card.querySelector('.qty-mas');
    if (!numEl || !menos || !mas) {
      return; // esta tarjeta no tiene control de cantidad (ej: "Sin stock")
    }

    const stockDisponible = parseInt(card.dataset.stock || '0', 10);

    menos.addEventListener('click', () => {
      const actual = parseInt(numEl.textContent, 10);
      if (actual > 1) {
        numEl.textContent = actual - 1;
        if (inputEl) inputEl.value = actual - 1;
      }
    });

    mas.addEventListener('click', () => {
      const actual = parseInt(numEl.textContent, 10);
      // No dejamos pedir más unidades de las que hay en stock
      if (!stockDisponible || actual < stockDisponible) {
        numEl.textContent = actual + 1;
        if (inputEl) inputEl.value = actual + 1;
      }
    });
  });

  /**
   * ORDENAR POR
   * El <select> de "Ordenar por" no tiene un botón de submit: al
   * elegir una opción, recargamos la página agregando el parámetro
   * ?orden=... a la URL actual (conservando categoría y página).
   */
  const sortSelect = document.getElementById('sort-select');
  if (sortSelect) {
    sortSelect.addEventListener('change', () => {
      const url = new URL(window.location.href);
      url.searchParams.set('orden', sortSelect.value);
      url.searchParams.set('pagina', 1);
      window.location.href = url.toString();
    });
  }

  /**
   * BUSCADOR
   * El formulario es un GET común (?q=...), así que funciona aunque este script no cargue. Acá solo se suman
   * comodidades: la "x" para borrar aparece cuando hay texto, no se envían búsquedas vacías, y la tecla "/"
   * lleva el cursor a la barra desde cualquier parte de la página.
   */
  const formBusqueda = document.getElementById('form-busqueda');
  const campoBusqueda = document.getElementById('buscar-productos');
  const btnLimpiar = document.getElementById('busqueda-limpiar');
  if (formBusqueda && campoBusqueda) {
    const hayBusquedaActiva = new URL(window.location.href).searchParams.has('q');

    // Misma página, sin la búsqueda (conserva categoría y orden; vuelve a la página 1)
    const quitarBusqueda = () => {
      const url = new URL(window.location.href);
      url.searchParams.delete('q');
      url.searchParams.delete('pagina');
      window.location.href = url.toString();
    };

    const actualizarLimpiar = () => {
      if (btnLimpiar) btnLimpiar.hidden = campoBusqueda.value === '';
    };
    campoBusqueda.addEventListener('input', actualizarLimpiar);
    actualizarLimpiar();

    if (btnLimpiar) {
      btnLimpiar.addEventListener('click', () => {
        campoBusqueda.value = '';
        actualizarLimpiar();
        if (hayBusquedaActiva) {
          quitarBusqueda(); // había resultados filtrados: se vuelve al listado completo
        } else {
          campoBusqueda.focus();
        }
      });
    }

    formBusqueda.addEventListener('submit', (e) => {
      campoBusqueda.value = campoBusqueda.value.replace(/\s+/g, ' ').trim();
      if (campoBusqueda.value === '') {
        e.preventDefault();
        if (hayBusquedaActiva) quitarBusqueda(); else campoBusqueda.focus();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
      const t = e.target;
      if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
      e.preventDefault();
      campoBusqueda.focus();
      campoBusqueda.select();
    });
  }

});
