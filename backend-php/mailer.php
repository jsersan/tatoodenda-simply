<?php
// =============================================================================
//  MAILER  ·  Cliente SMTP mínimo, SIN dependencias (sockets nativos).
//  Soporta STARTTLS (587) y SSL directo (465), AUTH LOGIN y adjuntos.
//  Si smtp.host está vacío, no envía nada y devuelve sent=false (modo local).
//
//  Uso:
//    Mailer::send($config, 'cliente@x.com', 'Nombre', 'Asunto', '<b>hola</b>',
//                 [['name'=>'Albaran_5.pdf','data'=>$bytesPdf,'mime'=>'application/pdf']]);
// =============================================================================

class Mailer
{
    public static function send($config, $to, $toName, $subject, $htmlBody, $attachments = array())
    {
        $s = isset($config['smtp']) ? $config['smtp'] : array();
        $host = isset($s['host']) ? $s['host'] : '';

        if ($host === '') {
            return array('sent' => false, 'error' => 'SMTP no configurado (smtp.host vacío)');
        }

        $port    = isset($s['port'])    ? (int) $s['port'] : 587;
        $secure  = isset($s['secure'])  ? $s['secure']     : 'tls';
        $user    = isset($s['user'])    ? $s['user']       : '';
        $pass    = isset($s['pass'])    ? $s['pass']       : '';
        $from    = isset($s['from'])    ? $s['from']       : $user;
        $fromNm  = isset($s['from_name']) ? $s['from_name'] : 'TatooDenda';
        $timeout = isset($s['timeout']) ? (int) $s['timeout'] : 15;

        $transport = ($secure === 'ssl') ? "ssl://$host" : $host;

        $errno = 0; $errstr = '';
        $conn = @stream_socket_client("$transport:$port", $errno, $errstr, $timeout);
        if (!$conn) {
            return array('sent' => false, 'error' => "No se pudo conectar al SMTP: $errstr ($errno)");
        }
        stream_set_timeout($conn, $timeout);

        try {
            self::expect($conn, 220);

            $ehloHost = self::ehloName($from);
            self::cmd($conn, "EHLO $ehloHost", 250);

            if ($secure === 'tls') {
                self::cmd($conn, "STARTTLS", 220);
                if (!stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('No se pudo iniciar STARTTLS');
                }
                self::cmd($conn, "EHLO $ehloHost", 250);
            }

            if ($user !== '') {
                self::cmd($conn, "AUTH LOGIN", 334);
                self::cmd($conn, base64_encode($user), 334);
                self::cmd($conn, base64_encode($pass), 235);
            }

            self::cmd($conn, "MAIL FROM:<$from>", 250);
            self::cmd($conn, "RCPT TO:<$to>", array(250, 251));
            self::cmd($conn, "DATA", 354);

            $message = self::buildMessage($from, $fromNm, $to, $toName, $subject, $htmlBody, $attachments);
            // Cada línea que empiece por "." se escapa duplicándolo (dot-stuffing).
            $message = preg_replace('/^\./m', '..', $message);
            fwrite($conn, $message . "\r\n.\r\n");
            self::expect($conn, 250);

            self::cmd($conn, "QUIT", 221);
            fclose($conn);
            return array('sent' => true);
        } catch (Exception $e) {
            @fclose($conn);
            return array('sent' => false, 'error' => $e->getMessage());
        }
    }

    // ---- helpers privados ----------------------------------------------------

    private static function ehloName($from)
    {
        $at = strrchr($from, '@');
        return $at ? substr($at, 1) : 'localhost';
    }

    private static function cmd($conn, $command, $expected)
    {
        fwrite($conn, $command . "\r\n");
        return self::expect($conn, $expected);
    }

    private static function expect($conn, $expected)
    {
        $code = 0; $data = '';
        // Respuestas SMTP multilínea: "250-..." continúan; "250 ..." finaliza.
        while (($line = fgets($conn, 515)) !== false) {
            $data .= $line;
            $code = (int) substr($line, 0, 3);
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $ok = is_array($expected) ? in_array($code, $expected, true) : ($code === (int) $expected);
        if (!$ok) {
            throw new Exception("Respuesta SMTP inesperada ($code): " . trim($data));
        }
        return $code;
    }

    private static function buildMessage($from, $fromNm, $to, $toName, $subject, $htmlBody, $attachments)
    {
        $boundary = '=_tatoo_' . bin2hex(random_bytes(8));
        $eol = "\r\n";

        $headers  = 'From: ' . self::encodeName($fromNm) . " <$from>" . $eol;
        $headers .= 'To: ' . ($toName ? self::encodeName($toName) . " <$to>" : $to) . $eol;
        $headers .= 'Subject: ' . self::encodeName($subject) . $eol;
        $headers .= 'MIME-Version: 1.0' . $eol;
        $headers .= 'Date: ' . date('r') . $eol;

        $hasAtt = is_array($attachments) && count($attachments) > 0;

        if (!$hasAtt) {
            $headers .= 'Content-Type: text/html; charset=UTF-8' . $eol;
            $headers .= 'Content-Transfer-Encoding: base64' . $eol;
            return $headers . $eol . chunk_split(base64_encode($htmlBody));
        }

        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"" . $eol;

        $body  = "--$boundary" . $eol;
        $body .= 'Content-Type: text/html; charset=UTF-8' . $eol;
        $body .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
        $body .= chunk_split(base64_encode($htmlBody)) . $eol;

        foreach ($attachments as $att) {
            $name = isset($att['name']) ? $att['name'] : 'adjunto.bin';
            $mime = isset($att['mime']) ? $att['mime'] : 'application/octet-stream';
            $data = isset($att['data']) ? $att['data'] : '';
            $body .= "--$boundary" . $eol;
            $body .= "Content-Type: $mime; name=\"$name\"" . $eol;
            $body .= 'Content-Transfer-Encoding: base64' . $eol;
            $body .= "Content-Disposition: attachment; filename=\"$name\"" . $eol . $eol;
            $body .= chunk_split(base64_encode($data)) . $eol;
        }
        $body .= "--$boundary--" . $eol;

        return $headers . $eol . $body;
    }

    private static function encodeName($text)
    {
        // Codifica UTF-8 en cabeceras si hace falta (RFC 2047).
        if (preg_match('/[^\x20-\x7e]/', $text)) {
            return '=?UTF-8?B?' . base64_encode($text) . '?=';
        }
        return $text;
    }
}
