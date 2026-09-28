<?php
/*
 * Endpoint CRUD de gastos.
 * Métodos usados GET, POST, PUT y DELETE.
 * Acá también validamos la relación entre método de pago y tarjeta cuando corresponde.
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
 * Valida si la tarjeta es necesaria según el método de pago y si pertenece al usuario.
 * Recibe el PDO, id de usuario, método de pago y tarjeta_id (puede ser null)
 * Devuelve la tarjeta_id validada o null si no se requiere tarjeta.
 */
function validarTarjetaSegunMetodo($pdo, $usuarioId, $metodoPago, $tarjetaId)
{
    $usaTarjeta = false;
    if ($metodoPago === 'debito' || $metodoPago === 'credito') {
        $usaTarjeta = true;
    }

    if (!$usaTarjeta) {
        return null;
    }

    if ($tarjetaId === null || $tarjetaId <= 0) {
        responderError('Para débito o crédito debés asociar una tarjeta.', 422);
    }

    $sqlTarjeta = 'SELECT id, tipo FROM tarjetas WHERE id = :id AND usuario_id = :usuario_id LIMIT 1';
    $stmtTarjeta = $pdo->prepare($sqlTarjeta);
    $stmtTarjeta->execute(array(
        ':id' => $tarjetaId,
        ':usuario_id' => $usuarioId,
    ));
    $tarjeta = $stmtTarjeta->fetch();

    if (!$tarjeta) {
        responderError('La tarjeta asociada no existe o no pertenece al usuario.', 422);
    }

    if ($metodoPago === 'debito' && $tarjeta['tipo'] !== 'debito') {
        responderError('El método débito requiere una tarjeta de tipo débito.', 422);
    }

    if ($metodoPago === 'credito' && $tarjeta['tipo'] !== 'credito') {
        responderError('El método crédito requiere una tarjeta de tipo crédito.', 422);
    }

    return $tarjetaId;
}

