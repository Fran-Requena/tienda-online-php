<?php

require_once __DIR__ . '/Carrito.php';
require_once __DIR__ . '/Usuario.php';

class Pedido
{
    private PDO $conexion;

    public function __construct(?PDO $conexion = null)
    {
        $this->conexion = $conexion ?? Conexion::obtenerConexion();
    }

    public static function calcularCosteEnvio(float $subtotal, string $pais): float
    {
        return match ($pais) {
            'España' => $subtotal > 100 ? 0.0 : 5.0,
            'Portugal' => $subtotal > 100 ? 10.0 : 20.0,
            default => throw new DomainException('Actualiza tu perfil con una dirección de España o Portugal.'),
        };
    }

    public function realizarCompra(int $usuarioId): int
    {
        if ($usuarioId <= 0 || $usuarioId !== (int)($_SESSION['usuarioId'] ?? 0)) {
            throw new DomainException('Debes iniciar sesión para finalizar tu compra');
        }
        $this->conexion->beginTransaction();
        try {
            $carrito = new Carrito($this->conexion);
            $carrito->bloquearUsuario($usuarioId);
            $usuario = (new Usuario($this->conexion))->obtenerPorId($usuarioId);
            // Los bloqueos de producto se toman siempre por ID para evitar interbloqueos.
            $sentencia = $this->conexion->prepare(
                'SELECT p.idProducto AS id, p.nombre, p.precio, p.stock, c.cantidad
                 FROM lineaCarrito c JOIN productos p ON p.idProducto = c.idProducto
                 WHERE c.idUsuario = :usuarioId ORDER BY p.idProducto FOR UPDATE'
            );
            $sentencia->execute([':usuarioId' => $usuarioId]);
            $items = $sentencia->fetchAll(PDO::FETCH_ASSOC);
            if ($items === []) {
                throw new DomainException('Tu carrito está vacío. Añade algún producto antes de finalizar la compra.');
            }
            $subtotalCentimos = 0;
            foreach ($items as $item) {
                if ((int)$item['cantidad'] <= 0 || (int)$item['stock'] < (int)$item['cantidad']) {
                    throw new DomainException('No hay stock suficiente de «' . $item['nombre'] . '». Revisa su cantidad en el carrito.');
                }
                $subtotalCentimos += (int)round((float)$item['precio'] * 100) * (int)$item['cantidad'];
            }
            $subtotal = $subtotalCentimos / 100;
            $costeEnvio = self::calcularCosteEnvio($subtotal, $usuario['pais']);
            $sentencia = $this->conexion->prepare(
                'INSERT INTO pedidos (idUsuario, subtotal, gastosEnvio, totalPedido)
                 VALUES (:usuarioId, :subtotal, :costeEnvio, :total)'
            );
            $sentencia->execute([
                ':usuarioId' => $usuarioId, ':subtotal' => number_format($subtotal, 2, '.', ''),
                ':costeEnvio' => $costeEnvio, ':total' => number_format(($subtotalCentimos + (int)round($costeEnvio * 100)) / 100, 2, '.', ''),
            ]);
            $pedidoId = (int)$this->conexion->lastInsertId();
            $insertarLinea = $this->conexion->prepare(
                'INSERT INTO lineaPedido (idPedido, idProducto, cantidad, precioUnitario) VALUES (:pedidoId, :productoId, :cantidad, :precio)'
            );
            $restarStock = $this->conexion->prepare(
                'UPDATE productos SET stock = stock - :cantidad WHERE idProducto = :productoId AND stock >= :cantidadMinima'
            );
            foreach ($items as $item) {
                $insertarLinea->execute([':pedidoId' => $pedidoId, ':productoId' => $item['id'], ':cantidad' => $item['cantidad'], ':precio' => $item['precio']]);
                $restarStock->execute([':cantidad' => $item['cantidad'], ':productoId' => $item['id'], ':cantidadMinima' => $item['cantidad']]);
                if ($restarStock->rowCount() !== 1) {
                    throw new DomainException('El stock ha cambiado. Revisa tu carrito antes de continuar.');
                }
            }
            $carrito->vaciarCarrito($usuarioId);
            $this->conexion->commit();
            return $pedidoId;
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }
    }
}
