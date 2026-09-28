<?php
/*
 * Este archivo maneja el envío opcional de email de bienvenida con Brevo.
 * Si no configurás BREVO_API_KEY en .env, no pasa nada: se omite en silencio.
 * Esto está pensado así para que la app siga funcionando igual en local.
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Envía un email de bienvenida a un usuario recién registrado.
 */
function enviarEmailBienvenida($destinatario, $nombre)
{
    $apiKey = env('BREVO_API_KEY', '');

    // Si no hay API key, salimos sin romper nada.
    if ($apiKey === null || trim($apiKey) === '') {
        return;
    }

    $fromEmail = env('BREVO_FROM_EMAIL', 'no-reply@ejemplo.com');
    if ($fromEmail === null || $fromEmail === '') {
        $fromEmail = 'no-reply@ejemplo.com';
    }

    $fromName = env('BREVO_FROM_NAME', 'Gastos Personales');
    if ($fromName === null || $fromName === '') {
        $fromName = 'Gastos Personales';
    }

    $payload = array(
        'sender' => array(
            'name' => $fromName,
            'email' => $fromEmail,
        ),
        'to' => array(
            array(
                'email' => $destinatario,
                'name' => $nombre,
            )
        ),
        'subject' => 'Bienvenido/a a Gastos Personales',
        'htmlContent' => '<h2>¡Bienvenido/a, ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '!</h2><p>Tu cuenta fue creada correctamente.</p>',
    );

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    if ($ch === false) {
        return;
    }

    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $apiKey,
        ),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 10,
    ));

    // No frenamos el registro si Brevo falla: esto es complementario.
    curl_exec($ch);
    curl_close($ch);
}
