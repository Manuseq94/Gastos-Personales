/*
  Este archivo maneja autenticación en el frontend.
*/

/**
 * Verifica si la página actual requiere login.
*/
function requerirAutenticacion() {
  var token = obtenerToken();
  var enPublica = false;

  if (location.pathname.endsWith('index.html') || location.pathname.endsWith('/frontend/')) {
    enPublica = true;
  }

  if (!token && !enPublica) {
    location.href = 'index.html';
  }
}

/**
 * Conecta el botón de cerrar sesión si existe en la pantalla.
 */
function bindLogout() {
  var boton = document.getElementById('btn-logout');
  if (!boton) {
    return;
  }

  boton.addEventListener('click', function () {
    limpiarSesion();
    location.href = 'index.html';
  });
}

/**
 * Inicializa el formulario de inicio de sesión.
 */
function inicializarLogin() {
  var form = document.getElementById('form-login');
  if (!form) {
    return;
  }

  // Si ya está logueado pasa a la pantalla de inicio.
  if (obtenerToken()) {
    location.href = 'dashboard.html';
    return;
  }

  form.addEventListener('submit', async function (evento) {
    evento.preventDefault();
    mostrarMensaje('mensaje-global', 'Ingresando...', 'exito');

    var payload = {
      email: document.getElementById('login-email').value.trim(),
      password: document.getElementById('login-password').value
    };

    try {
      var respuesta = await apiRequest('login.php', {
        method: 'POST',
        body: JSON.stringify(payload)
      });

      guardarSesion(respuesta.datos.token, respuesta.datos.usuario);
      mostrarMensaje('mensaje-global', 'Inicio de sesión exitoso. Redirigiendo...', 'exito');
      location.href = 'dashboard.html';
    } catch (error) {
      mostrarMensaje('mensaje-global', error.message, 'error');
    }
  });
}

/**
 * Inicializa el formulario de registro.
 */
function inicializarRegistro() {
  var form = document.getElementById('form-registro');
  if (!form) {
    return;
  }

  form.addEventListener('submit', async function (evento) {
    evento.preventDefault();
    mostrarMensaje('mensaje-global', 'Registrando usuario...', 'exito');

    var payload = {
      nombre: document.getElementById('registro-nombre').value.trim(),
      email: document.getElementById('registro-email').value.trim(),
      password: document.getElementById('registro-password').value
      // El día de cierre se configura dentro de la app (Inicio > Configuración de balance).
    };

    try {
      var respuesta = await apiRequest('register.php', {
        method: 'POST',
        body: JSON.stringify(payload)
      });

      guardarSesion(respuesta.datos.token, respuesta.datos.usuario);
      mostrarMensaje('mensaje-global', 'Cuenta creada correctamente. Redirigiendo...', 'exito');
      location.href = 'dashboard.html';
    } catch (error) {
      mostrarMensaje('mensaje-global', error.message, 'error');
    }
  });
}

// Inicio de la aplicación: cuando el DOM esté listo, verificamos autenticación y conectamos eventos.
document.addEventListener('DOMContentLoaded', function () {
  requerirAutenticacion();
  bindLogout();
  inicializarLogin();
  inicializarRegistro();
});
