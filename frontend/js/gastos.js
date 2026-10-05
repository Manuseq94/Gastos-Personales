/*
  Este archivo maneja todo el CRUD de gastos en gastos.html.
  Reglas de la UI según método de pago:
  - efectivo / billetera_virtual: solo monto.
  - debito: monto + tarjeta de débito.
  - credito: monto + tarjeta de crédito + cantidad de cuotas (valor de cuota calculado).
  El cálculo de cuota acá es solo una vista previa: el backend recalcula y guarda el valor real.
*/

var listadoGastos = [];
var listadoTarjetas = [];

var NOMBRES_METODO = {
  efectivo: 'Efectivo',
  debito: 'Débito',
  credito: 'Crédito',
  billetera_virtual: 'Billetera virtual'
};

/**
 * Devuelve la fecha de hoy (hora LOCAL) en formato YYYY-MM-DD.
 * toISOString() usa UTC: en Argentina, después de las 21 h devolvía el día siguiente.
 */
function hoyISO() {
  var hoy = new Date();
  var mes = String(hoy.getMonth() + 1).padStart(2, '0');
  var dia = String(hoy.getDate()).padStart(2, '0');
  return hoy.getFullYear() + '-' + mes + '-' + dia;
}

/**
 * Mismo cálculo que el backend (calcularPlanDePago): monto / cuotas redondeado a 2 decimales.
 * Devuelve null si los datos todavía no son válidos.
 */
function calcularValorCuota(monto, cuotas) {
  if (!(monto > 0) || !Number.isInteger(cuotas) || cuotas < 1) {
    return null;
  }
  return Math.round((monto / cuotas) * 100) / 100;
}

/**
 * Actualiza la vista previa del valor de cuota.
 */
function actualizarValorCuota() {
  var monto = Number(document.getElementById('gasto-monto').value);
  var cuotas = Number(document.getElementById('gasto-cuotas').value);
  var salida = document.getElementById('gasto-valor-cuota');
  var valor = calcularValorCuota(monto, cuotas);

  if (valor === null) {
    salida.textContent = '-';
    return;
  }

  salida.textContent = cuotas + ' x ' + formatearMoneda(valor);
}

/**
 * Muestra u oculta tarjeta y cuotas según el método de pago elegido.
 */
function aplicarReglasMetodoPago() {
  var metodo = document.getElementById('gasto-metodo').value;
  var usaTarjeta = metodo === 'debito' || metodo === 'credito';
  var esCredito = metodo === 'credito';

  var selectTarjeta = document.getElementById('gasto-tarjeta');
  var inputCuotas = document.getElementById('gasto-cuotas');

  document.getElementById('grupo-tarjeta').classList.toggle('oculto', !usaTarjeta);
  selectTarjeta.required = usaTarjeta;
  if (usaTarjeta) {
    poblarSelectTarjetas(metodo);
  } else {
    selectTarjeta.value = '';
  }

  document.getElementById('grupo-cuotas').classList.toggle('oculto', !esCredito);
  document.getElementById('grupo-valor-cuota').classList.toggle('oculto', !esCredito);
  inputCuotas.required = esCredito;
  if (!esCredito) {
    inputCuotas.value = 1;
  }

  actualizarValorCuota();
}

/**
 * Carga las opciones del select de tarjetas filtradas por tipo.
 * Usa textContent para no interpretar como HTML datos cargados por el usuario.
 */
function poblarSelectTarjetas(tipoRequerido) {
  var select = document.getElementById('gasto-tarjeta');
  var seleccionPrevia = select.value;

  select.innerHTML = '<option value="">Seleccionar tarjeta</option>';

  listadoTarjetas.forEach(function (tarjeta) {
    if (tipoRequerido && tarjeta.tipo !== tipoRequerido) {
      return;
    }

    var option = document.createElement('option');
    option.value = tarjeta.id;
    option.textContent = tarjeta.nombre + ' - ' + tarjeta.banco + ' •••• ' + tarjeta.ultimos_4;
    select.appendChild(option);
  });

  // Si la tarjeta elegida sigue siendo válida para el nuevo filtro, la conservamos.
  select.value = seleccionPrevia;
  if (select.value !== seleccionPrevia) {
    select.value = '';
  }
}

