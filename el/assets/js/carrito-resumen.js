/* carrito-resumen.js
 * Calcula el envío y el total según se elija "Retiro en local" o
 * "Delivery", y valida que haya dirección cargada antes de enviar.
 * El subtotal y el recargo de envío vienen del backend como
 * data-attributes en el propio formulario (ver carrito.php), así este
 * archivo queda sin ninguna dependencia de PHP.
 */

// IIFE: se ejecuta sola y evita variables globales.
// IMPORTANTE: este JS es solo COMODIDAD (mostrar el envío, validar antes
// de enviar). La seguridad real la garantiza el servidor, que vuelve a
// validar y a calcular todo.
(function () {
  // Si no existe el formulario (carrito vacío), no hay nada que hacer.
  const form = document.getElementById('form-finalizar');
  if (!form) return;

  // Datos que PHP imprimió como data-attributes del formulario
  // (data-subtotal, data-recargo-delivery). parseFloat los pasa a número.
  const subtotal = parseFloat(form.dataset.subtotal || '0');
  const recargoDelivery = parseFloat(form.dataset.recargoDelivery || '0');

  // Referencias a los elementos de la página que se van a leer o modificar.
  const radioRetiro = document.getElementById('entrega-retiro');
  const radioDelivery = document.getElementById('entrega-delivery');
  const direccionWrap = document.getElementById('campo-direccion-wrap');
  const campoCalle = document.getElementById('campo-calle');
  const campoNumero = document.getElementById('campo-numero');
  const campoBarrio = document.getElementById('campo-barrio');
  const envioTexto = document.getElementById('envio-texto');
  const totalFinal = document.getElementById('total-final');
  const filaEnvio = document.getElementById('fila-envio');

  // Los campos de dirección requeridos solo cuando es delivery
  const camposDireccionRequeridos = [campoCalle, campoNumero, campoBarrio];

  // Formatea un número como pesos argentinos (ej.: $15.400).
  function formatoARS(numero) {
    return '$' + numero.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  // Actualiza la pantalla según el tipo de entrega elegido.
  function actualizarResumen() {
    const esDelivery = radioDelivery.checked;
    // Delivery → muestra el bloque de dirección; retiro → lo oculta.
    direccionWrap.style.display = esDelivery ? 'block' : 'none';
    // Calle, número y barrio son obligatorios solo con delivery.
    camposDireccionRequeridos.forEach(campo => { campo.required = esDelivery; });
    // Alterna la clase que pinta el envío de verde cuando es gratis.
    if (filaEnvio) filaEnvio.classList.toggle('envio-gratis', !esDelivery); // color verde por CSS, no inline

    // Delivery: muestra el recargo y suma al total. Retiro: "Gratis".
    if (esDelivery) {
      envioTexto.textContent = formatoARS(recargoDelivery);
      totalFinal.textContent = formatoARS(subtotal + recargoDelivery);
    } else {
      envioTexto.textContent = 'Gratis (retiro en local)';
      totalFinal.textContent = formatoARS(subtotal);
    }
  }

  // Cada vez que se cambia de opción se recalcula; y una vez al cargar.
  radioRetiro.addEventListener('change', actualizarResumen);
  radioDelivery.addEventListener('change', actualizarResumen);
  actualizarResumen();

  // Botón de finalizar y su texto original (para restaurarlo después).
  const botonFinalizar = form.querySelector('.btn-finalizar-compra');
  const textoBotonOriginal = botonFinalizar ? botonFinalizar.textContent : '';

  // Al enviar el formulario...
  form.addEventListener('submit', function (e) {
    // ...si es delivery, se verifica que calle, número y barrio no estén vacíos;
    // si falta alguno se frena el envío (preventDefault) y se avisa.
    if (radioDelivery.checked) {
      const faltante = camposDireccionRequeridos.find(campo => campo.value.trim() === '');
      if (faltante) {
        e.preventDefault();
        alert('Completá calle, número y barrio para el delivery.');
        faltante.focus();
        return;
      }
    }
    // Evita el doble clic: un solo envío por vez (el servidor además usa un token de un solo uso)
    if (form.dataset.enviando === '1') {
      e.preventDefault();
      return;
    }
    // Marca el formulario como "enviando" y bloquea el botón.
    form.dataset.enviando = '1';
    if (botonFinalizar) {
      botonFinalizar.disabled = true;
      botonFinalizar.textContent = 'PROCESANDO...';
    }
  });

  // Si el usuario vuelve atrás (caché del navegador), se rehabilita el botón
  window.addEventListener('pageshow', function () {
    form.dataset.enviando = '0';
    if (botonFinalizar) {
      botonFinalizar.disabled = false;
      botonFinalizar.textContent = textoBotonOriginal;
    }
  });

  /* ─────────────────────────────────────────────────────────
     "Usar otro número" / "Usar otra dirección": el teléfono y
     la dirección guardados del usuario vienen precargados y en
     solo lectura; estos botones los habilitan para editar (y
     los vuelven a dejar en solo lectura si el usuario se
     arrepiente y quiere volver a usar el dato guardado).
     Si el usuario no tiene nada guardado, estos elementos ni
     siquiera existen en la página, así que todo queda protegido
     con checks de null.
     ───────────────────────────────────────────────────────── */
  // Función reutilizable para los botones "Usar otro número / dirección".
  // Recibe una configuración (ids, campos y mensajes) y alterna entre el
  // dato guardado (solo lectura) y un campo editable.
  function activarToggleDatoGuardado(config) {
    // Si el usuario no tiene dato guardado, el botón no existe: se sale.
    const boton = document.getElementById(config.botonId);
    if (!boton) return;

    const textoSpan = document.getElementById(config.textoId);
    // true = se está usando el dato guardado (campos bloqueados).
    let usandoGuardado = true;

    // Al hacer clic se alterna entre los dos modos.
    boton.addEventListener('click', function () {
      if (usandoGuardado) {
        // Pasa a "usar otro": habilita los campos, los vacía y enfoca el primero.
        config.campos.forEach(campo => { campo.readOnly = false; campo.value = ''; });
        textoSpan.textContent = config.mensajeEditando;
        boton.textContent = config.textoVolver;
        config.campos[0].focus();
      } else {
        // Vuelve al dato guardado: bloquea los campos y los rellena con el valor
        // que el botón guarda en sus data-attributes.
        config.campos.forEach(campo => {
          campo.readOnly = true;
          campo.value = boton.dataset[campo.dataset.claveGuardada] || '';
        });
        textoSpan.textContent = config.mensajeGuardado;
        boton.textContent = config.textoCambiar;
      }
      // Invierte el modo para el próximo clic.
      usandoGuardado = !usandoGuardado;
    });
  }

  // Configura el botón del TELÉFONO.
  const campoTelefono = document.getElementById('campo-telefono');
  const btnTelefonoCambiar = document.getElementById('telefono-cambiar-btn');
  if (campoTelefono && btnTelefonoCambiar) {
    campoTelefono.dataset.claveGuardada = 'guardado';
    activarToggleDatoGuardado({
      botonId: 'telefono-cambiar-btn',
      textoId: 'telefono-aviso-texto',
      campos: [campoTelefono],
      mensajeEditando: 'Ingresá el número al que te vamos a contactar para este pedido.',
      mensajeGuardado: 'Te vamos a contactar al número que tenés guardado.',
      textoVolver: 'Usar el número guardado',
      textoCambiar: 'Usar otro número',
    });
  }

  // Configura el botón de la DIRECCIÓN (calle, número, barrio, referencia).
  // Cada campo recibe la clave del data-attribute donde está su valor guardado.
  const btnDireccionCambiar = document.getElementById('direccion-cambiar-btn');
  if (btnDireccionCambiar && campoCalle && campoNumero && campoBarrio) {
    const campoReferencia = document.getElementById('campo-referencia');
    campoCalle.dataset.claveGuardada = 'calle';
    campoNumero.dataset.claveGuardada = 'numero';
    campoBarrio.dataset.claveGuardada = 'barrio';
    if (campoReferencia) campoReferencia.dataset.claveGuardada = 'referencia';

    activarToggleDatoGuardado({
      botonId: 'direccion-cambiar-btn',
      textoId: 'direccion-aviso-texto',
      campos: campoReferencia ? [campoCalle, campoNumero, campoBarrio, campoReferencia] : [campoCalle, campoNumero, campoBarrio],
      mensajeEditando: 'Ingresá la dirección donde querés recibir este pedido.',
      mensajeGuardado: 'Vamos a enviarlo a tu dirección guardada.',
      textoVolver: 'Usar la dirección guardada',
      textoCambiar: 'Usar otra dirección',
    });
  }
})();
