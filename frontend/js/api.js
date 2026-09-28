/*
  Este archivo concentra funciones comunes del frontend:
  - sesión (guardar clave/usuario)
  - llamadas a la API con fetch
  - mensajes en pantalla y formato de moneda
  Se importa en todas las páginas.
*/

/**
  * Devuelve la URL base de la API según la URL del frontend.
 */
function obtenerUrlApi() {
  var fallback = 'http://localhost/gastos-personales-app/backend/api';

  // Controlamos si estamos en http o https.
  if (location.protocol !== 'http:' && location.protocol !== 'https:') {
    return fallback;
  }

  var path = location.pathname;
  var indiceFrontend = path.indexOf('/frontend');

  if (indiceFrontend === -1) {
    return fallback;
  }

  // Tomamos todo lo que está antes de /frontend para armar /backend/api.
  var baseAntesDeFrontend = path.substring(0, indiceFrontend);
  if (baseAntesDeFrontend === '') {
    baseAntesDeFrontend = '/';
  }

  if (baseAntesDeFrontend.length > 1 && baseAntesDeFrontend.charAt(baseAntesDeFrontend.length - 1) === '/') {
    baseAntesDeFrontend = baseAntesDeFrontend.substring(0, baseAntesDeFrontend.length - 1);
  }

  return location.origin + baseAntesDeFrontend + '/backend/api';
}

const API_BASE_URL = obtenerUrlApi();

/**
 * Lee la clave de sesión guardada en localStorage.
 * Devuelve un string con clave o vacío si no hay sesión
 */
function obtenerToken() {
  var token = localStorage.getItem('token_sesion');
  if (!token) {
    return '';
  }
  return token;
}

/**
 * Guarda la clave de sesión y usuario en localStorage.
 * Recibe la clave y el objeto usuario.
 */
function guardarSesion(token, usuario) {
  localStorage.setItem('token_sesion', token);
  localStorage.setItem('usuario_actual', JSON.stringify(usuario));
}

/**
 * Limpia datos de sesión local.
  */
function limpiarSesion() {
  localStorage.removeItem('token_sesion');
  localStorage.removeItem('usuario_actual');
}

/**
 * Devuelve usuario actual desde localStorage.
  */
function obtenerUsuarioActual() {
  var raw = localStorage.getItem('usuario_actual');
  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw);
  } catch (error) {
    return null;
  }
}

/**
 * Wrapper para hacer llamadas a la API con fetch.
 */
async function apiRequest(endpoint, options) {
  var opciones = options;
  if (!opciones) {
    opciones = {};
  }

  var token = obtenerToken();

  var headers = {
    'Content-Type': 'application/json'
  };

  if (opciones.headers) {
    for (var clave in opciones.headers) {
      if (Object.prototype.hasOwnProperty.call(opciones.headers, clave)) {
        headers[clave] = opciones.headers[clave];
      }
    }
  }

  if (token) {
    headers.Authorization = 'Bearer ' + token;
  }

  var respuesta = await fetch(API_BASE_URL + '/' + endpoint, {
    method: opciones.method,
    headers: headers,
    body: opciones.body
  });

  var data = null;

  try {
    data = await respuesta.json();
  } catch (errorParse) {
    var mensajeNoJson = 'El servidor no respondió en formato JSON (código HTTP ' + respuesta.status + '). ' +
      'Fijate que Apache y MySQL estén encendidos, que la carpeta del proyecto esté en htdocs con el mismo nombre y que la URL del backend sea la correcta.';

    data = {
      ok: false,
      mensaje: mensajeNoJson,
      errores: {
        detalle: 'Respuesta no JSON o vacía.'
      }
    };
  }

  var respuestaOk = respuesta.ok;
  var dataOk = false;
  if (data && data.ok === true) {
    dataOk = true;
  }

  if (!respuestaOk || !dataOk) {
    var mensaje = 'Error HTTP ' + respuesta.status;
    if (data && data.mensaje) {
      mensaje = data.mensaje;
    }

    var error = new Error(mensaje);
    error.status = respuesta.status;
    error.payload = data;

    // Si la clave de sesión es inválida limpiamos la sesión y redirigimos a login.
    if (respuesta.status === 401) {
      limpiarSesion();
      var estaEnLogin = location.pathname.endsWith('index.html') || location.pathname.endsWith('/frontend/');
      if (!estaEnLogin) {
        location.href = 'index.html';
      }
    }

    throw error;
  }

  return data;
}

/**
 * Muestra un mensaje de éxito o error en un contenedor.
 */
function mostrarMensaje(idElemento, texto, tipo) {
  var tipoMensaje = tipo;
  if (!tipoMensaje) {
    tipoMensaje = 'exito';
  }

  var elemento = document.getElementById(idElemento);
  if (!elemento) {
    return;
  }

  elemento.textContent = texto;
  elemento.classList.remove('exito');
  elemento.classList.remove('error');
  elemento.classList.add(tipoMensaje);
}

/**
 * Formatea los números a moneda argentina.
 */
function formatearMoneda(valor) {
  var numero = Number(valor || 0);
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2
  }).format(numero);
}