/**
 * Crea una celda con texto plano (evita XSS: nunca concatenar datos en innerHTML).
 */
function crearCelda(texto) {
  var td = document.createElement('td');
  td.textContent = texto;
  return td;
}

/**
 * Crea un botón de acción de la tabla.
 */
function crearBotonAccion(accion, id, texto, clase) {
  var boton = document.createElement('button');
  boton.type = 'button';
  boton.className = 'btn btn-accion ' + clase;
  boton.dataset.accion = accion;
  boton.dataset.id = id;
  boton.textContent = texto;
  return boton;
}

/**
 * Renderiza la tabla de gastos en el DOM.
 */
function renderTablaGastos() {
  var tbody = document.getElementById('tabla-gastos');
  tbody.innerHTML = '';

  if (listadoGastos.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8">No hay gastos registrados.</td></tr>';
    return;
  }

  listadoGastos.forEach(function (gasto) {
    var tr = document.createElement('tr');

    var textoCuotas = '-';
    if (gasto.metodo_pago === 'credito') {
      textoCuotas = gasto.cantidad_cuotas + ' x ' + formatearMoneda(gasto.valor_cuota);
    }

    tr.appendChild(crearCelda(gasto.fecha));
    tr.appendChild(crearCelda(gasto.descripcion));
    tr.appendChild(crearCelda(gasto.categoria));
    tr.appendChild(crearCelda(NOMBRES_METODO[gasto.metodo_pago] || gasto.metodo_pago));
    tr.appendChild(crearCelda(formatearMoneda(gasto.monto)));
    tr.appendChild(crearCelda(textoCuotas));
    tr.appendChild(crearCelda(gasto.tarjeta_nombre || '-'));

    var tdAcciones = document.createElement('td');
    tdAcciones.appendChild(crearBotonAccion('editar', gasto.id, 'Editar', 'btn-secundario'));
    tdAcciones.appendChild(crearBotonAccion('eliminar', gasto.id, 'Eliminar', 'btn-eliminar'));
    tr.appendChild(tdAcciones);

    tbody.appendChild(tr);
  });
}

/**
 * Pide tarjetas al backend para usarlas en el select del formulario.
 */
async function cargarTarjetas() {
  var respuesta = await apiRequest('tarjetas.php');
  listadoTarjetas = respuesta.datos.tarjetas || [];
  aplicarReglasMetodoPago();
}

/**
 * Pide gastos al backend y refresca la tabla.
 */
async function cargarGastos() {
  try {
    var respuesta = await apiRequest('gastos.php');
    listadoGastos = respuesta.datos.gastos || [];
    renderTablaGastos();
  } catch (error) {
    mostrarMensaje('mensaje-gasto', error.message, 'error');
  }
}

/**
 * Deja el formulario listo para cargar un gasto nuevo.
 */
function resetFormulario() {
  document.getElementById('form-gasto').reset();
  document.getElementById('gasto-id').value = '';
  document.getElementById('gasto-fecha').value = hoyISO();
  document.getElementById('gasto-metodo').value = 'efectivo';
  document.getElementById('gasto-cuotas').value = 1;

  document.getElementById('titulo-form-gasto').textContent = 'Nuevo gasto';
  document.getElementById('btn-cancelar-gasto').classList.add('oculto');

  aplicarReglasMetodoPago();
}

/**
 * Carga un gasto existente en el formulario para editar.
 */
function cargarEnFormulario(gasto) {
  document.getElementById('gasto-id').value = gasto.id;
  document.getElementById('gasto-descripcion').value = gasto.descripcion;
  document.getElementById('gasto-monto').value = gasto.monto;
  document.getElementById('gasto-fecha').value = gasto.fecha;
  document.getElementById('gasto-categoria').value = gasto.categoria;
  document.getElementById('gasto-metodo').value = gasto.metodo_pago;
  document.getElementById('gasto-cuotas').value = gasto.cantidad_cuotas || 1;

  aplicarReglasMetodoPago();
  document.getElementById('gasto-tarjeta').value = gasto.tarjeta_id || '';

  document.getElementById('titulo-form-gasto').textContent = 'Editando gasto #' + gasto.id;
  document.getElementById('btn-cancelar-gasto').classList.remove('oculto');
}

