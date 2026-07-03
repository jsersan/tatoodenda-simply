<?php
// =============================================================================
//  INDEX.PHP  ·  Front controller / router REST  (TatooDenda simplificado)
//  Un único backend PHP que sustituye al Node/Express/Sequelize original.
//
//  Recursos (el frontend Angular les añade el prefijo de environment.apiUrl):
//    /users/register      POST            registro (+token)
//    /users/login         POST            login (+token)
//    /users/profile       GET   [auth]    perfil del usuario del token
//    /users/{id}          PUT   [auth]    actualizar perfil
//    /categorias          GET             listar   ·  POST [admin] crear
//    /categorias/{id}     GET             uno      ·  PUT/DELETE [admin]
//    /productos           GET             listar   ·  POST [admin] crear
//    /productos/search?q= GET             buscar
//    /productos/categoria/{id}  GET       por categoría
//    /productos/{id}/colors     GET       colores usados del producto
//    /productos/{id}      GET  · PUT/DELETE [admin]
//    /pedidos             POST  [auth]    crear pedido + líneas
//    /pedidos/user/{id}   GET   [auth]    pedidos del usuario (con líneas)
//    /pedidos/{id}        GET   [auth]    un pedido (con líneas)
//    /pedidos/{id}/lineas GET   [auth]    líneas de un pedido
// =============================================================================

declare(strict_types=1);

// ---------- CORS (antes de cargar nada más) ----------
$__allowed = array(
    'http://localhost:4200', 'http://127.0.0.1:4200',
    'http://txemaserrano.com', 'https://txemaserrano.com',
);
$__origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . (in_array($__origin, $__allowed, true) ? $__origin : '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Vary: Origin');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$config = require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/usuarios.php';
require __DIR__ . '/categorias.php';
require __DIR__ . '/productos.php';
require __DIR__ . '/pedidos.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/albaran.php';

DB::init($config);
$secret = $config['jwt_secret'];
$ttl    = $config['jwt_ttl'];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ---------- Resolver la ruta (tolerante a falta de mod_rewrite) ----------
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$script = $_SERVER['SCRIPT_NAME'] ?? '';
$base   = rtrim(dirname($script), '/');
$path   = $uri;
if ($base !== '' && strpos($path, $base) === 0) {
    $path = substr($path, strlen($base));
}
$path = ltrim($path, '/');
if (strpos($path, 'index.php') === 0) {
    $path = ltrim(substr($path, strlen('index.php')), '/');
}
if (isset($_GET['path'])) {
    $path = trim((string) $_GET['path'], '/');
}
$path     = trim($path, '/');
$segments = $path === '' ? array() : explode('/', $path);

// Acepta un prefijo opcional "api/".
if (($segments[0] ?? '') === 'api') {
    array_shift($segments);
}

$recurso = $segments[0] ?? '';

