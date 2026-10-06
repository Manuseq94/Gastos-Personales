<?php
/*
 * Acá se hacen dos procedimientos importantes del backend
 * 1) leer variables del archivo .env
 * 2) abrir la conexión PDO a MySQL
 */

/**
 * Se carga el entorno desde un archivo .env.
 */
function cargarEntorno($rutaEnv)
{
    if (!file_exists($rutaEnv)) {
        return;
    }

    $lineas = file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lineas === false) {
        return;
    }

    foreach ($lineas as $linea) {
        $linea = trim($linea);

        // Evitamos comentarios y líneas vacías.
        if ($linea === '' || substr($linea, 0, 1) === '#') {
            continue;
        }

        $partes = explode('=', $linea, 2);
        $clave = trim($partes[0]);
        $valor = '';

        if (isset($partes[1])) {
            $valor = trim($partes[1]);
        }

        // Eliminamos las comillas si están al principio y al final del valor.
        if ($valor !== '') {
            $primerChar = substr($valor, 0, 1);
            $ultimoChar = substr($valor, -1);
            if (($primerChar === '"' && $ultimoChar === '"') || ($primerChar === "'" && $ultimoChar === "'")) {
                $valor = substr($valor, 1, -1);
            }
        }

        if ($clave !== '') {
            $_ENV[$clave] = $valor;
            putenv($clave . '=' . $valor);
        }
    }
}

// Cargamos el entorno desde el archivo .env en la raíz del proyecto.
cargarEntorno(__DIR__ . '/../.env');

/**
 * Busca una variable de entorno y si no existe devuelve un valor por defecto.
 * Recibe clave (string) y valor por defecto (string o null) y 
 * devuelve el valor de la variable de entorno o el valor por defecto.
 */
function env($clave, $default = null)
{
    if (isset($_ENV[$clave]) && $_ENV[$clave] !== '') {
        return $_ENV[$clave];
    }

    $valor = getenv($clave);
    if ($valor !== false && $valor !== '') {
        return $valor;
    }

    return $default;
}

/**
 * Devuelve los datos de conexión a MySQL.
 * Primero usa DB_*; si no están, usa las variables MYSQL* que crea Railway.
 */
function obtenerConfigDb()
{
    return array(
        'host' => env('DB_HOST', env('MYSQLHOST', '127.0.0.1')),
        'port' => env('DB_PORT', env('MYSQLPORT', '3306')),
        'name' => env('DB_NAME', env('MYSQLDATABASE', 'gastos_personales')),
        'user' => env('DB_USER', env('MYSQLUSER', 'root')),
        'pass' => env('DB_PASS', env('MYSQLPASSWORD', '')),
    );
}

/**
 * Abre la conexión PDO a MySQL usando los datos de entorno.
 */
function obtenerConexion()
{
    $config = obtenerConfigDb();
    $host = $config['host'];
    $port = $config['port'];
    $dbName = $config['name'];
    $user = $config['user'];
    $pass = $config['pass'];

    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbName . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, $user, $pass, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            // rowCount() de un UPDATE devuelve filas ENCONTRADAS y no solo las modificadas.
            // Sin esto, guardar un registro sin cambios respondía 404 por error.
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
        ));

        // Alineamos la zona horaria de MySQL con la de PHP para que NOW()
        // coincida con las fechas que arma PHP (por ejemplo, vencimiento de tokens).
        $offset = (new DateTime('now'))->format('P');
        $pdo->exec("SET time_zone = '" . $offset . "'");

        return $pdo;
    } catch (PDOException $e) {
        /*
         * Devolvemos un error 500 con un mensaje genérico y detalle del error en JSON.
         */
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(array(
            'ok' => false,
            'mensaje' => 'No se pudo conectar a la base de datos. Revisá que MySQL esté encendido y los datos de conexión.',
            'errores' => array(
                'detalle' => $e->getMessage(),
            ),
        ), JSON_UNESCAPED_UNICODE);

        exit;
    }
}