/**
 * Busca un gasto por id en el array local.
 */
function buscarGastoPorId(id) {
  for (var i = 0; i < listadoGastos.length; i += 1) {
    if (Number(listadoGastos[i].id) === Number(id)) {
      return listadoGastos[i];
    }
  }

  return null;
}

/**
 * Gestiona los botones de editar y eliminar de la tabla de gastos.
 */
function bindEventosTabla() {
  var tabla = document.getElementById('tabla-gastos');

  tabla.addEventListener('click', async function (evento) {
    var boton = evento.target.closest('button[data-accion]');
    if (!boton) {
      return;
    }

    var id = Number(boton.dataset.id);
    var gasto = buscarGastoPorId(id);
    if (!gasto) {
      return;
    }

    if (boton.dataset.accion === 'editar') {
      cargarEnFormulario(gasto);
      return;
    }

    if (boton.dataset.accion === 'eliminar') {
      if (!confirm('¿Seguro que querés eliminar este gasto?')) {
        return;
      }

      try {
        await apiRequest('gastos.php?id=' + id, { method: 'DELETE' });
        mostrarMensaje('mensaje-gasto', 'Gasto eliminado correctamente.', 'exito');
        await cargarGastos();
      } catch (error) {
        mostrarMensaje('mensaje-gasto', error.message, 'error');
      }
    }
  });
}

/**
 * Reúne los datos del formulario para enviarlos al servidor.
 * Solo se envían cuotas cuando el método es crédito; valor_cuota y valor_total
 * no se envían porque los calcula el backend.
 */
function construirPayloadGasto() {
  var metodo = document.getElementById('gasto-metodo').value;

  var payload = {
    descripcion: document.getElementById('gasto-descripcion').value.trim(),
    monto: Number(document.getElementById('gasto-monto').value),
    fecha: document.getElementById('gasto-fecha').value,
    categoria: document.getElementById('gasto-categoria').value.trim(),
    metodo_pago: metodo,
    tarjeta_id: document.getElementById('gasto-tarjeta').value || null
  };

  if (metodo === 'credito') {
    payload.cantidad_cuotas = Number(document.getElementById('gasto-cuotas').value);
  }

  return payload;
}

/**
 * Conecta los eventos del formulario principal.
 */
function bindFormulario() {
  var form = document.getElementById('form-gasto');

  document.getElementById('gasto-metodo').addEventListener('change', aplicarReglasMetodoPago);
  document.getElementById('gasto-monto').addEventListener('input', actualizarValorCuota);
  document.getElementById('gasto-cuotas').addEventListener('input', actualizarValorCuota);

  form.addEventListener('submit', async function (evento) {
    evento.preventDefault();

    var id = document.getElementById('gasto-id').value;
    var payload = construirPayloadGasto();

    try {
      if (id) {
        await apiRequest('gastos.php?id=' + encodeURIComponent(id), {
          method: 'PUT',
          body: JSON.stringify(payload)
        });
        mostrarMensaje('mensaje-gasto', 'Gasto actualizado correctamente.', 'exito');
      } else {
        await apiRequest('gastos.php', {
          method: 'POST',
          body: JSON.stringify(payload)
        });
        mostrarMensaje('mensaje-gasto', 'Gasto creado correctamente.', 'exito');
      }

      resetFormulario();
      await cargarGastos();
    } catch (error) {
      mostrarMensaje('mensaje-gasto', error.message, 'error');
    }
  });

  document.getElementById('btn-cancelar-gasto').addEventListener('click', resetFormulario);
}

/**
 * Inicializa la pantalla de gastos.
 */
async function initGastosPage() {
  if (!document.getElementById('form-gasto')) {
    return;
  }

  resetFormulario();
  bindFormulario();
  bindEventosTabla();

  try {
    await cargarTarjetas();
  } catch (error) {
    mostrarMensaje('mensaje-gasto', 'No se pudieron cargar tarjetas: ' + error.message, 'error');
  }

  await cargarGastos();
}

document.addEventListener('DOMContentLoaded', function () {
  initGastosPage();
});