try {
    if ($metodo === 'GET') {
        $gastoId = 0;
        if (isset($_GET['id'])) {
            $gastoId = (int) $_GET['id'];
        }

        if ($gastoId > 0) {
            $sqlUno = 'SELECT g.id, g.descripcion, g.monto, g.fecha, g.categoria, g.metodo_pago, g.tarjeta_id,
                              g.cantidad_cuotas, g.valor_cuota, g.valor_total, t.nombre AS tarjeta_nombre
                       FROM gastos g
                       LEFT JOIN tarjetas t ON t.id = g.tarjeta_id
                       WHERE g.id = :id AND g.usuario_id = :usuario_id
                       LIMIT 1';
            $stmtUno = $pdo->prepare($sqlUno);
            $stmtUno->execute(array(
                ':id' => $gastoId,
                ':usuario_id' => $usuarioId,
            ));
            $gasto = $stmtUno->fetch();

            if (!$gasto) {
                responderError('Gasto no encontrado.', 404);
            }

            responderExito('Gasto obtenido correctamente.', array('gasto' => $gasto));
        }

        $sqlLista = 'SELECT g.id, g.descripcion, g.monto, g.fecha, g.categoria, g.metodo_pago, g.tarjeta_id,
                            g.cantidad_cuotas, g.valor_cuota, g.valor_total, t.nombre AS tarjeta_nombre
                     FROM gastos g
                     LEFT JOIN tarjetas t ON t.id = g.tarjeta_id
                     WHERE g.usuario_id = :usuario_id
                     ORDER BY g.fecha DESC, g.id DESC';
        $stmtLista = $pdo->prepare($sqlLista);
        $stmtLista->execute(array(':usuario_id' => $usuarioId));

        responderExito('Gastos listados correctamente.', array('gastos' => $stmtLista->fetchAll()));
    }

    if ($metodo === 'POST') {
        $datos = obtenerBodyJson();
        validarRequeridos($datos, array('descripcion', 'monto', 'fecha', 'categoria', 'metodo_pago'));

        $descripcion = trim((string) $datos['descripcion']);
        $monto = validarMonto($datos['monto']);
        $fecha = (string) $datos['fecha'];
        $categoria = trim((string) $datos['categoria']);
        $metodoPago = trim((string) $datos['metodo_pago']);

        $tarjetaId = null;
        if (isset($datos['tarjeta_id']) && $datos['tarjeta_id'] !== '') {
            $tarjetaId = (int) $datos['tarjeta_id'];
        }

        validarFecha($fecha);
        validarMetodoPago($metodoPago);
        $tarjetaIdValidada = validarTarjetaSegunMetodo($pdo, $usuarioId, $metodoPago, $tarjetaId);

        $cantidadCuotas = 1;
        if (isset($datos['cantidad_cuotas'])) {
            $cantidadCuotas = (int) $datos['cantidad_cuotas'];
            if ($cantidadCuotas < 1) {
                $cantidadCuotas = 1;
            }
        }

        $valorTotal = $monto;
        if (isset($datos['valor_total']) && is_numeric($datos['valor_total'])) {
            $valorTotal = round((float) $datos['valor_total'], 2);
        }

        $valorCuota = round($valorTotal / $cantidadCuotas, 2);
        if (isset($datos['valor_cuota']) && is_numeric($datos['valor_cuota'])) {
            $valorCuota = round((float) $datos['valor_cuota'], 2);
        }

        $sqlInsertar = 'INSERT INTO gastos
                        (usuario_id, tarjeta_id, descripcion, monto, fecha, categoria, metodo_pago, cantidad_cuotas, valor_cuota, valor_total)
                        VALUES
                        (:usuario_id, :tarjeta_id, :descripcion, :monto, :fecha, :categoria, :metodo_pago, :cantidad_cuotas, :valor_cuota, :valor_total)';
        $stmtInsertar = $pdo->prepare($sqlInsertar);
        $stmtInsertar->execute(array(
            ':usuario_id' => $usuarioId,
            ':tarjeta_id' => $tarjetaIdValidada,
            ':descripcion' => $descripcion,
            ':monto' => $monto,
            ':fecha' => $fecha,
            ':categoria' => $categoria,
            ':metodo_pago' => $metodoPago,
            ':cantidad_cuotas' => $cantidadCuotas,
            ':valor_cuota' => $valorCuota,
            ':valor_total' => $valorTotal,
        ));

        responderExito('Gasto creado correctamente.', array('id' => (int) $pdo->lastInsertId()), 201);
    }

    if ($metodo === 'PUT') {
        $gastoId = 0;
        if (isset($_GET['id'])) {
            $gastoId = (int) $_GET['id'];
        }

        if ($gastoId <= 0) {
            responderError('Debés indicar un id de gasto válido.', 422);
        }

        $datos = obtenerBodyJson();
        validarRequeridos($datos, array('descripcion', 'monto', 'fecha', 'categoria', 'metodo_pago'));

        $descripcion = trim((string) $datos['descripcion']);
        $monto = validarMonto($datos['monto']);
        $fecha = (string) $datos['fecha'];
        $categoria = trim((string) $datos['categoria']);
        $metodoPago = trim((string) $datos['metodo_pago']);

        $tarjetaId = null;
        if (isset($datos['tarjeta_id']) && $datos['tarjeta_id'] !== '') {
            $tarjetaId = (int) $datos['tarjeta_id'];
        }

        validarFecha($fecha);
        validarMetodoPago($metodoPago);
        $tarjetaIdValidada = validarTarjetaSegunMetodo($pdo, $usuarioId, $metodoPago, $tarjetaId);

        $cantidadCuotas = 1;
        if (isset($datos['cantidad_cuotas'])) {
            $cantidadCuotas = (int) $datos['cantidad_cuotas'];
            if ($cantidadCuotas < 1) {
                $cantidadCuotas = 1;
            }
        }

        $valorTotal = $monto;
        if (isset($datos['valor_total']) && is_numeric($datos['valor_total'])) {
            $valorTotal = round((float) $datos['valor_total'], 2);
        }

        $valorCuota = round($valorTotal / $cantidadCuotas, 2);
        if (isset($datos['valor_cuota']) && is_numeric($datos['valor_cuota'])) {
            $valorCuota = round((float) $datos['valor_cuota'], 2);
        }

        $sqlUpdate = 'UPDATE gastos
                      SET tarjeta_id = :tarjeta_id,
                          descripcion = :descripcion,
                          monto = :monto,
                          fecha = :fecha,
                          categoria = :categoria,
                          metodo_pago = :metodo_pago,
                          cantidad_cuotas = :cantidad_cuotas,
                          valor_cuota = :valor_cuota,
                          valor_total = :valor_total
                      WHERE id = :id AND usuario_id = :usuario_id';
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute(array(
            ':tarjeta_id' => $tarjetaIdValidada,
            ':descripcion' => $descripcion,
            ':monto' => $monto,
            ':fecha' => $fecha,
            ':categoria' => $categoria,
            ':metodo_pago' => $metodoPago,
            ':cantidad_cuotas' => $cantidadCuotas,
            ':valor_cuota' => $valorCuota,
            ':valor_total' => $valorTotal,
            ':id' => $gastoId,
            ':usuario_id' => $usuarioId,
        ));

        if ($stmtUpdate->rowCount() === 0) {
            responderError('Gasto no encontrado o sin cambios para actualizar.', 404);
        }

        responderExito('Gasto actualizado correctamente.');
    }

    if ($metodo === 'DELETE') {
        $gastoId = 0;
        if (isset($_GET['id'])) {
            $gastoId = (int) $_GET['id'];
        }

        if ($gastoId <= 0) {
            responderError('Debés indicar un id de gasto válido.', 422);
        }

        $sqlDelete = 'DELETE FROM gastos WHERE id = :id AND usuario_id = :usuario_id';
        $stmtDelete = $pdo->prepare($sqlDelete);
        $stmtDelete->execute(array(
            ':id' => $gastoId,
            ':usuario_id' => $usuarioId,
        ));

        if ($stmtDelete->rowCount() === 0) {
            responderError('Gasto no encontrado.', 404);
        }

        responderExito('Gasto eliminado correctamente.');
    }

    responderError('Método no permitido.', 405);
} catch (PDOException $e) {
    responderError('Ocurrió un error en la base de datos.', 500, array('detalle' => $e->getMessage()));
}
