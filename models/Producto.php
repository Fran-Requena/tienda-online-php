<?php
require_once __DIR__ . '/../config/Conexion.php';

/**
 * Clase Producto
 * Gestiona las operaciones de consulta y control de stock de los artículos
 * de merchandising en la base de datos.
 */
class Producto {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenerConexion();
    }

    /**
     * Obtiene el listado completo de productos del catálogo.
     *
     * @return array Lista de productos en formato asociativo.
     */
    public function obtenerTodos(): array {
        $sql = "SELECT idProducto, nombre, descripcion, imagen, precio, stock FROM productos ORDER BY idProducto ASC";
        $sentencia = $this->conexion->prepare($sql);
        $sentencia->execute();

        return $sentencia->fetchAll();
    }

    /**
     * Busca un producto concreto por su identificador único.
     *
     * @param int $id Identificador del producto.
     * @return array|false Datos del producto o false si no existe.
     */
    public function obtenerPorId(int $id): array|false {
        $sql = "SELECT idProducto, nombre, descripcion, imagen, precio, stock FROM productos WHERE idProducto = :id";
        $sentencia = $this->conexion->prepare($sql);
        $sentencia->execute([':id' => $id]);

        return $sentencia->fetch();
    }

    /**
     * Comprueba si existe stock suficiente para cubrir una cantidad solicitada.
     *
     * @param int $productoId Identificador del producto.
     * @param int $cantidadSolicitada Unidades que el usuario desea añadir o comprar.
     * @return bool True si hay stock suficiente, false en caso contrario.
     */
    public function hayStockSuficiente(int $productoId, int $cantidadSolicitada): bool {
        if ($cantidadSolicitada <= 0) {
            return false;
        }

        $producto = $this->obtenerPorId($productoId);

        if (!$producto) {
            return false;
        }

        return (int)$producto['stock'] >= $cantidadSolicitada;
    }

    /**
     * Descuenta unidades del stock tras confirmar una compra.
     * Incluye la condición stock >= :cantidad para evitar valores negativos.
     *
     * @param int $productoId Identificador del producto.
     * @param int $cantidadComprada Unidades vendidas.
     * @return bool True si se actualizó el stock correctamente.
     */
    public function restarStock(int $productoId, int $cantidadComprada): bool {
        $sql = "UPDATE productos 
                SET stock = stock - :cantidad 
                WHERE idProducto = :id AND stock >= :cantidad";

        $sentencia = $this->conexion->prepare($sql);
        $sentencia->execute([
            ':cantidad' => $cantidadComprada,
            ':id'       => $productoId
        ]);

        return $sentencia->rowCount() > 0;
    }
}
