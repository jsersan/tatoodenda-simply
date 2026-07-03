<?php
// =============================================================================
//  PEDIDOS  ·  Repositorio de `pedido` y `lineapedido`
//  Shapes que espera el frontend (models/order.ts, OrderBackend/OrderLineBackend):
//    pedido: { id, iduser, fecha, total, lineas:[...] }
//    linea : { id, idpedido, idprod, color, cant, nombre, precio }
// =============================================================================

class PedidoRepo
{
    private static function hydrateLinea($row)
    {
        return array(
            'id'       => (int) $row['id'],
            'idpedido' => (int) $row['idpedido'],
            'idprod'   => (int) $row['idprod'],
            'color'    => (string) $row['color'],
            'cant'     => (int) $row['cant'],
            'nombre'   => (string) ($row['nombre'] ?? ''),
            'precio'   => (float) $row['precio'],
        );
    }

    private static function hydratePedido($row)
    {
        return array(
            'id'     => (int) $row['id'],
            'iduser' => (int) $row['iduser'],
            'fecha'  => (string) $row['fecha'],
            'total'  => (float) $row['total'],
        );
    }

    /** Líneas de un pedido. */
    public static function lines($pedidoId)
    {
        $stmt = DB::pdo()->prepare(
            "SELECT id, idpedido, idprod, color, cant, nombre, precio
               FROM lineapedido WHERE idpedido = ? ORDER BY id"
        );
        $stmt->execute(array((int) $pedidoId));
        return array_map(array('self', 'hydrateLinea'), $stmt->fetchAll());
    }

    /** Un pedido con sus líneas, o null. */
    public static function get($id)
    {
        $stmt = DB::pdo()->prepare("SELECT id, iduser, fecha, total FROM pedido WHERE id = ?");
        $stmt->execute(array((int) $id));
        $row = $stmt->fetch();
        if (!$row) return null;
        $pedido = self::hydratePedido($row);
        $pedido['lineas'] = self::lines($pedido['id']);
        return $pedido;
    }

    /** Todos los pedidos de un usuario, con sus líneas (más recientes primero). */
    public static function byUser($userId)
    {
        $stmt = DB::pdo()->prepare(
            "SELECT id, iduser, fecha, total FROM pedido
              WHERE iduser = ? ORDER BY id DESC"
        );
        $stmt->execute(array((int) $userId));
        $pedidos = array_map(array('self', 'hydratePedido'), $stmt->fetchAll());
        foreach ($pedidos as &$p) {
            $p['lineas'] = self::lines($p['id']);
        }
        return $pedidos;
    }

    /**
     * Crea un pedido y sus líneas dentro de una transacción.
     * Acepta iduser/userId/usuario_id y calcula el total desde las líneas si no llega.
     */
    public static function create($data)
    {
        $iduser = (int) ($data['iduser'] ?? ($data['userId'] ?? ($data['usuario_id'] ?? 0)));
        $lineas = isset($data['lineas']) && is_array($data['lineas']) ? $data['lineas'] : array();

        if ($iduser <= 0)          return array('error' => 'Usuario no especificado.');
        if (count($lineas) === 0)  return array('error' => 'El pedido debe contener al menos un producto.');

        // Total: usa el enviado o lo calcula (cant * precio).
        $total = isset($data['total']) ? (float) $data['total'] : 0.0;
        if ($total <= 0) {
            foreach ($lineas as $l) {
                $total += ((int) ($l['cant'] ?? 0)) * ((float) ($l['precio'] ?? 0));
            }
        }

        $fecha = isset($data['fecha']) && $data['fecha'] !== ''
            ? substr((string) $data['fecha'], 0, 10)
            : date('Y-m-d');

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO pedido (iduser, fecha, total) VALUES (?, ?, ?)"
            );
            $stmt->execute(array($iduser, $fecha, $total));
            $pedidoId = (int) $pdo->lastInsertId();

            $stmtL = $pdo->prepare(
                "INSERT INTO lineapedido (idpedido, idprod, color, cant, nombre, precio)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            foreach ($lineas as $l) {
                $stmtL->execute(array(
                    $pedidoId,
                    (int) ($l['idprod'] ?? 0),
                    (string) ($l['color'] ?? 'Estándar'),
                    (int) ($l['cant'] ?? 1),
                    (string) ($l['nombre'] ?? ''),
                    (float) ($l['precio'] ?? 0),
                ));
            }

            $pdo->commit();
            return self::get($pedidoId);
        } catch (Exception $e) {
            $pdo->rollBack();
            return array('error' => 'No se pudo crear el pedido: ' . $e->getMessage());
        }
    }
}
