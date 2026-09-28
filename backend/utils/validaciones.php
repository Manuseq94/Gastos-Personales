<?php
/*
 * Este archivo junta validaciones reutilizables del proyecto.
 * Sirve para evitar repetir controles en cada endpoint.
 */

require_once __DIR__ . '/response.php';

/**
 * Verifica que estén todos los campos obligatorios.
 */
function validarRequeridos($datos, $campos)
{
    $faltantes = array();

    foreach ($campos as $campo) {
        if (!array_key_exists($campo, $datos) || $datos[$campo] === null || $datos[$campo] === '') {
            $faltantes[] = $campo;
        }
    }

    if (count($faltantes) > 0) {
        responderError('Faltan campos obligatorios.', 422, array('faltantes' => $faltantes));
    }
}

/**
 * Valida formato básico de email.
 */
function validarEmail($email)
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        responderError('El email no tiene un formato válido.', 422);
    }
}

/**
 * Valida que un monto sea numérico y mayor a cero.
*/
function validarMonto($monto, $nombreCampo = 'monto')
{
    if (!is_numeric($monto)) {
        responderError('El campo ' . $nombreCampo . ' debe ser numérico.', 422);
    }

    $valor = (float) $monto;
    if ($valor <= 0) {
        responderError('El campo ' . $nombreCampo . ' debe ser mayor a 0.', 422);
    }

    return round($valor, 2);
}

/**
 * Valida una fecha con formato YYYY-MM-DD.
 */
function validarFecha($fecha, $nombreCampo = 'fecha')
{
    $objFecha = DateTime::createFromFormat('Y-m-d', $fecha);

    if (!$objFecha || $objFecha->format('Y-m-d') !== $fecha) {
        responderError('El campo ' . $nombreCampo . ' debe tener formato YYYY-MM-DD.', 422);
    }
}

/**
 * Valida el día de cierre de balance.
 */
function validarDiaCierre($dia)
{
    if (!is_numeric($dia)) {
        responderError('El día de cierre debe ser un número.', 422);
    }

    $diaEntero = (int) $dia;
    if ($diaEntero < 1 || $diaEntero > 28) {
        responderError('El día de cierre debe estar entre 1 y 28.', 422);
    }

    return $diaEntero;
}

/**
 * Valida el método de pago permitido para gastos.
 */
function validarMetodoPago($metodo)
{
    $permitidos = array('efectivo', 'debito', 'credito', 'billetera_virtual');

    if (!in_array($metodo, $permitidos, true)) {
        responderError('Método de pago inválido.', 422, array('permitidos' => $permitidos));
    }
}

/**
 * Valida el tipo de tarjeta permitido.
 */
function validarTipoTarjeta($tipo)
{
    $permitidos = array('debito', 'credito');

    if (!in_array($tipo, $permitidos, true)) {
        responderError('Tipo de tarjeta inválido.', 422, array('permitidos' => $permitidos));
    }
}

/**
 * Valida que los últimos 4 dígitos tengan exactamente 4 números.
 */
function validarUltimosCuatro($ultimosCuatro)
{
    $texto = trim((string) $ultimosCuatro);

    if (!preg_match('/^\d{4}$/', $texto)) {
        responderError('Los últimos 4 dígitos deben contener exactamente 4 números.', 422);
    }

    return $texto;
}
