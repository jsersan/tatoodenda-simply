<?php
// =============================================================================
//  HELPERS  ·  Respuestas JSON, lectura del body y JWT (HS256 sin librerías)
// =============================================================================

class Response
{
    public static function json($data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error($code, $message)
    {
        self::json(array('message' => $message), $code);
    }
}

/** Lee y decodifica el cuerpo JSON de la petición. */
function body_json()
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return array();
    $data = json_decode($raw, true);
    return is_array($data) ? $data : array();
}

// -----------------------------------------------------------------------------
//  JWT mínimo (HS256). Suficiente para el login del frontend, sin dependencias.
// -----------------------------------------------------------------------------
class Jwt
{
    private static function b64url($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64url_decode($data)
    {
        $pad = strlen($data) % 4;
        if ($pad) $data .= str_repeat('=', 4 - $pad);
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /** Genera un token con {id, role} y caducidad. */
    public static function encode($payload, $secret, $ttl)
    {
        $header  = array('alg' => 'HS256', 'typ' => 'JWT');
        $payload['iat'] = time();
        $payload['exp'] = time() + $ttl;

        $h = self::b64url(json_encode($header));
        $p = self::b64url(json_encode($payload));
        $sig = hash_hmac('sha256', "$h.$p", $secret, true);
        return "$h.$p." . self::b64url($sig);
    }

    /** Devuelve el payload si el token es válido y no ha caducado; si no, null. */
    public static function decode($token, $secret)
    {
        $parts = explode('.', (string) $token);
        if (count($parts) !== 3) return null;
        list($h, $p, $s) = $parts;

        $expected = self::b64url(hash_hmac('sha256', "$h.$p", $secret, true));
        if (!hash_equals($expected, $s)) return null;

        $payload = json_decode(self::b64url_decode($p), true);
        if (!is_array($payload)) return null;
        if (isset($payload['exp']) && time() >= $payload['exp']) return null;

        return $payload;
    }
}

/** Extrae el token de la cabecera Authorization: Bearer xxx. */
function bearer_token()
{
    $headers = array();
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }
    $auth = '';
    foreach ($headers as $k => $v) {
        if (strtolower($k) === 'authorization') { $auth = $v; break; }
    }
    if ($auth === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if ($auth === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if (stripos($auth, 'Bearer ') === 0) {
        return trim(substr($auth, 7));
    }
    return null;
}

/** Exige un token válido; corta con 401 si no lo hay. Devuelve el payload. */
function require_auth($secret)
{
    $payload = Jwt::decode(bearer_token(), $secret);
    if (!$payload) {
        Response::error(401, 'No autorizado. Inicia sesión de nuevo.');
    }
    return $payload;
}

/** Exige token válido y rol admin; corta con 403 si no lo es. */
function require_admin($secret)
{
    $payload = require_auth($secret);
    if (($payload['role'] ?? 'user') !== 'admin') {
        Response::error(403, 'No tienes permisos para esta operación.');
    }
    return $payload;
}
