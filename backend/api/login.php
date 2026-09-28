<?php
/*
 * Endpoint de inicio de sesión.
 * Método permitido POST.
 * Valida usuario y clave, devuelve token Bearer para usar en endpoints protegidos.
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

if ($metodo !== 'POST') {
    responderError('Método no permitido.', 405);
}

$pdo = obtenerConexion();
$datos = obtenerBodyJson();

validarRequeridos($datos, array('email', 'password'));

$email = strtolower(trim((string) $datos['email']));
$password = (string) $datos['password'];

validarEmail($email);

$sql = 'SELECT id, nombre, email, password_hash, dia_cierre_balance
        FROM usuarios
        WHERE email = :email
        LIMIT 1';
$stmt = $pdo->prepare($sql);
$stmt->execute(array(':email' => $email));
$usuario = $stmt->fetch();

if (!$usuario) {
    responderError('Datos de inicio de sesión inválidos.', 401);
}

if (!password_verify($password, $usuario['password_hash'])) {
    responderError('Datos de inicio de sesión inválidos.', 401);
}

$sesion = crearSesionToken($pdo, (int) $usuario['id']);

responderExito('Inicio de sesión exitoso.', array(
    'usuario' => array(
        'id' => (int) $usuario['id'],
        'nombre' => $usuario['nombre'],
        'email' => $usuario['email'],
        'dia_cierre_balance' => (int) $usuario['dia_cierre_balance'],
    ),
    'token' => $sesion['token'],
    'expira_en' => $sesion['expira_en'],
));
