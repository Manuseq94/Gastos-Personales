/*
  Este archivo maneja todo el CRUD de gastos en gastos.html.
  Incluye cargar tarjetas, mostrar/ocultar selector de tarjeta,
  guardar gastos, editar, eliminar y refrescar la tabla.
*/

var listadoGastos = [];
var listadoTarjetas = [];

/**
 * Devuelve la fecha de hoy en formato YYYY-MM-DD.
 */
function hoyISO() {
  return new Date().toISOString().slice(0, 10);
}

/**
 * Muestra u oculta el select de tarjeta según método de pago.
 */
function toggleTarjetaSegunMetodo() {
  var metodo = document.getElementById('gasto-metodo').value;
  var grupoTarjeta = document.getElementById('grupo-tarjeta');
  var selectTarjeta = document.getElementById('gasto-tarjeta');

  if (metodo === 'debito' || metodo === 'credito') {
    grupoTarjeta.classList.remove('oculto');
    selectTarjeta.required = true;
    poblarSelectTarjetas(metodo);
  } else {
    grupoTarjeta.classList.add('oculto');
    selectTarjeta.required = false;
    selectTarjeta.value = '';
  }
}

/**
 * Carga las opciones del select de tarjetas.
 */
function poblarSelectTarjetas(tipoRequerido) {
  var tipo = tipoRequerido || null;
  var select = document.getElementById('gasto-tarjeta');

  select.innerHTML = '<option value="">Seleccionar tarjeta</option>';

  listadoTarjetas.forEach(function (tarjeta) {
    if (tipo && tarjeta.tipo !== tipo) {
      return;
    }

    var option = document.createElement('option');
    option.value = tarjeta.id;
    option.textContent = tarjeta.nombre + ' (' + tarjeta.tipo + ') - ' + tarjeta.banco + ' •••• ' + tarjeta.ultimos_4;
    select.appendChild(option);
  });
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
    tr.innerHTML = '' +
      '<td>' + gasto.fecha + '</td>' +
      '<td>' + gasto.descripcion + '</td>' +
      '<td>' + gasto.categoria + '</td>' +
      '<td>' + gasto.metodo_pago + '</td>' +
      '<td>' + formatearMoneda(gasto.monto) + '</td>' +
      '<td>' + gasto.cantidad_cuotas + ' x ' + formatearMoneda(gasto.valor_cuota) + '</td>' +
      '<td>' + (gasto.tarjeta_nombre || '-') + '</td>' +
      '<td>' +
      '  <button class="btn btn-secundario btn-accion" data-accion="editar" data-id="' + gasto.id + '">Editar</button>' +
      '  <button class="btn btn-eliminar btn-accion" data-accion="eliminar" data-id="' + gasto.id + '">Eliminar</button>' +
      '</td>';

    tbody.appendChild(tr);
  });
}

/**
 * Pide tarjetas al backend para usarlas en el select del formulario.
 */
async function cargarTarjetas() {
  var respuesta = await apiRequest('tarjetas.php');
  listadoTarjetas = respuesta.datos.tarjetas || [];
  poblarSelectTarjetas(null);
  toggleTarjetaSegunMetodo();
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
  document.getElementById('gasto-id').value = '';
  document.getElementById('gasto-descripcion').value = '';
  document.getElementById('gasto-monto').value = '';
  document.getElementById('gasto-fecha').value = hoyISO();
  document.getElementById('gasto-categoria').value = '';
  document.getElementById('gasto-metodo').value = 'efectivo';
  document.getElementById('gasto-tarjeta').value = '';
  document.getElementById('gasto-cuotas').value = 1;
  document.getElementById('gasto-valor-cuota').value = 0;
  document.getElementById('gasto-valor-total').value = 0;

  document.getElementById('titulo-form-gasto').textContent = 'Nuevo gasto';
  document.getElementById('btn-cancelar-gasto').classList.add('oculto');

  toggleTarjetaSegunMetodo();
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

  toggleTarjetaSegunMetodo();

  if (gasto.tarjeta_id) {
    document.getElementById('gasto-tarjeta').value = gasto.tarjeta_id;
  } else {
    document.getElementById('gasto-tarjeta').value = '';
  }

  document.getElementById('gasto-cuotas').value = gasto.cantidad_cuotas;
  document.getElementById('gasto-valor-cuota').value = gasto.valor_cuota;
  document.getElementById('gasto-valor-total').value = gasto.valor_total;

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
    var accion = boton.dataset.accion;
    var gasto = buscarGastoPorId(id);

    if (!gasto) {
      return;
    }

    if (accion === 'editar') {
      cargarEnFormulario(gasto);
      return;
    }

    if (accion === 'eliminar') {
      var confirmar = confirm('¿Seguro que querés eliminar este gasto?');
      if (!confirmar) {
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
 */
function construirPayloadGasto() {
  return {
    descripcion: document.getElementById('gasto-descripcion').value.trim(),
    monto: Number(document.getElementById('gasto-monto').value),
    fecha: document.getElementById('gasto-fecha').value,
    categoria: document.getElementById('gasto-categoria').value.trim(),
    metodo_pago: document.getElementById('gasto-metodo').value,
    tarjeta_id: document.getElementById('gasto-tarjeta').value || null,
    cantidad_cuotas: Number(document.getElementById('gasto-cuotas').value),
    valor_cuota: Number(document.getElementById('gasto-valor-cuota').value),
    valor_total: Number(document.getElementById('gasto-valor-total').value)
  };
}

/**
 * Conecta los eventos del formulario principal.
 */
function bindFormulario() {
  var form = document.getElementById('form-gasto');
  var metodo = document.getElementById('gasto-metodo');

  metodo.addEventListener('change', function () {
    toggleTarjetaSegunMetodo();
  });

  form.addEventListener('submit', async function (evento) {
    evento.preventDefault();

    var id = document.getElementById('gasto-id').value;
    var payload = construirPayloadGasto();

    try {
      if (id) {
        await apiRequest('gastos.php?id=' + id, {
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

  document.getElementById('btn-cancelar-gasto').addEventListener('click', function () {
    resetFormulario();
  });
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
