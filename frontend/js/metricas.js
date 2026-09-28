/*
  Este archivo maneja la pantalla de inicio.
  Acá pedimos métricas al backend y actualizamos la UI.
  También guardamos el día de cierre elegido por el usuario.
*/

/**
 * Devuelve el mes actual en formato YYYY-MM para el input type="month".
 */
function mesActualInputValue() {
  var hoy = new Date();
  var anio = hoy.getFullYear();
  var mes = String(hoy.getMonth() + 1).padStart(2, '0');

  return anio + '-' + mes;
}

/**
 * Carga métricas desde la API, las pinta y muestra en pantalla.
 */
async function cargarMetricas() {
  var mesInput = document.getElementById('mes-consulta');
  var mes = mesActualInputValue();

  if (mesInput && mesInput.value) {
    mes = mesInput.value;
  }

  try {
    var resp = await apiRequest('metricas.php?mes=' + encodeURIComponent(mes));
    var datos = resp.datos;

    document.getElementById('total-gastos').textContent = formatearMoneda(datos.total_gastos_mes);
    document.getElementById('total-ingresos').textContent = formatearMoneda(datos.total_ingresos_mes);
    document.getElementById('economia').textContent = formatearMoneda(datos.economia.valor) + ' (' + datos.economia.estado + ')';
    document.getElementById('balance-cierre').textContent = formatearMoneda(datos.balance_por_cierre);
    document.getElementById('dia-cierre').value = datos.dia_cierre_balance;

    var listaCategorias = document.getElementById('lista-categorias');
    listaCategorias.innerHTML = '';

    if (!datos.gastos_por_categoria || datos.gastos_por_categoria.length === 0) {
      listaCategorias.innerHTML = '<li>No hay gastos registrados para este mes.</li>';
    } else {
      datos.gastos_por_categoria.forEach(function (item) {
        var li = document.createElement('li');
        li.textContent = item.categoria + ': ' + formatearMoneda(item.total);
        listaCategorias.appendChild(li);
      });
    }

    mostrarMensaje('mensaje-metricas', 'Métricas cargadas del mes ' + mes + '.', 'exito');
  } catch (error) {
    mostrarMensaje('mensaje-metricas', error.message, 'error');
  }
}

/**
 * Configura los eventos de la pantalla principal.
 */
function inicializarMetricas() {
  var mesInput = document.getElementById('mes-consulta');
  if (!mesInput) {
    return;
  }

  mesInput.value = mesActualInputValue();
  mesInput.addEventListener('change', function () {
    cargarMetricas();
  });

  var formCierre = document.getElementById('form-dia-cierre');
  formCierre.addEventListener('submit', async function (evento) {
    evento.preventDefault();

    var dia = Number(document.getElementById('dia-cierre').value);

    try {
      await apiRequest('metricas.php', {
        method: 'PUT',
        body: JSON.stringify({ dia_cierre_balance: dia })
      });

      mostrarMensaje('mensaje-cierre', 'Día de cierre guardado correctamente.', 'exito');
      await cargarMetricas();
    } catch (error) {
      mostrarMensaje('mensaje-cierre', error.message, 'error');
    }
  });

  cargarMetricas();
}

document.addEventListener('DOMContentLoaded', function () {
  inicializarMetricas();
});
