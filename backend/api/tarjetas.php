<?php
/*
 * Endpoint CRUD de tarjetas.
 * Métodos usados GET, POST, PUT, DELETE.
 * De acuerdo al método, se listan, crean, actualizan o eliminan tarjetas del usuario autenticado.
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

try {
    if ($metodo === 'GET') {
        $tarjetaId = 0;
        if (isset($_GET['id'])) {
            $tarjetaId = (int) $_GET['id'];
        }

        $conGastos = false;
        if (isset($_GET['con_gastos']) && $_GET['con_gastos'] === '1') {
            $conGastos = true;
        }

        // GET por id
        if ($tarjetaId > 0) {
            $sqlTarjeta = 'SELECT id, nombre, tipo, banco, ultimos_4, creado_en, actualizado_en
                           FROM tarjetas
                           WHERE id = :id AND usuario_id = :usuario_id
                           LIMIT 1';
            $stmtTarjeta = $pdo->prepare($sqlTarjeta);
            $stmtTarjeta->execute(array(
                ':id' => $tarjetaId,
                ':usuario_id' => $usuarioId,
            ));
            $tarjeta = $stmtTarjeta->fetch();

            if (!$tarjeta) {
                responderError('Tarjeta no encontrada.', 404);
            }

            if ($conGastos) {
                $sqlGastos = 'SELECT id, descripcion, monto, fecha, categoria, metodo_pago, cantidad_cuotas, valor_cuota, valor_total
                              FROM gastos
                              WHERE usuario_id = :usuario_id AND tarjeta_id = :tarjeta_id
                              ORDER BY fecha DESC, id DESC';
                $stmtGastos = $pdo->prepare($sqlGastos);
                $stmtGastos->execute(array(
                    ':usuario_id' => $usuarioId,
                    ':tarjeta_id' => $tarjetaId,
                ));
                $tarjeta['gastos_asociados'] = $stmtGastos->fetchAll();
            }

            responderExito('Tarjeta obtenida correctamente.', array('tarjeta' => $tarjeta));
        }

        // GET listado completo
        $sqlListado = 'SELECT id, nombre, tipo, banco, ultimos_4, creado_en, actualizado_en
                       FROM tarjetas
                       WHERE usuario_id = :usuario_id
                       ORDER BY id DESC';
        $stmtListado = $pdo->prepare($sqlListado);
        $stmtListado->execute(array(':usuario_id' => $usuarioId));
        $tarjetas = $stmtListado->fetchAll();

        if ($conGastos) {
            foreach ($tarjetas as $indice => $tarjetaItem) {
                $sqlGastos = 'SELECT id, descripcion, monto, fecha, categoria, metodo_pago, cantidad_cuotas, valor_cuota, valor_total
                              FROM gastos
                              WHERE usuario_id = :usuario_id AND tarjeta_id = :tarjeta_id
                              ORDER BY fecha DESC, id DESC';
                $stmtGastos = $pdo->prepare($sqlGastos);
                $stmtGastos->execute(array(
                    ':usuario_id' => $usuarioId,
                    ':tarjeta_id' => (int) $tarjetaItem['id'],
                ));
                $tarjetas[$indice]['gastos_asociados'] = $stmtGastos->fetchAll();
            }
        }

        responderExito('Tarjetas listadas correctamente.', array('tarjetas' => $tarjetas));
    }

    if ($metodo === 'POST') {
        $datos = obtenerBodyJson();
        validarRequeridos($datos, array('nombre', 'tipo', 'banco', 'ultimos_4'));

        $nombre = trim((string) $datos['nombre']);
        $tipo = trim((string) $datos['tipo']);
        $banco = trim((string) $datos['banco']);
        $ultimos4 = validarUltimosCuatro($datos['ultimos_4']);

        validarTipoTarjeta($tipo);

        $sqlInsertar = 'INSERT INTO tarjetas (usuario_id, nombre, tipo, banco, ultimos_4)
                        VALUES (:usuario_id, :nombre, :tipo, :banco, :ultimos_4)';
        $stmtInsertar = $pdo->prepare($sqlInsertar);
        $stmtInsertar->execute(array(
            ':usuario_id' => $usuarioId,
            ':nombre' => $nombre,
            ':tipo' => $tipo,
            ':banco' => $banco,
            ':ultimos_4' => $ultimos4,
        ));

        responderExito('Tarjeta creada correctamente.', array('id' => (int) $pdo->lastInsertId()), 201);
    }

    if ($metodo === 'PUT') {
        $tarjetaId = 0;
        if (isset($_GET['id'])) {
            $tarjetaId = (int) $_GET['id'];
        }

        if ($tarjetaId <= 0) {
            responderError('Debés indicar un id de tarjeta válido.', 422);
        }

        $datos = obtenerBodyJson();
        validarRequeridos($datos, array('nombre', 'tipo', 'banco', 'ultimos_4'));

        $nombre = trim((string) $datos['nombre']);
        $tipo = trim((string) $datos['tipo']);
        $banco = trim((string) $datos['banco']);
        $ultimos4 = validarUltimosCuatro($datos['ultimos_4']);

        validarTipoTarjeta($tipo);

        $sqlUpdate = 'UPDATE tarjetas
                      SET nombre = :nombre, tipo = :tipo, banco = :banco, ultimos_4 = :ultimos_4
                      WHERE id = :id AND usuario_id = :usuario_id';
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute(array(
            ':nombre' => $nombre,
            ':tipo' => $tipo,
            ':banco' => $banco,
            ':ultimos_4' => $ultimos4,
            ':id' => $tarjetaId,
            ':usuario_id' => $usuarioId,
        ));

        if ($stmtUpdate->rowCount() === 0) {
            responderError('Tarjeta no encontrada o sin cambios para actualizar.', 404);
        }

        responderExito('Tarjeta actualizada correctamente.');
    }

    if ($metodo === 'DELETE') {
        $tarjetaId = 0;
        if (isset($_GET['id'])) {
            $tarjetaId = (int) $_GET['id'];
        }

        if ($tarjetaId <= 0) {
            responderError('Debés indicar un id de tarjeta válido.', 422);
        }

        $sqlDelete = 'DELETE FROM tarjetas WHERE id = :id AND usuario_id = :usuario_id';
        $stmtDelete = $pdo->prepare($sqlDelete);
        $stmtDelete->execute(array(
            ':id' => $tarjetaId,
            ':usuario_id' => $usuarioId,
        ));

        if ($stmtDelete->rowCount() === 0) {
            responderError('Tarjeta no encontrada.', 404);
        }

        responderExito('Tarjeta eliminada correctamente.');
    }

    responderError('Método no permitido.', 405);
} catch (PDOException $e) {
    responderError('Ocurrió un error en la base de datos.', 500, array('detalle' => $e->getMessage()));
}
