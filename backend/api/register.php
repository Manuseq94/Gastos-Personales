<?php
/*
 * Endpoint de registro de usuario.
 * Método permitido POST.
 * Crea el usuario y genera la clave de sesión.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validaciones.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/email.php';

manejarPreflight();

$metodo = 'GET';
if (isset($_SERVER['REQUEST_METHOD'])) {
    $metodo = $_SERVER['REQUEST_METHOD'];
}

if ($metodo !== 'POST') {
    responderError('Método no permitido.', 405);
}

$pdo = obtenerConexion();
$datos = obtenerBodyJson();

validarRequeridos($datos, array('nombre', 'email', 'password'));

$nombre = trim((string) $datos['nombre']);
$email = strtolower(trim((string) $datos['email']));
$password = (string) $datos['password'];

// El día de cierre ya no se pide al registrarse: arranca en el valor por defecto
// y el usuario lo cambia desde la app (configuracion.php). Si llega en el body, se ignora.
$diaCierre = DIA_CIERRE_POR_DEFECTO;

if (mb_strlen($nombre) < 2) {
    responderError('El nombre debe tener al menos 2 caracteres.', 422);
}

if (strlen($password) < 6) {
    responderError('La contraseña debe tener al menos 6 caracteres.', 422);
}

validarEmail($email);

// Antes de crear el usuario, chequeamos que el email no esté registrado antes.
$sqlBuscar = 'SELECT id FROM usuarios WHERE email = :email LIMIT 1';
$stmtBuscar = $pdo->prepare($sqlBuscar);
$stmtBuscar->execute(array(':email' => $email));
if ($stmtBuscar->fetch()) {
    responderError('Ya existe un usuario registrado con ese email.', 409);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$sqlInsertar = 'INSERT INTO usuarios (nombre, email, password_hash, dia_cierre_balance)
                VALUES (:nombre, :email, :password_hash, :dia_cierre_balance)';
$stmtInsertar = $pdo->prepare($sqlInsertar);
$stmtInsertar->execute(array(
    ':nombre' => $nombre,
    ':email' => $email,
    ':password_hash' => $passwordHash,
    ':dia_cierre_balance' => $diaCierre,
));

$usuarioId = (int) $pdo->lastInsertId();
$sesion = crearSesionToken($pdo, $usuarioId);

enviarEmailBienvenida($email, $nombre);

responderExito('Usuario registrado correctamente.', array(
    'usuario' => array(
        'id' => $usuarioId,
        'nombre' => $nombre,
        'email' => $email,
        'dia_cierre_balance' => $diaCierre,
    ),
    'token' => $sesion['token'],
    'expira_en' => $sesion['expira_en'],
), 201);
