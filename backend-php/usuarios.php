<?php
// =============================================================================
//  USUARIOS  ·  Repositorio de la tabla `user` + autenticación
//  Contraseñas con password_hash() (bcrypt nativo de PHP).
//  Shape público (models/user.ts): sin password.
// =============================================================================

class UsuarioRepo
{
    private static function hydrate($row)
    {
        return array(
            'id'        => (int) $row['id'],
            'username'  => (string) $row['username'],
            'nombre'    => (string) $row['nombre'],
            'email'     => (string) $row['email'],
            'direccion' => (string) ($row['direccion'] ?? ''),
            'ciudad'    => (string) ($row['ciudad'] ?? ''),
            'cp'        => (string) ($row['cp'] ?? ''),
            'role'      => (string) ($row['role'] ?? 'user'),
        );
    }

    public static function get($id)
    {
        $stmt = DB::pdo()->prepare("SELECT * FROM user WHERE id = ?");
        $stmt->execute(array($id));
        $row = $stmt->fetch();
        return $row ? self::hydrate($row) : null;
    }

    /** Registra un usuario. Devuelve el usuario público o lanza mensaje de error. */
    public static function register($data)
    {
        $username = trim((string) ($data['username'] ?? ''));
        $email    = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $email === '' || $password === '') {
            return array('error' => 'Faltan campos obligatorios (username, email, password).');
        }

        // ¿Ya existe username o email?
        $stmt = DB::pdo()->prepare("SELECT id FROM user WHERE email = ? OR username = ? LIMIT 1");
        $stmt->execute(array($email, $username));
        if ($stmt->fetch()) {
            return array('error' => 'El usuario o el email ya están registrados.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = DB::pdo()->prepare(
            "INSERT INTO user (username, password, nombre, email, direccion, ciudad, cp, role)
             VALUES (:username, :password, :nombre, :email, :direccion, :ciudad, :cp, 'user')"
        );
        $stmt->execute(array(
            'username'  => $username,
            'password'  => $hash,
            'nombre'    => trim((string) ($data['nombre'] ?? $username)),
            'email'     => $email,
            'direccion' => trim((string) ($data['direccion'] ?? '')),
            'ciudad'    => trim((string) ($data['ciudad'] ?? '')),
            'cp'        => trim((string) ($data['cp'] ?? '')),
        ));

        return self::get((int) DB::pdo()->lastInsertId());
    }

    /** Comprueba credenciales. Devuelve el usuario público o null. */
    public static function login($username, $password)
    {
        $stmt = DB::pdo()->prepare("SELECT * FROM user WHERE username = ? LIMIT 1");
        $stmt->execute(array($username));
        $row = $stmt->fetch();
        if (!$row) return null;

        // Verifica bcrypt de PHP; y como red de seguridad, migra hashes antiguos en texto plano.
        $ok = password_verify((string) $password, (string) $row['password']);
        if (!$ok && hash_equals((string) $row['password'], (string) $password)) {
            // Contraseña guardada en claro (datos heredados): la re-hasheamos.
            $new = password_hash((string) $password, PASSWORD_BCRYPT);
            DB::pdo()->prepare("UPDATE user SET password = ? WHERE id = ?")
                     ->execute(array($new, $row['id']));
            $ok = true;
        }
        return $ok ? self::hydrate($row) : null;
    }

    /** Actualiza datos de perfil (no toca password salvo que venga uno nuevo). */
    public static function update($id, $data)
    {
        $current = self::get($id);
        if (!$current) return null;

        $fields = array(
            'username'  => isset($data['username'])  ? trim((string) $data['username'])  : $current['username'],
            'nombre'    => isset($data['nombre'])    ? trim((string) $data['nombre'])    : $current['nombre'],
            'email'     => isset($data['email'])     ? trim((string) $data['email'])     : $current['email'],
            'direccion' => isset($data['direccion']) ? trim((string) $data['direccion']) : $current['direccion'],
            'ciudad'    => isset($data['ciudad'])    ? trim((string) $data['ciudad'])    : $current['ciudad'],
            'cp'        => isset($data['cp'])        ? trim((string) $data['cp'])        : $current['cp'],
        );

        $sql = "UPDATE user SET username = :username, nombre = :nombre, email = :email,
                       direccion = :direccion, ciudad = :ciudad, cp = :cp";
        $params = $fields;
        $params['id'] = $id;

        if (!empty($data['password'])) {
            $sql .= ", password = :password";
            $params['password'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }
        $sql .= " WHERE id = :id";

        DB::pdo()->prepare($sql)->execute($params);
        return self::get($id);
    }

    // -------------------------------------------------------------------------
    //  RECUPERACIÓN DE CONTRASEÑA
    //  En la BBDD se guarda el HASH del token (no el token en claro), más su
    //  caducidad. El enlace del email lleva el token en claro.
    // -------------------------------------------------------------------------

    /**
     * Crea un token de recuperación para el email dado.
     * Devuelve ['token'=>..., 'user'=>...] si el email existe, o null si no.
     * (El endpoint responde igual exista o no, para no filtrar usuarios.)
     */
    public static function createResetToken($email, $ttlSeconds = 3600)
    {
        $stmt = DB::pdo()->prepare("SELECT * FROM user WHERE email = ? LIMIT 1");
        $stmt->execute(array(trim((string) $email)));
        $row = $stmt->fetch();
        if (!$row) return null;

        $token  = bin2hex(random_bytes(32));
        $hash   = hash('sha256', $token);
        $expiry = date('Y-m-d H:i:s', time() + $ttlSeconds);

        DB::pdo()->prepare("UPDATE user SET resetToken = ?, resetTokenExpiry = ? WHERE id = ?")
                 ->execute(array($hash, $expiry, $row['id']));

        return array('token' => $token, 'user' => self::hydrate($row));
    }

    /** Devuelve el usuario si el token es válido y no ha caducado; si no, null. */
    public static function findByResetToken($token)
    {
        $hash = hash('sha256', (string) $token);
        $stmt = DB::pdo()->prepare(
            "SELECT * FROM user
              WHERE resetToken = ? AND resetTokenExpiry IS NOT NULL
                AND resetTokenExpiry >= NOW() LIMIT 1"
        );
        $stmt->execute(array($hash));
        $row = $stmt->fetch();
        return $row ? $row : null;   // fila cruda (incluye id); no exponer fuera
    }

    /** Restablece la contraseña con un token válido. Devuelve true/false. */
    public static function resetPassword($token, $newPassword)
    {
        if (strlen((string) $newPassword) < 4) return false;
        $row = self::findByResetToken($token);
        if (!$row) return false;

        $hash = password_hash((string) $newPassword, PASSWORD_BCRYPT);
        DB::pdo()->prepare(
            "UPDATE user SET password = ?, resetToken = NULL, resetTokenExpiry = NULL WHERE id = ?"
        )->execute(array($hash, $row['id']));
        return true;
    }
}
