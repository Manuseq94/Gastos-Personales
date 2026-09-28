/*
  Este archivo resuelve el CRUD de ingresos.
  Se encarga de dibujar la tabla, enviar altas y modificaciones,
  cargar datos para editar y borrar registros.
*/

var listadoIngresos = [];

/**
 * Devuelve fecha de hoy en formato YYYY-MM-DD.
 */
function hoyISOIngreso() {
  return new Date().toISOString().slice(0, 10);
}

/**
 * Renderiza la tabla de ingresos en el DOM.
 */
function renderTablaIngresos() {
  var tbody = document.getElementById('tabla-ingresos');
  tbody.innerHTML = '';

  if (listadoIngresos.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5">No hay ingresos registrados.</td></tr>';
    return;
  }

  listadoIngresos.forEach(function (ingreso) {
    var tr = document.createElement('tr');
    tr.innerHTML = '' +
      '<td>' + ingreso.fecha + '</td>' +
      '<td>' + ingreso.descripcion + '</td>' +
      '<td>' + formatearMoneda(ingreso.monto) + '</td>' +
      '<td>' + (ingreso.es_fijo ? 'Sí' : 'No') + '</td>' +
      '<td>' +
      '  <button class="btn btn-secundario btn-accion" data-accion="editar" data-id="' + ingreso.id + '">Editar</button>' +
      '  <button class="btn btn-eliminar btn-accion" data-accion="eliminar" data-id="' + ingreso.id + '">Eliminar</button>' +
      '</td>';

    tbody.appendChild(tr);
  });
}

/**
 * Trae ingresos del backend y refresca tabla.
  */
async function cargarIngresos() {
  try {
    var respuesta = await apiRequest('ingresos.php');
    listadoIngresos = respuesta.datos.ingresos || [];
    renderTablaIngresos();
  } catch (error) {
    mostrarMensaje('mensaje-ingreso', error.message, 'error');
  }
}

/**
 * Limpia formulario y lo deja listo para un nuevo ingreso.
 */
function resetFormularioIngreso() {
  document.getElementById('ingreso-id').value = '';
  document.getElementById('ingreso-descripcion').value = '';
  document.getElementById('ingreso-monto').value = '';
  document.getElementById('ingreso-fecha').value = hoyISOIngreso();
  document.getElementById('ingreso-es-fijo').checked = false;

  document.getElementById('titulo-form-ingreso').textContent = 'Nuevo ingreso';
  document.getElementById('btn-cancelar-ingreso').classList.add('oculto');
}

/**
 * Carga un ingreso en el formulario para editar.
 */
function cargarIngresoEnFormulario(ingreso) {
  document.getElementById('ingreso-id').value = ingreso.id;
  document.getElementById('ingreso-descripcion').value = ingreso.descripcion;
  document.getElementById('ingreso-monto').value = ingreso.monto;
  document.getElementById('ingreso-fecha').value = ingreso.fecha;
  document.getElementById('ingreso-es-fijo').checked = Boolean(ingreso.es_fijo);

  document.getElementById('titulo-form-ingreso').textContent = 'Editando ingreso #' + ingreso.id;
  document.getElementById('btn-cancelar-ingreso').classList.remove('oculto');
}

/**
 * Busca ingreso por id en el array local.
 */
function buscarIngresoPorId(id) {
  for (var i = 0; i < listadoIngresos.length; i += 1) {
    if (Number(listadoIngresos[i].id) === Number(id)) {
      return listadoIngresos[i];
    }
  }

  return null;
}

/**
 * Gestiona los botones de edición y eliminación de la tabla de ingresos.
 */
function bindTablaIngresos() {
  var tabla = document.getElementById('tabla-ingresos');

  tabla.addEventListener('click', async function (evento) {
    var boton = evento.target.closest('button[data-accion]');
    if (!boton) {
      return;
    }

    var id = Number(boton.dataset.id);
    var accion = boton.dataset.accion;
    var ingreso = buscarIngresoPorId(id);

    if (!ingreso) {
      return;
    }

    if (accion === 'editar') {
      cargarIngresoEnFormulario(ingreso);
      return;
    }

    if (accion === 'eliminar') {
      var confirmar = confirm('¿Seguro que querés eliminar este ingreso?');
      if (!confirmar) {
        return;
      }

      try {
        await apiRequest('ingresos.php?id=' + id, { method: 'DELETE' });
        mostrarMensaje('mensaje-ingreso', 'Ingreso eliminado correctamente.', 'exito');
        await cargarIngresos();
      } catch (error) {
        mostrarMensaje('mensaje-ingreso', error.message, 'error');
      }
    }
  });
}

/**
 * Prepara los datos del ingreso para enviarlos al servidor.
 */
function construirPayloadIngreso() {
  return {
    descripcion: document.getElementById('ingreso-descripcion').value.trim(),
    monto: Number(document.getElementById('ingreso-monto').value),
    fecha: document.getElementById('ingreso-fecha').value,
    es_fijo: document.getElementById('ingreso-es-fijo').checked
  };
}

/**
 * Configura el envío del formulario y la acción del botón cancelar.
 */
function bindFormularioIngresos() {
  var form = document.getElementById('form-ingreso');

  form.addEventListener('submit', async function (evento) {
    evento.preventDefault();

    var id = document.getElementById('ingreso-id').value;
    var payload = construirPayloadIngreso();

    try {
      if (id) {
        await apiRequest('ingresos.php?id=' + id, {
          method: 'PUT',
          body: JSON.stringify(payload)
        });
        mostrarMensaje('mensaje-ingreso', 'Ingreso actualizado correctamente.', 'exito');
      } else {
        await apiRequest('ingresos.php', {
          method: 'POST',
          body: JSON.stringify(payload)
        });
        mostrarMensaje('mensaje-ingreso', 'Ingreso creado correctamente.', 'exito');
      }

      resetFormularioIngreso();
      await cargarIngresos();
    } catch (error) {
      mostrarMensaje('mensaje-ingreso', error.message, 'error');
    }
  });

  document.getElementById('btn-cancelar-ingreso').addEventListener('click', function () {
    resetFormularioIngreso();
  });
}

/**
 * Inicializa la página de ingresos.
 */
function initIngresosPage() {
  if (!document.getElementById('form-ingreso')) {
    return;
  }

  resetFormularioIngreso();
  bindFormularioIngresos();
  bindTablaIngresos();
  cargarIngresos();
}

document.addEventListener('DOMContentLoaded', function () {
  initIngresosPage();
});
