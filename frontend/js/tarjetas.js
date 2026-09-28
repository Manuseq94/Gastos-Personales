/*
  Este archivo maneja el CRUD de tarjetas.
  Además muestra abajo de cada tarjeta los gastos asociados.
*/

var tarjetas = [];

/**
 * Crea el HTML de las tarjetas y lo inserta en el DOM.
 */
function renderTarjetas() {
  var contenedor = document.getElementById('lista-tarjetas');
  contenedor.innerHTML = '';

  if (tarjetas.length === 0) {
    contenedor.innerHTML = '<p>No hay tarjetas registradas.</p>';
    return;
  }

  tarjetas.forEach(function (tarjeta) {
    var card = document.createElement('article');
    card.className = 'tarjeta-item';

    var gastos = tarjeta.gastos_asociados || [];
    var listaGastos = '';

    if (gastos.length === 0) {
      listaGastos = '<li>Sin gastos asociados.</li>';
    } else {
      gastos.forEach(function (gasto) {
        listaGastos += '<li>' + gasto.fecha + ' - ' + gasto.descripcion + ' (' + formatearMoneda(gasto.monto) + ')</li>';
      });
    }

    card.innerHTML = '' +
      '<h3>' + tarjeta.nombre + ' (' + tarjeta.tipo + ')</h3>' +
      '<p><strong>Banco:</strong> ' + tarjeta.banco + '</p>' +
      '<p><strong>Últimos 4:</strong> ' + tarjeta.ultimos_4 + '</p>' +
      '<div>' +
      '  <button class="btn btn-secundario btn-accion" data-accion="editar" data-id="' + tarjeta.id + '">Editar</button>' +
      '  <button class="btn btn-eliminar btn-accion" data-accion="eliminar" data-id="' + tarjeta.id + '">Eliminar</button>' +
      '</div>' +
      '<h4>Gastos asociados</h4>' +
      '<ul class="lista-simple">' + listaGastos + '</ul>';

    contenedor.appendChild(card);
  });
}

/**
 * Se pide al backend la lista de tarjetas y 
 * sus gastos asociados, y se renderiza.
 */
async function cargarTarjetasConGastos() {
  try {
    var respuesta = await apiRequest('tarjetas.php?con_gastos=1');
    tarjetas = respuesta.datos.tarjetas || [];
    renderTarjetas();
  } catch (error) {
    mostrarMensaje('mensaje-tarjeta', error.message, 'error');
  }
}

/**
 * Deja el formulario limpio para agregar una nueva tarjeta.
 */
function resetFormTarjeta() {
  document.getElementById('tarjeta-id').value = '';
  document.getElementById('tarjeta-nombre').value = '';
  document.getElementById('tarjeta-tipo').value = 'debito';
  document.getElementById('tarjeta-banco').value = '';
  document.getElementById('tarjeta-ultimos4').value = '';

  document.getElementById('titulo-form-tarjeta').textContent = 'Nueva tarjeta';
  document.getElementById('btn-cancelar-tarjeta').classList.add('oculto');
}

/**
 * Carga una tarjeta en el formulario para editar.
 */
function cargarTarjetaEnFormulario(tarjeta) {
  document.getElementById('tarjeta-id').value = tarjeta.id;
  document.getElementById('tarjeta-nombre').value = tarjeta.nombre;
  document.getElementById('tarjeta-tipo').value = tarjeta.tipo;
  document.getElementById('tarjeta-banco').value = tarjeta.banco;
  document.getElementById('tarjeta-ultimos4').value = tarjeta.ultimos_4;

  document.getElementById('titulo-form-tarjeta').textContent = 'Editando tarjeta #' + tarjeta.id;
  document.getElementById('btn-cancelar-tarjeta').classList.remove('oculto');
}

/**
 * Busca una tarjeta por id.
 */
function buscarTarjetaPorId(id) {
  for (var i = 0; i < tarjetas.length; i += 1) {
    if (Number(tarjetas[i].id) === Number(id)) {
      return tarjetas[i];
    }
  }

  return null;
}

/**
 * Gestiona las acciones de edición y eliminación de las tarjetas mostradas.
 */
function bindEventosTarjetas() {
  var lista = document.getElementById('lista-tarjetas');

  lista.addEventListener('click', async function (evento) {
    var boton = evento.target.closest('button[data-accion]');
    if (!boton) {
      return;
    }

    var accion = boton.dataset.accion;
    var id = Number(boton.dataset.id);
    var tarjeta = buscarTarjetaPorId(id);

    if (!tarjeta) {
      return;
    }

    if (accion === 'editar') {
      cargarTarjetaEnFormulario(tarjeta);
      return;
    }

    if (accion === 'eliminar') {
      var confirmar = confirm('¿Seguro que querés eliminar esta tarjeta?');
      if (!confirmar) {
        return;
      }

      try {
        await apiRequest('tarjetas.php?id=' + id, { method: 'DELETE' });
        mostrarMensaje('mensaje-tarjeta', 'Tarjeta eliminada correctamente.', 'exito');
        await cargarTarjetasConGastos();
      } catch (error) {
        mostrarMensaje('mensaje-tarjeta', error.message, 'error');
      }
    }
  });
}

/**
 * Prepara los datos de la tarjeta para enviarlos al servidor.
 */
function construirPayloadTarjeta() {
  return {
    nombre: document.getElementById('tarjeta-nombre').value.trim(),
    tipo: document.getElementById('tarjeta-tipo').value,
    banco: document.getElementById('tarjeta-banco').value.trim(),
    ultimos_4: document.getElementById('tarjeta-ultimos4').value.trim()
  };
}

/**
 *  Configura las acciones del formulario principal.
 */
function bindFormTarjeta() {
  var form = document.getElementById('form-tarjeta');

  form.addEventListener('submit', async function (evento) {
    evento.preventDefault();

    var id = document.getElementById('tarjeta-id').value;
    var payload = construirPayloadTarjeta();

    try {
      if (id) {
        await apiRequest('tarjetas.php?id=' + id, {
          method: 'PUT',
          body: JSON.stringify(payload)
        });
        mostrarMensaje('mensaje-tarjeta', 'Tarjeta actualizada correctamente.', 'exito');
      } else {
        await apiRequest('tarjetas.php', {
          method: 'POST',
          body: JSON.stringify(payload)
        });
        mostrarMensaje('mensaje-tarjeta', 'Tarjeta creada correctamente.', 'exito');
      }

      resetFormTarjeta();
      await cargarTarjetasConGastos();
    } catch (error) {
      mostrarMensaje('mensaje-tarjeta', error.message, 'error');
    }
  });

  document.getElementById('btn-cancelar-tarjeta').addEventListener('click', function () {
    resetFormTarjeta();
  });
}

/**
 * Inicializa la página tarjetas.html.
 */
function initTarjetasPage() {
  if (!document.getElementById('form-tarjeta')) {
    return;
  }

  resetFormTarjeta();
  bindFormTarjeta();
  bindEventosTarjetas();
  cargarTarjetasConGastos();
}

document.addEventListener('DOMContentLoaded', function () {
  initTarjetasPage();
});
