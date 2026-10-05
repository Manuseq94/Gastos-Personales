<?php
/*
 * Endpoint de configuración del usuario autenticado.
 * GET: devuelve la configuración actual.
 * PUT: actualiza el día de cierre de balance (1 a 28).
 *
 * Se separa de metricas.php para que cada endpoint tenga una sola responsabilidad:
 * métricas solo lee/calcula, configuración es la única que escribe preferencias.
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

try {
    if ($metodo === 'GET') {
        // requerirUsuarioAutenticado ya trae dia_cierre_balance: no hace falta otra consulta.
        responderExito('Configuración obtenida correctamente.', array(
            'dia_cierre_balance' => $usuario['dia_cierre_balance'],
        ));
    }

    if ($metodo === 'PUT') {
        $datos = obtenerBodyJson();
        validarRequeridos($datos, array('dia_cierre_balance'));
        $diaCierre = validarDiaCierre($datos['dia_cierre_balance']);

        $stmtUpdate = $pdo->prepare('UPDATE usuarios SET dia_cierre_balance = :dia_cierre_balance WHERE id = :id');
        $stmtUpdate->execute(array(
            ':dia_cierre_balance' => $diaCierre,
            ':id' => $usuario['id'],
        ));

        responderExito('Día de cierre actualizado correctamente.', array('dia_cierre_balance' => $diaCierre));
    }

    responderError('Método no permitido.', 405);
} catch (PDOException $e) {
    responderError('Ocurrió un error en la base de datos.', 500, array('detalle' => $e->getMessage()));
}
