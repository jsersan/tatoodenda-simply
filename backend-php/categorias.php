<?php
// =============================================================================
//  CATEGORIAS  ·  Repositorio de la tabla `categoria`
//  Shape (models/category.ts): { id, nombre, padre }
//  Regla del modelo original: una categoría "principal" tiene padre = su propio id.
// =============================================================================

class CategoriaRepo
{
    private static function hydrate($row)
    {
        return array(
            'id'     => (int) $row['id'],
            'nombre' => (string) $row['nombre'],
            'padre'  => (int) $row['padre'],
        );
    }

    public static function all()
    {
        $stmt = DB::pdo()->query("SELECT id, nombre, padre FROM categoria ORDER BY id");
        return array_map(array('self', 'hydrate'), $stmt->fetchAll());
    }

    public static function get($id)
    {
        $stmt = DB::pdo()->prepare("SELECT id, nombre, padre FROM categoria WHERE id = ?");
        $stmt->execute(array($id));
        $row = $stmt->fetch();
        return $row ? self::hydrate($row) : null;
    }

    public static function create($data)
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $padre  = isset($data['padre']) ? (int) $data['padre'] : -1;

        $stmt = DB::pdo()->prepare("INSERT INTO categoria (nombre, padre) VALUES (?, ?)");
        $stmt->execute(array($nombre, $padre));
        $id = (int) DB::pdo()->lastInsertId();

        // Si es principal (padre = -1), se autoasigna padre = id (como el hook Sequelize).
        if ($padre === -1) {
            DB::pdo()->prepare("UPDATE categoria SET padre = ? WHERE id = ?")
                     ->execute(array($id, $id));
        }
        return self::get($id);
    }

    public static function update($id, $data)
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $padre  = isset($data['padre']) ? (int) $data['padre'] : $id;
        $stmt = DB::pdo()->prepare("UPDATE categoria SET nombre = ?, padre = ? WHERE id = ?");
        $stmt->execute(array($nombre, $padre, $id));
        return self::get($id);
    }

    public static function delete($id)
    {
        $stmt = DB::pdo()->prepare("DELETE FROM categoria WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->rowCount() > 0;
    }
}
