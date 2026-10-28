<?php
// =============================================================================
//  GOOGLE  ·  Verificación del id_token de "Acceder con Google"
//
//  Usa el endpoint público de Google `tokeninfo` (una simple llamada HTTPS),
//  así NO hacen falta librerías ni verificar la firma RS256 a mano.
//  Contrapartida: una petición HTTP a Google por cada login con Google.
//
//  Devuelve ['email'=>..., 'name'=>...] si el token es válido para NUESTRO
//  client_id; null en caso contrario.
// =============================================================================

function google_fetch($url)
{
    // Preferimos cURL (más fiable en hostings); si no, file_get_contents.
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $out = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($out !== false && $code >= 200 && $code < 300) ? $out : null;
    }
    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(array('http' => array('timeout' => 10)));
        $out = @file_get_contents($url, false, $ctx);
        return $out === false ? null : $out;
    }
    return null;
}

/**
 * Verifica el id_token de Google contra tokeninfo.
 * $clientId: si no está vacío, se exige que el token sea para ese client_id.
 */
function verify_google_token($idToken, $clientId)
{
    $idToken = (string) $idToken;
    if ($idToken === '') return null;

    $url  = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
    $body = google_fetch($url);
    if ($body === null) return null;

    $info = json_decode($body, true);
    if (!is_array($info) || empty($info['email'])) return null;

    // El token debe ser para NUESTRA app.
    if ($clientId !== '' && ($info['aud'] ?? '') !== $clientId) return null;

    // Emisor correcto.
    $iss = $info['iss'] ?? '';
    if ($iss !== 'accounts.google.com' && $iss !== 'https://accounts.google.com') return null;

    // No caducado.
    if (isset($info['exp']) && time() >= (int) $info['exp']) return null;

    // Email verificado (tokeninfo devuelve strings 'true'/'false').
    $verified = $info['email_verified'] ?? 'false';
    if ($verified !== true && $verified !== 'true') return null;

    return array(
        'email' => (string) $info['email'],
        'name'  => (string) ($info['name'] ?? $info['email']),
    );
}
