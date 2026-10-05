<?php
/*
 * Endpoint de métricas del inicio (solo lectura).
 * GET: muestra el resumen mensual (gastos, ingresos, economía, balance por cierre y categorías).
 * El día de cierre se configura en configuracion.php: este endpoint solo lo lee.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validaciones.php';
require_once __DIR__ . '/../utils/auth.php';

manejarPreflight();

$metodo = 'GET';
if (isset($_SERVER['REQUEST_METHOD'])) {
    $metodo = $_SERVER['REQUEST_METHOD'];
}

$pdo = obtenerConexion();
$usuario = requerirUsuarioAutenticado($pdo);
$usuarioId = (int) $usuario['id'];

/**
 * Valida el parámetro mes (YYYY-MM). Si no llega, usa el mes actual.
 * Recibe el parámetro mes (string) o null y devuelve un array con año y mes.
 */
function validarMesConsulta($mes)
{
    if ($mes === null || trim($mes) === '') {
        return array((int) date('Y'), (int) date('m'));
    }

    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
        responderError('El parámetro mes debe tener formato YYYY-MM.', 422);
    }

    $partes = explode('-', $mes);
    $anio = (int) $partes[0];
    $mesNumero = (int) $partes[1];

    if ($mesNumero < 1 || $mesNumero > 12) {
        responderError('El mes consultado no es válido.', 422);
    }

    return array($anio, $mesNumero);
}

/**
 * Calcula inicio y fin del mes calendario.
 * Recibe año y mes (int) y devuelve un array con fecha de inicio y fin del mes.
 */
function obtenerRangoCalendario($anio, $mes)
{
    $inicio = sprintf('%04d-%02d-01', $anio, $mes);
    $fin = date('Y-m-t', strtotime($inicio));

    return array($inicio, $fin);
}

/**
 * Calcula el rango para hacer el balance por día de cierre.
 * Recibe año, mes y día de cierre (int) y devuelve un array con fecha de inicio y fin del balance.
 */
function obtenerRangoBalance($anio, $mes, $diaCierre)
{
    // Si el cierre es día 1, el rango coincide con el mes calendario.
    if ($diaCierre === 1) {
        return obtenerRangoCalendario($anio, $mes);
    }

    $actual = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $anio, $mes));
    if (!$actual) {
        responderError('No se pudo calcular el período de balance.', 500);
    }

    // El período termina el día de cierre (inclusive) y arranca el día siguiente
    // al cierre anterior. Antes ambos extremos usaban el mismo día y como BETWEEN
    // es inclusivo, los movimientos del día de cierre se contaban en dos períodos.
    $cierreAnterior = clone $actual;
    $cierreAnterior->modify('-1 month');
    $cierreAnterior->setDate((int) $cierreAnterior->format('Y'), (int) $cierreAnterior->format('m'), $diaCierre);
    $cierreAnterior->modify('+1 day');

    $inicio = $cierreAnterior->format('Y-m-d');
    $fin = sprintf('%04d-%02d-%02d', (int) $actual->format('Y'), (int) $actual->format('m'), $diaCierre);

    return array($inicio, $fin);
}

