<?php
/*
 * Endpoint CRUD de ingresos.
 * Métodos: GET, POST, PUT y DELETE.
 * El campo es_fijo se guarda como 0 o 1 en la base, en la app lo mostramos como booleano.
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
        $ingresoId = 0;
        if (isset($_GET['id'])) {
            $ingresoId = (int) $_GET['id'];
        }

        if ($ingresoId > 0) {
            $sqlUno = 'SELECT id, descripcion, monto, fecha, es_fijo, creado_en, actualizado_en
                       FROM ingresos
                       WHERE id = :id AND usuario_id = :usuario_id
                       LIMIT 1';
            $stmtUno = $pdo->prepare($sqlUno);
            $stmtUno->execute(array(
                ':id' => $ingresoId,
                ':usuario_id' => $usuarioId,
            ));
            $ingreso = $stmtUno->fetch();

            if (!$ingreso) {
                responderError('Ingreso no encontrado.', 404);
            }

            $ingreso['es_fijo'] = (bool) $ingreso['es_fijo'];
            responderExito('Ingreso obtenido correctamente.', array('ingreso' => $ingreso));
        }

        $sqlLista = 'SELECT id, descripcion, monto, fecha, es_fijo, creado_en, actualizado_en
                     FROM ingresos
                     WHERE usuario_id = :usuario_id
                     ORDER BY fecha DESC, id DESC';
        $stmtLista = $pdo->prepare($sqlLista);
        $stmtLista->execute(array(':usuario_id' => $usuarioId));
        $ingresos = $stmtLista->fetchAll();

        foreach ($ingresos as $i => $fila) {
            $ingresos[$i]['es_fijo'] = (bool) $fila['es_fijo'];
        }

        responderExito('Ingresos listados correctamente.', array('ingresos' => $ingresos));
    }

    if ($metodo === 'POST') {
        $datos = obtenerBodyJson();
        validarRequeridos($datos, array('descripcion', 'monto', 'fecha', 'es_fijo'));

        $descripcion = trim((string) $datos['descripcion']);
        $monto = validarMonto($datos['monto']);
        $fecha = (string) $datos['fecha'];
        $esFijo = filter_var($datos['es_fijo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        validarFecha($fecha);
        if ($esFijo === null) {
            responderError('El campo es_fijo debe ser verdadero o falso.', 422);
        }

        $sqlInsertar = 'INSERT INTO ingresos (usuario_id, descripcion, monto, fecha, es_fijo)
                        VALUES (:usuario_id, :descripcion, :monto, :fecha, :es_fijo)';
        $stmtInsertar = $pdo->prepare($sqlInsertar);
        $stmtInsertar->execute(array(
            ':usuario_id' => $usuarioId,
            ':descripcion' => $descripcion,
            ':monto' => $monto,
            ':fecha' => $fecha,
            ':es_fijo' => $esFijo ? 1 : 0,
        ));

        responderExito('Ingreso creado correctamente.', array('id' => (int) $pdo->lastInsertId()), 201);
    }

    if ($metodo === 'PUT') {
        $ingresoId = 0;
        if (isset($_GET['id'])) {
            $ingresoId = (int) $_GET['id'];
        }

        if ($ingresoId <= 0) {
            responderError('Debés indicar un id de ingreso válido.', 422);
        }

        $datos = obtenerBodyJson();
        validarRequeridos($datos, array('descripcion', 'monto', 'fecha', 'es_fijo'));

        $descripcion = trim((string) $datos['descripcion']);
        $monto = validarMonto($datos['monto']);
        $fecha = (string) $datos['fecha'];
        $esFijo = filter_var($datos['es_fijo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        validarFecha($fecha);
        if ($esFijo === null) {
            responderError('El campo es_fijo debe ser verdadero o falso.', 422);
        }

        $sqlUpdate = 'UPDATE ingresos
                      SET descripcion = :descripcion,
                          monto = :monto,
                          fecha = :fecha,
                          es_fijo = :es_fijo
                      WHERE id = :id AND usuario_id = :usuario_id';
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute(array(
            ':descripcion' => $descripcion,
            ':monto' => $monto,
            ':fecha' => $fecha,
            ':es_fijo' => $esFijo ? 1 : 0,
            ':id' => $ingresoId,
            ':usuario_id' => $usuarioId,
        ));

        if ($stmtUpdate->rowCount() === 0) {
            responderError('Ingreso no encontrado o sin cambios para actualizar.', 404);
        }

        responderExito('Ingreso actualizado correctamente.');
    }

    if ($metodo === 'DELETE') {
        $ingresoId = 0;
        if (isset($_GET['id'])) {
            $ingresoId = (int) $_GET['id'];
        }

        if ($ingresoId <= 0) {
            responderError('Debés indicar un id de ingreso válido.', 422);
        }

        $sqlDelete = 'DELETE FROM ingresos WHERE id = :id AND usuario_id = :usuario_id';
        $stmtDelete = $pdo->prepare($sqlDelete);
        $stmtDelete->execute(array(
            ':id' => $ingresoId,
            ':usuario_id' => $usuarioId,
        ));

        if ($stmtDelete->rowCount() === 0) {
            responderError('Ingreso no encontrado.', 404);
        }

        responderExito('Ingreso eliminado correctamente.');
    }

    responderError('Método no permitido.', 405);
} catch (PDOException $e) {
    responderError('Ocurrió un error en la base de datos.', 500, array('detalle' => $e->getMessage()));
}