try {
    switch ($recurso) {

        // =====================================================================
        //  USERS
        // =====================================================================
        case 'users': {
            $sub = $segments[1] ?? '';

            if ($method === 'POST' && $sub === 'register') {
                $r = UsuarioRepo::register(body_json());
                if (isset($r['error'])) Response::error(400, $r['error']);
                $token = Jwt::encode(array('id' => $r['id'], 'role' => $r['role']), $secret, $ttl);
                Response::json(array_merge($r, array('token' => $token, 'message' => 'Usuario registrado exitosamente')), 201);
            }

            if ($method === 'POST' && $sub === 'login') {
                $b = body_json();
                $u = UsuarioRepo::login($b['username'] ?? '', $b['password'] ?? '');
                if (!$u) Response::error(401, 'Credenciales inválidas');
                $token = Jwt::encode(array('id' => $u['id'], 'role' => $u['role']), $secret, $ttl);
                Response::json(array_merge($u, array('token' => $token, 'message' => 'Login exitoso')));
            }

            if ($method === 'GET' && $sub === 'profile') {
                $auth = require_auth($secret);
                $u = UsuarioRepo::get((int) $auth['id']);
                $u ? Response::json($u) : Response::error(404, 'Usuario no encontrado');
            }

            // ----- Recuperación de contraseña -----
            if ($method === 'POST' && $sub === 'forgot-password') {
                $b = body_json();
                $email = trim((string) ($b['email'] ?? ''));
                if ($email === '') Response::error(400, 'Falta el email');

                $res = UsuarioRepo::createResetToken($email, 3600);
                if ($res) {
                    $link = rtrim($config['app_url'], '/') . '/reset-password?token=' . $res['token'];
                    $nombre = $res['user']['nombre'] ?: $res['user']['username'];
                    $html = '<p>Hola ' . htmlspecialchars($nombre) . ',</p>'
                          . '<p>Has solicitado restablecer tu contraseña en TatooDenda. '
                          . 'Pulsa el enlace (válido 1 hora):</p>'
                          . '<p><a href="' . htmlspecialchars($link) . '">Restablecer contraseña</a></p>'
                          . '<p>Si no has sido tú, ignora este mensaje.</p>';
                    Mailer::send($config, $email, $nombre, 'Recuperar contraseña · TatooDenda', $html);
                }
                // Respuesta genérica (no revela si el email existe).
                Response::json(array('message' => 'Si el email existe, recibirás un enlace de recuperación.'));
            }

            if ($method === 'GET' && $sub === 'verify-reset-token') {
                $token = $segments[2] ?? '';
                $valid = (bool) UsuarioRepo::findByResetToken($token);
                Response::json(array('valid' => $valid,
                    'message' => $valid ? 'Token válido' : 'Token inválido o caducado'));
            }

            if ($method === 'POST' && $sub === 'reset-password') {
                $b = body_json();
                $ok = UsuarioRepo::resetPassword($b['token'] ?? '', $b['newPassword'] ?? '');
                $ok ? Response::json(array('message' => 'Contraseña actualizada correctamente'))
                    : Response::error(400, 'Token inválido/caducado o contraseña demasiado corta');
            }

            if ($method === 'PUT' && $sub !== '') {   // PUT /users/{id}
                require_auth($secret);
                $u = UsuarioRepo::update((int) $sub, body_json());
                $u ? Response::json($u) : Response::error(404, 'Usuario no encontrado');
            }

            Response::error(404, 'Ruta de usuarios no encontrada');
        }

        // =====================================================================
        //  CATEGORIAS
        // =====================================================================
        case 'categorias': {
            $id = isset($segments[1]) ? (int) $segments[1] : null;

            if ($method === 'GET') {
                if ($id) {
                    $c = CategoriaRepo::get($id);
                    $c ? Response::json($c) : Response::error(404, 'Categoría no encontrada');
                }
                Response::json(CategoriaRepo::all());
            }
            if ($method === 'POST') {
                require_admin($secret);
                Response::json(CategoriaRepo::create(body_json()), 201);
            }
            if ($method === 'PUT') {
                require_admin($secret);
                if (!$id) Response::error(400, 'Falta el id');
                $c = CategoriaRepo::update($id, body_json());
                $c ? Response::json($c) : Response::error(404, 'Categoría no encontrada');
            }
            if ($method === 'DELETE') {
                require_admin($secret);
                if (!$id) Response::error(400, 'Falta el id');
                CategoriaRepo::delete($id);
                Response::json(array('message' => 'Categoría eliminada correctamente'));
            }
            Response::error(405, 'Método no permitido');
        }

        // =====================================================================
        //  PRODUCTOS
        // =====================================================================
        case 'productos': {
            $seg1 = $segments[1] ?? '';
            $seg2 = $segments[2] ?? '';

            // GET /productos/search?q=...
            if ($method === 'GET' && $seg1 === 'search') {
                $q = isset($_GET['q']) ? (string) $_GET['q'] : '';
                Response::json(ProductoRepo::search($q));
            }
            // GET /productos/categoria/{id}
            if ($method === 'GET' && $seg1 === 'categoria') {
                Response::json(ProductoRepo::byCategory((int) $seg2));
            }
            // GET /productos/{id}/colors
            if ($method === 'GET' && $seg1 !== '' && $seg2 === 'colors') {
                $stmt = DB::pdo()->prepare(
                    "SELECT DISTINCT color FROM lineapedido WHERE idprod = ?"
                );
                $stmt->execute(array((int) $seg1));
                Response::json($stmt->fetchAll());
            }

            $id = ($seg1 !== '' && ctype_digit($seg1)) ? (int) $seg1 : null;

            if ($method === 'GET') {
                if ($id) {
                    $p = ProductoRepo::get($id);
                    $p ? Response::json($p) : Response::error(404, 'Producto no encontrado');
                }
                Response::json(ProductoRepo::all());
            }
            if ($method === 'POST') {
                require_admin($secret);
                Response::json(ProductoRepo::create(body_json()), 201);
            }
            if ($method === 'PUT') {
                require_admin($secret);
                if (!$id) Response::error(400, 'Falta el id');
                $p = ProductoRepo::update($id, body_json());
                $p ? Response::json($p) : Response::error(404, 'Producto no encontrado');
            }
            if ($method === 'DELETE') {
                require_admin($secret);
                if (!$id) Response::error(400, 'Falta el id');
                ProductoRepo::delete($id);
                Response::json(array('message' => 'Producto eliminado correctamente'));
            }
            Response::error(405, 'Método no permitido');
        }

        // =====================================================================
        //  PEDIDOS
        // =====================================================================
        case 'pedidos': {
            $seg1 = $segments[1] ?? '';
            $seg2 = $segments[2] ?? '';

            // GET /pedidos/user/{id}
            if ($method === 'GET' && $seg1 === 'user') {
                require_auth($secret);
                Response::json(array('success' => true, 'data' => PedidoRepo::byUser((int) $seg2)));
            }
            // GET /pedidos/{id}/lineas
            if ($method === 'GET' && $seg1 !== '' && $seg2 === 'lineas') {
                require_auth($secret);
                Response::json(PedidoRepo::lines((int) $seg1));
            }
            // GET /pedidos/{id}
            if ($method === 'GET' && ctype_digit($seg1)) {
                require_auth($secret);
                $p = PedidoRepo::get((int) $seg1);
                $p ? Response::json($p) : Response::error(404, 'Pedido no encontrado');
            }
            // GET /pedidos/albaran/{id}  → descarga del PDF generado en el servidor
            if ($method === 'GET' && $seg1 === 'albaran') {
                require_auth($secret);
                $pedido = PedidoRepo::get((int) $seg2);
                if (!$pedido) Response::error(404, 'Pedido no encontrado');
                $usuario = UsuarioRepo::get($pedido['iduser']);
                $bytes = albaran_pdf_bytes($pedido, $usuario);
                if ($bytes === null) {
                    Response::error(501, 'Generación de PDF en servidor no disponible (falta lib/fpdf.php).');
                }
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="Albaran_' . $pedido['id'] . '.pdf"');
                echo $bytes;
                exit;
            }

            // POST /pedidos/enviar-albaran-email  → email con el PDF que manda el frontend
            if ($method === 'POST' && $seg1 === 'enviar-albaran-email') {
                require_auth($secret);
                $b = body_json();
                $usuario = $b['usuario'] ?? null;
                $pedido  = $b['pedido'] ?? array();
                $email   = $usuario['email'] ?? '';
                if ($email === '') Response::error(400, 'Falta el email del destinatario');

                $atts = array();
                if (!empty($b['pdfBase64'])) {
                    $raw = preg_replace('#^data:.*?;base64,#', '', (string) $b['pdfBase64']);
                    $bin = base64_decode($raw, true);
                    if ($bin !== false) {
                        $atts[] = array('name' => 'Albaran_' . ($pedido['id'] ?? '') . '.pdf',
                                        'mime' => 'application/pdf', 'data' => $bin);
                    }
                }
                $html = '<p>Adjuntamos el albarán de tu pedido en TatooDenda.</p>';
                $res  = Mailer::send($config, $email, $usuario['nombre'] ?? '', 'Tu albarán · TatooDenda', $html, $atts);
                Response::json(array('success' => $res['sent'],
                    'message' => $res['sent'] ? 'Albarán enviado por email' : ('No se pudo enviar: ' . ($res['error'] ?? ''))));
            }

            // POST /pedidos/reenviar-albaran/{id}  → regenera el PDF y reenvía
            if ($method === 'POST' && $seg1 === 'reenviar-albaran') {
                require_auth($secret);
                $pedido = PedidoRepo::get((int) $seg2);
                if (!$pedido) Response::error(404, 'Pedido no encontrado');
                $usuario = UsuarioRepo::get($pedido['iduser']);
                if (!$usuario || empty($usuario['email'])) Response::error(400, 'Usuario sin email');

                $atts = array();
                $bytes = albaran_pdf_bytes($pedido, $usuario);
                if ($bytes !== null) {
                    $atts[] = array('name' => 'Albaran_' . $pedido['id'] . '.pdf',
                                    'mime' => 'application/pdf', 'data' => $bytes);
                }
                $html = '<p>Reenviamos el albarán de tu pedido nº ' . $pedido['id'] . '.</p>';
                $res  = Mailer::send($config, $usuario['email'], $usuario['nombre'], 'Tu albarán · TatooDenda', $html, $atts);
                Response::json(array('success' => $res['sent'],
                    'message' => $res['sent'] ? 'Albarán reenviado' : ('No se pudo enviar: ' . ($res['error'] ?? ''))));
            }

            // POST /pedidos  → crear pedido (+ intento de email con albarán)
            if ($method === 'POST' && $seg1 === '') {
                require_auth($secret);
                $r = PedidoRepo::create(body_json());
                if (isset($r['error'])) Response::error(400, $r['error']);

                // Intento de email automático (no falla el pedido si el email falla).
                $emailEnviado = false;
                $usuario = UsuarioRepo::get($r['iduser']);
                if ($usuario && !empty($usuario['email'])) {
                    $atts  = array();
                    $bytes = albaran_pdf_bytes($r, $usuario);
                    if ($bytes !== null) {
                        $atts[] = array('name' => 'Albaran_' . $r['id'] . '.pdf',
                                        'mime' => 'application/pdf', 'data' => $bytes);
                    }
                    $html = '<p>Hola ' . htmlspecialchars($usuario['nombre']) . ', gracias por tu compra. '
                          . 'Tu pedido nº ' . $r['id'] . ' se ha registrado correctamente'
                          . ($bytes !== null ? ' (albarán adjunto).' : '.') . '</p>';
                    $sent = Mailer::send($config, $usuario['email'], $usuario['nombre'], 'Confirmación de pedido · TatooDenda', $html, $atts);
                    $emailEnviado = !empty($sent['sent']);
                }

                Response::json(array(
                    'success' => true,
                    'message' => 'Pedido creado exitosamente',
                    'data'    => array('pedido' => $r, 'emailEnviado' => $emailEnviado),
                ), 201);
            }

            Response::error(404, 'Ruta de pedidos no encontrada');
        }

        // =====================================================================
        //  UTILIDADES
        // =====================================================================
        case 'health':
            Response::json(array('status' => 'OK', 'timestamp' => date('c')));

        case 'info':
            Response::json(array(
                'name'    => 'TatooDenda API (PHP simplificada)',
                'version' => '1.0.0',
                'endpoints' => array('users', 'categorias', 'productos', 'pedidos'),
            ));

        default:
            Response::error(404, 'Ruta no encontrada');
    }
} catch (PDOException $e) {
    Response::error(500, 'Error de base de datos: ' . $e->getMessage());
} catch (Throwable $e) {
    Response::error(500, 'Error: ' . $e->getMessage());
}