<?php
/*
 * Endpoint CRUD de gastos.
 * Métodos usados GET, POST, PUT y DELETE.
 *
 * Reglas de negocio del medio de pago:
 * - efectivo, billetera_virtual y debito: solo se informa el monto (pago en 1 vez).
 * - credito: se informa monto y cantidad de cuotas; el valor de cuota lo calcula el servidor.
 * El backend es la única fuente de verdad: valor_cuota y valor_total que mande el cliente se ignoran.
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
 * Devuelve la tarjeta_id validada o null si el método no usa tarjeta.
 */
function validarTarjetaSegunMetodo($pdo, $usuarioId, $metodoPago, $tarjetaId)
{
    if ($metodoPago !== 'debito' && $metodoPago !== 'credito') {
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

    // El método de pago y el tipo de tarjeta coinciden por nombre ('debito' / 'credito').
    if ($tarjeta['tipo'] !== $metodoPago) {
        responderError('El método ' . $metodoPago . ' requiere una tarjeta de tipo ' . $metodoPago . '.', 422);
    }

    return $tarjetaId;
}

/**
 * Calcula el plan de pago según el método.
 * Solo crédito admite cuotas; el resto siempre es 1 pago por el monto total.
 * Devuelve array(cantidad_cuotas, valor_cuota, valor_total).
 */
function calcularPlanDePago($metodoPago, $monto, $datos)
{
    if ($metodoPago !== 'credito') {
        return array(1, $monto, $monto);
    }

    if (!isset($datos['cantidad_cuotas']) || $datos['cantidad_cuotas'] === '') {
        responderError('Para crédito debés indicar la cantidad de cuotas.', 422);
    }

    $cuotas = validarCantidadCuotas($datos['cantidad_cuotas']);

    // Sin interés: valor_total = monto. El redondeo a 2 decimales puede dejar
    // una diferencia de centavos (ej: 1000 / 3 = 333,33 x 3 = 999,99), que en
    // la práctica absorbe la última cuota del resumen de la tarjeta.
    $valorCuota = round($monto / $cuotas, 2);

    return array($cuotas, $valorCuota, $monto);
}

/**
 * Valida y normaliza el body de un gasto (compartido por POST y PUT).
 * Devuelve un array listo para bindear en la consulta SQL.
 */
function construirGastoDesdeBody($pdo, $usuarioId, $datos)
{
    validarRequeridos($datos, array('descripcion', 'monto', 'fecha', 'categoria', 'metodo_pago'));

    $descripcion = trim((string) $datos['descripcion']);
    $categoria = trim((string) $datos['categoria']);
    $fecha = (string) $datos['fecha'];
    $metodoPago = trim((string) $datos['metodo_pago']);
    $monto = validarMonto($datos['monto']);

    if ($descripcion === '' || $categoria === '') {
        responderError('La descripción y la categoría no pueden estar vacías.', 422);
    }

    validarFecha($fecha);
    validarMetodoPago($metodoPago);

    $tarjetaId = null;
    if (isset($datos['tarjeta_id']) && $datos['tarjeta_id'] !== '') {
        $tarjetaId = (int) $datos['tarjeta_id'];
    }
    $tarjetaIdValidada = validarTarjetaSegunMetodo($pdo, $usuarioId, $metodoPago, $tarjetaId);

    list($cantidadCuotas, $valorCuota, $valorTotal) = calcularPlanDePago($metodoPago, $monto, $datos);

    return array(
        ':tarjeta_id' => $tarjetaIdValidada,
        ':descripcion' => $descripcion,
        ':monto' => $monto,
        ':fecha' => $fecha,
        ':categoria' => $categoria,
        ':metodo_pago' => $metodoPago,
        ':cantidad_cuotas' => $cantidadCuotas,
        ':valor_cuota' => $valorCuota,
        ':valor_total' => $valorTotal,
    );
}

/**
 * Lee el id de la query string. Devuelve 0 si no vino o es inválido.
 */
function obtenerIdQuery()
{
    if (!isset($_GET['id'])) {
        return 0;
    }

    return (int) $_GET['id'];
}

$sqlSelectBase = 'SELECT g.id, g.descripcion, g.monto, g.fecha, g.categoria, g.metodo_pago, g.tarjeta_id,
                         g.cantidad_cuotas, g.valor_cuota, g.valor_total, t.nombre AS tarjeta_nombre
                  FROM gastos g
                  LEFT JOIN tarjetas t ON t.id = g.tarjeta_id';

try {
    if ($metodo === 'GET') {
        $gastoId = obtenerIdQuery();

        if ($gastoId > 0) {
            $stmtUno = $pdo->prepare($sqlSelectBase . ' WHERE g.id = :id AND g.usuario_id = :usuario_id LIMIT 1');
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

        $stmtLista = $pdo->prepare($sqlSelectBase . ' WHERE g.usuario_id = :usuario_id ORDER BY g.fecha DESC, g.id DESC');
        $stmtLista->execute(array(':usuario_id' => $usuarioId));

        responderExito('Gastos listados correctamente.', array('gastos' => $stmtLista->fetchAll()));
    }

    if ($metodo === 'POST') {
        $parametros = construirGastoDesdeBody($pdo, $usuarioId, obtenerBodyJson());
        $parametros[':usuario_id'] = $usuarioId;

        $sqlInsertar = 'INSERT INTO gastos
                        (usuario_id, tarjeta_id, descripcion, monto, fecha, categoria, metodo_pago, cantidad_cuotas, valor_cuota, valor_total)
                        VALUES
                        (:usuario_id, :tarjeta_id, :descripcion, :monto, :fecha, :categoria, :metodo_pago, :cantidad_cuotas, :valor_cuota, :valor_total)';
        $stmtInsertar = $pdo->prepare($sqlInsertar);
        $stmtInsertar->execute($parametros);

        responderExito('Gasto creado correctamente.', array(
            'id' => (int) $pdo->lastInsertId(),
            'cantidad_cuotas' => $parametros[':cantidad_cuotas'],
            'valor_cuota' => $parametros[':valor_cuota'],
        ), 201);
    }

    if ($metodo === 'PUT') {
        $gastoId = obtenerIdQuery();
        if ($gastoId <= 0) {
            responderError('Debés indicar un id de gasto válido.', 422);
        }

        $parametros = construirGastoDesdeBody($pdo, $usuarioId, obtenerBodyJson());
        $parametros[':id'] = $gastoId;
        $parametros[':usuario_id'] = $usuarioId;

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
        $stmtUpdate->execute($parametros);

        // Con MYSQL_ATTR_FOUND_ROWS, 0 significa "no existe o no es del usuario".
        if ($stmtUpdate->rowCount() === 0) {
            responderError('Gasto no encontrado.', 404);
        }

        responderExito('Gasto actualizado correctamente.', array(
            'cantidad_cuotas' => $parametros[':cantidad_cuotas'],
            'valor_cuota' => $parametros[':valor_cuota'],
        ));
    }

    if ($metodo === 'DELETE') {
        $gastoId = obtenerIdQuery();
        if ($gastoId <= 0) {
            responderError('Debés indicar un id de gasto válido.', 422);
        }

        $stmtDelete = $pdo->prepare('DELETE FROM gastos WHERE id = :id AND usuario_id = :usuario_id');
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
