<?php
// =============================================================================
//  PRODUCTOS  ·  Repositorio de la tabla `producto`
//  Shape que consume el frontend (models/product.ts):
//  { id, nombre, descripcion, precio, imagen, categoria, categoria_id, carpetaimg }
// =============================================================================

class ProductoRepo
{
    // Mapa categoría->carpeta de imágenes (equivale a getCarpetaImg del modelo Node).
    private static $CARPETAS = array(
        1 => 'anillo', 2 => 'barbell', 3 => 'tuneles', 4 => 'plug',
        5 => 'expander', 6 => 'banana', 7 => 'labret', 8 => 'barbell',
        9 => 'circular-barbel', 10 => 'anillo', 11 => 'piercing', 12 => 'dilataciones',
    );

    private static function carpeta($categoria)
    {
        $cat = (int) $categoria;
        return isset(self::$CARPETAS[$cat]) ? self::$CARPETAS[$cat] : 'default';
    }

    private static function hydrate($row)
    {
        $categoria = (int) $row['categoria'];
        return array(
            'id'           => (int) $row['id'],
            'nombre'       => (string) $row['nombre'],
            'descripcion'  => (string) ($row['descripcion'] ?? ''),
            'precio'       => (float) $row['precio'],
            'imagen'       => (string) ($row['imagen'] ?? ''),
            'categoria'    => $categoria,
            'categoria_id' => $categoria,        // alias que usan algunos componentes
            'carpetaimg'   => self::carpeta($categoria),
        );
    }

    public static function all()
    {
        $stmt = DB::pdo()->query(
            "SELECT id, nombre, descripcion, precio, imagen, categoria
               FROM producto ORDER BY id DESC"
        );
        return array_map(array('self', 'hydrate'), $stmt->fetchAll());
    }

    public static function get($id)
    {
        $stmt = DB::pdo()->prepare(
            "SELECT id, nombre, descripcion, precio, imagen, categoria
               FROM producto WHERE id = ?"
        );
        $stmt->execute(array($id));
        $row = $stmt->fetch();
        return $row ? self::hydrate($row) : null;
    }

    public static function byCategory($categoryId)
    {
        $stmt = DB::pdo()->prepare(
            "SELECT id, nombre, descripcion, precio, imagen, categoria
               FROM producto WHERE categoria = ? ORDER BY id DESC"
        );
        $stmt->execute(array((int) $categoryId));
        return array_map(array('self', 'hydrate'), $stmt->fetchAll());
    }

    public static function search($term)
    {
        if ($term === null || trim($term) === '') return array();
        $like = '%' . $term . '%';
        $stmt = DB::pdo()->prepare(
            "SELECT id, nombre, descripcion, precio, imagen, categoria
               FROM producto
              WHERE nombre LIKE ? OR descripcion LIKE ?
              ORDER BY id DESC
              LIMIT 50"
        );
        $stmt->execute(array($like, $like));
        return array_map(array('self', 'hydrate'), $stmt->fetchAll());
    }

    public static function create($data)
    {
        $stmt = DB::pdo()->prepare(
            "INSERT INTO producto (nombre, descripcion, precio, categoria, imagen)
             VALUES (:nombre, :descripcion, :precio, :categoria, :imagen)"
        );
        $stmt->execute(self::sanitize($data));
        return self::get((int) DB::pdo()->lastInsertId());
    }

    public static function update($id, $data)
    {
        $params = self::sanitize($data);
        $params['id'] = $id;
        $stmt = DB::pdo()->prepare(
            "UPDATE producto
                SET nombre = :nombre, descripcion = :descripcion,
                    precio = :precio, categoria = :categoria, imagen = :imagen
              WHERE id = :id"
        );
        $stmt->execute($params);
        return self::get($id);
    }

    public static function delete($id)
    {
        $stmt = DB::pdo()->prepare("DELETE FROM producto WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->rowCount() > 0;
    }

    // El frontend admite categoria / categoria_id / category_id: aceptamos las tres.
    private static function sanitize($data)
    {
        $categoria = $data['categoria'] ?? ($data['categoria_id'] ?? ($data['category_id'] ?? 0));
        return array(
            'nombre'      => trim((string) ($data['nombre'] ?? '')),
            'descripcion' => trim((string) ($data['descripcion'] ?? '')),
            'precio'      => (float) ($data['precio'] ?? 0),
            'categoria'   => (int) $categoria,
            'imagen'      => trim((string) ($data['imagen'] ?? '')),
        );
    }
}
