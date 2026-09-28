<?php
/*
 * Acá centralizamos todo lo relacionado a respuestas JSON de la API.
 * También se configura CORS y el preflight OPTIONS.
 * La idea es que todos los endpoints respondan de forma consistente.
 */

require_once __DIR__ . '/../config/database.php';

/*
 * Red de seguridad #1:
 * apagamos display_errors para que PHP no imprima HTML de errores,
 * porque ese HTML rompe el formato JSON que espera el frontend.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Esta bandera simple nos dice si ya enviamos una respuesta JSON oficial.
if (!isset($GLOBALS['respuesta_enviada'])) {
    $GLOBALS['respuesta_enviada'] = false;
}

/**
 * Configura cabeceras CORS y tipo de contenido JSON.
 */
function configurarCors()
{
    $origen = env('CORS_ALLOW_ORIGIN', '*');
    if ($origen === null || $origen === '') {
        $origen = '*';
    }

    header('Access-Control-Allow-Origin: ' . $origen);
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Content-Type: application/json; charset=utf-8');
}

/**
 * Resuelve el preflight de CORS.
*/
function manejarPreflight()
{
    configurarCors();

    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        $GLOBALS['respuesta_enviada'] = true;
        http_response_code(204);
        exit;
    }
}

/**
 * Envía una respuesta JSON y termina el script.

 */
function responderJson($codigoHttp, $payload)
{
    configurarCors();
    $GLOBALS['respuesta_enviada'] = true;
    http_response_code($codigoHttp);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Respuesta estándar para casos exitosos.
 */
function responderExito($mensaje, $datos = array(), $codigoHttp = 200)
{
    responderJson($codigoHttp, array(
        'ok' => true,
        'mensaje' => $mensaje,
        'datos' => $datos,
    ));
}

/**
 * Respuesta estándar para errores.
 */
function responderError($mensaje, $codigoHttp = 400, $errores = array())
{
    responderJson($codigoHttp, array(
        'ok' => false,
        'mensaje' => $mensaje,
        'errores' => $errores,
    ));
}

/**
 * Lee el body JSON de la request y lo transforma a array.
 */
function obtenerBodyJson()
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return array();
    }

    $datos = json_decode($raw, true);
    if (!is_array($datos)) {
        responderError('El cuerpo de la petición debe ser JSON válido.', 422);
    }

    return $datos;
}

/*
 * Red de seguridad #2:
 * si aparece una excepción que nadie capturó, devolvemos JSON 500.
 * Los try/catch de los endpoints siguen teniendo prioridad: si ya capturan,
 * este handler no se ejecuta.
 */
set_exception_handler(function ($excepcion) {
    if ($GLOBALS['respuesta_enviada']) {
        return;
    }

    if (!headers_sent()) {
        configurarCors();
        http_response_code(500);
    }

    $GLOBALS['respuesta_enviada'] = true;

    echo json_encode(array(
        'ok' => false,
        'mensaje' => 'Ocurrió un error inesperado en el servidor.',
        'errores' => array(
            'detalle' => $excepcion->getMessage(),
        ),
    ), JSON_UNESCAPED_UNICODE);

    exit;
});

/*
 * Red de seguridad #3:
 * cubre errores fatales (por ejemplo un error de PHP que frena todo).
 * Si todavía no enviamos respuesta, devolvemos JSON 500 para no romper el frontend.
 */
register_shutdown_function(function () {
    if ($GLOBALS['respuesta_enviada']) {
        return;
    }

    $error = error_get_last();
    if (!$error) {
        return;
    }

    $tiposFatales = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
    if (!in_array($error['type'], $tiposFatales, true)) {
        return;
    }

    if (!headers_sent()) {
        configurarCors();
        http_response_code(500);
    }

    $GLOBALS['respuesta_enviada'] = true;

    echo json_encode(array(
        'ok' => false,
        'mensaje' => 'Ocurrió un error fatal en el servidor.',
        'errores' => array(
            'detalle' => $error['message'],
        ),
    ), JSON_UNESCAPED_UNICODE);
});