try {
    if ($metodo !== 'GET') {
        responderError('Método no permitido.', 405);
    }

    $mesParam = null;
    if (isset($_GET['mes'])) {
        $mesParam = $_GET['mes'];
    }

    $resultadoMes = validarMesConsulta($mesParam);
    $anio = $resultadoMes[0];
    $mes = $resultadoMes[1];

    $diaCierre = (int) $usuario['dia_cierre_balance'];
    if (isset($_GET['dia_cierre'])) {
        $diaCierre = validarDiaCierre($_GET['dia_cierre']);
    }

    $rangoCalendario = obtenerRangoCalendario($anio, $mes);
    $inicioMes = $rangoCalendario[0];
    $finMes = $rangoCalendario[1];

    $rangoBalance = obtenerRangoBalance($anio, $mes, $diaCierre);
    $inicioBalance = $rangoBalance[0];
    $finBalance = $rangoBalance[1];

    // Totales del mes calendario
    $sqlGastosMes = 'SELECT COALESCE(SUM(monto), 0) AS total
                     FROM gastos
                     WHERE usuario_id = :usuario_id AND fecha BETWEEN :inicio AND :fin';
    $stmtGastosMes = $pdo->prepare($sqlGastosMes);
    $stmtGastosMes->execute(array(':usuario_id' => $usuarioId, ':inicio' => $inicioMes, ':fin' => $finMes));
    $filaGastosMes = $stmtGastosMes->fetch();
    $totalGastosMes = (float) $filaGastosMes['total'];

    $sqlIngresosMes = 'SELECT COALESCE(SUM(monto), 0) AS total
                       FROM ingresos
                       WHERE usuario_id = :usuario_id AND fecha BETWEEN :inicio AND :fin';
    $stmtIngresosMes = $pdo->prepare($sqlIngresosMes);
    $stmtIngresosMes->execute(array(':usuario_id' => $usuarioId, ':inicio' => $inicioMes, ':fin' => $finMes));
    $filaIngresosMes = $stmtIngresosMes->fetch();
    $totalIngresosMes = (float) $filaIngresosMes['total'];

    $sqlCategorias = 'SELECT categoria, COALESCE(SUM(monto), 0) AS total
                      FROM gastos
                      WHERE usuario_id = :usuario_id AND fecha BETWEEN :inicio AND :fin
                      GROUP BY categoria
                      ORDER BY total DESC';
    $stmtCategorias = $pdo->prepare($sqlCategorias);
    $stmtCategorias->execute(array(':usuario_id' => $usuarioId, ':inicio' => $inicioMes, ':fin' => $finMes));
    $gastosPorCategoria = $stmtCategorias->fetchAll();

    // Totales para balance según día de cierre
    $sqlGastosBalance = 'SELECT COALESCE(SUM(monto), 0) AS total
                         FROM gastos
                         WHERE usuario_id = :usuario_id AND fecha BETWEEN :inicio AND :fin';
    $stmtGastosBalance = $pdo->prepare($sqlGastosBalance);
    $stmtGastosBalance->execute(array(':usuario_id' => $usuarioId, ':inicio' => $inicioBalance, ':fin' => $finBalance));
    $filaGastosBalance = $stmtGastosBalance->fetch();
    $totalGastosBalance = (float) $filaGastosBalance['total'];

    $sqlIngresosBalance = 'SELECT COALESCE(SUM(monto), 0) AS total
                           FROM ingresos
                           WHERE usuario_id = :usuario_id AND fecha BETWEEN :inicio AND :fin';
    $stmtIngresosBalance = $pdo->prepare($sqlIngresosBalance);
    $stmtIngresosBalance->execute(array(':usuario_id' => $usuarioId, ':inicio' => $inicioBalance, ':fin' => $finBalance));
    $filaIngresosBalance = $stmtIngresosBalance->fetch();
    $totalIngresosBalance = (float) $filaIngresosBalance['total'];

    $economiaValor = $totalIngresosMes - $totalGastosMes;
    $estadoEconomia = 'negativa';
    if ($economiaValor >= 0) {
        $estadoEconomia = 'positiva';
    }

    $balanceCierre = $totalIngresosBalance - $totalGastosBalance;

    responderExito('Métricas obtenidas correctamente.', array(
        'mes_consultado' => sprintf('%04d-%02d', $anio, $mes),
        'dia_cierre_balance' => $diaCierre,
        'periodo_mes_calendario' => array(
            'inicio' => $inicioMes,
            'fin' => $finMes,
        ),
        'periodo_balance_cierre' => array(
            'inicio' => $inicioBalance,
            'fin' => $finBalance,
        ),
        'total_gastos_mes' => round($totalGastosMes, 2),
        'gastos_por_categoria' => $gastosPorCategoria,
        'total_ingresos_mes' => round($totalIngresosMes, 2),
        'economia' => array(
            'valor' => round($economiaValor, 2),
            'estado' => $estadoEconomia,
        ),
        'balance_por_cierre' => round($balanceCierre, 2),
    ));
} catch (PDOException $e) {
    responderError('Ocurrió un error en la base de datos.', 500, array('detalle' => $e->getMessage()));
}
