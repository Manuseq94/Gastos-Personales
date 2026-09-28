<?php
/*
 * Acá creamos funciones para manejar la autenticación 
 * de usuarios mediante tokens Bearer.
 */

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Crea un token aleatorio seguro.
 */
function generarTokenSeguro()
{
    return bin2hex(random_bytes(32));
}

/**
 * Busca el token Bearer dentro del header Authorization.
 */
function extraerBearerToken()
{
    $header = '';

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['Authorization'])) {
        $header = $_SERVER['Authorization'];
    }

    // En algunos servidores, Authorization viene por getallheaders().
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $header = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $header = $headers['authorization'];
        }
    }

    if (!preg_match('/Bearer\s+(.*)$/i', $header, $coincidencias)) {
        responderError('Token de autorización no enviado o con formato inválido.', 401);
    }

    return trim($coincidencias[1]);
}

/**
 * Crea una sesión nueva guardando token y fecha de expiración.
 */
function crearSesionToken($pdo, $usuarioId)
{
    $token = generarTokenSeguro();

    $horasTexto = env('TOKEN_EXPIRATION_HOURS', '24');
    $horas = (int) $horasTexto;
    if ($horas <= 0) {
        $horas = 24;
    }

    $fechaExpiracion = new DateTime('now');
    $fechaExpiracion->modify('+' . $horas . ' hours');
    $expiraEn = $fechaExpiracion->format('Y-m-d H:i:s');

    $sql = 'INSERT INTO tokens (usuario_id, token, expira_en) VALUES (:usuario_id, :token, :expira_en)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(
        ':usuario_id' => $usuarioId,
        ':token' => $token,
        ':expira_en' => $expiraEn,
    ));

    return array(
        'token' => $token,
        'expira_en' => $expiraEn,
    );
}

/**
 * Borra tokens vencidos de la base.
 */
function limpiarTokensExpirados($pdo)
{
    $stmt = $pdo->prepare('DELETE FROM tokens WHERE expira_en < NOW()');
    $stmt->execute();
}

/**
 * Valida token Bearer y devuelve el usuario autenticado.
 */
function requerirUsuarioAutenticado($pdo)
{
    limpiarTokensExpirados($pdo);
    $token = extraerBearerToken();

    $sql = 'SELECT u.id, u.nombre, u.email, u.dia_cierre_balance
            FROM tokens t
            INNER JOIN usuarios u ON u.id = t.usuario_id
            WHERE t.token = :token AND t.expira_en >= NOW()
            LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':token' => $token));
    $usuario = $stmt->fetch();

    if (!$usuario) {
        responderError('Token inválido o vencido. Iniciá sesión nuevamente.', 401);
    }

    $usuario['id'] = (int) $usuario['id'];
    $usuario['dia_cierre_balance'] = (int) $usuario['dia_cierre_balance'];

    return $usuario;
}
