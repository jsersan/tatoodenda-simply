<?php
// =============================================================================
//  CONFIG.PHP  ·  Configuración base (valores de DESARROLLO / MAMP)
//  - Los valores reales de producción (Arsys) se ponen en config.local.php,
//    que NO se sube a Git (está en .gitignore) y sobreescribe lo de aquí.
// =============================================================================

$config = [
    'db' => [
        'host'    => 'localhost',
        'port'    => '8889',              // MAMP usa 8889; XAMPP/otros usan 3306
        'name'    => 'tatoodenda',
        'user'    => 'root',
        'pass'    => 'root',
        'charset' => 'utf8mb4',
    ],

    // Clave para firmar los JWT. En producción, cámbiala en config.local.php.
    'jwt_secret' => 'CAMBIA-ESTA-CLAVE-EN-config.local.php',
    'jwt_ttl'    => 60 * 60 * 24,          // 24 horas (en segundos)

    // URL pública del frontend, para construir el enlace de recuperación.
    'app_url' => 'http://localhost:4200',

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
