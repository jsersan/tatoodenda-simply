<?php
// =============================================================================
//  CONFIG.PHP  ·  Configuración base (valores de DESARROLLO / MAMP)
//  - Este fichero SÍ se sube a Git: aquí solo van valores locales sin secretos.
//  - Los valores reales de producción (Arsys) van en config.local.php, que
//    NO se sube a Git (está en .gitignore) y sobreescribe lo de aquí.
// =============================================================================

$config = [
    'db' => [
        'host'    => 'qamn130.txemaserrano.com',           // MySQL de MAMP (127.0.0.1 evita el socket)
        'port'    => '8889',                // MAMP usa 8889; XAMPP/otros usan 3306
        'name'    => 'qamn130',          // creada por backend-php/sql/tatoodenda.sql
        'user'    => 'qamn130',                // usuario por defecto de MAMP
        'pass'    => 'Jss12apozMafmk2556#',                // contraseña por defecto de MAMP
        'charset' => 'utf8mb4',
    ],

    'google_client_id' => '258914466032-s0c4khhlnsj5uub7qijir9vig2t5rgbd.apps.googleusercontent.com',

    // Clave para firmar los JWT. En producción, cámbiala en config.local.php.
    'jwt_secret' => 'CAMBIA-ESTA-CLAVE-EN-config.local.php',
    'jwt_ttl'    => 60 * 60 * 24,          // 24 horas (en segundos)

    // URL pública del frontend, para construir el enlace de recuperación.
    'app_url' => 'http://localhost:4200',

    // Modo desarrollo. Si es true (o si 'smtp.host' está vacío), el endpoint
    // /users/forgot-password devuelve el enlace de reset en la propia respuesta
    // (campo devResetLink) para poder probar sin correo. En PRODUCCIÓN debe
    // quedarse en false Y tener el SMTP configurado, o el enlace se expondría.
    'dev_mode' => false,

    // Envío de email (SMTP autenticado). Rellena en config.local.php.
    // Deja 'host' vacío para DESACTIVAR el envío (los endpoints responderán
    // igual pero sin mandar correo, útil en local).
    'smtp' => [
        'host'      => '',                 // p. ej. 'smtp.tudominio.com'
        'port'      => 587,                // 587 = STARTTLS · 465 = SSL/TLS
        'secure'    => 'tls',              // 'tls' (587) · 'ssl' (465) · '' (sin cifrado)
        'user'      => '',                 // usuario SMTP
        'pass'      => '',                 // contraseña SMTP
        'from'      => 'no-reply@txemaserrano.com',
        'from_name' => 'TatooDenda',
        'timeout'   => 15,
    ],

    // Orígenes permitidos por CORS.
    'cors_origins' => ['http://localhost:4200', 'http://127.0.0.1:4200'],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override);
    }
}

return $config;