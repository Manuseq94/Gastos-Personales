<?php
/*
 * Se ejecuta al arrancar el contenedor (antes de Apache).
 * Espera a que MySQL esté disponible y crea las tablas si no existen.
 * Si no logra conectarse, avisa por consola y deja que Apache arranque igual.
 */

require_once __DIR__ . '/../backend/config/database.php';

$config = obtenerConfigDb();
$user = $config['user'];
$pass = $config['pass'];

$dsn = 'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['name'] . ';charset=utf8mb4';
$intentos = 15;
$pdo = null;

for ($i = 1; $i <= $intentos; $i++) {
    try {
        $pdo = new PDO($dsn, $user, $pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        break;
    } catch (PDOException $e) {
        fwrite(STDERR, '[init-db] Intento ' . $i . '/' . $intentos . ': MySQL no disponible (' . $e->getMessage() . ")\n");
        sleep(2);
    }
}

if ($pdo === null) {
    fwrite(STDERR, "[init-db] No se pudo conectar a MySQL. Se omite la creación de tablas.\n");
    exit(0);
}

$sql = file_get_contents(__DIR__ . '/schema.sql');

// Quitamos comentarios de línea y ejecutamos sentencia por sentencia.
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
foreach (explode(';', $sql) as $sentencia) {
    $sentencia = trim($sentencia);
    if ($sentencia !== '') {
        $pdo->exec($sentencia);
    }
}

fwrite(STDOUT, "[init-db] Esquema verificado correctamente.\n");
