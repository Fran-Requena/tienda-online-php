<?php

require_once __DIR__ . '/../config/Conexion.php';

class Carrito
{
    private PDO $conexion;

    public function __construct(?PDO $conexion = null)
    {
        $this->conexion = $conexion ?? Conexion::obtenerConexion();
    }

    /** La sesión tiene prioridad: la cookie es una copia, no otro carrito que sumar. */
    private function obtenerCarritoInvitado(): array
    {
        $datos = $_SESSION['carrito'] ?? null;
        if ($datos === null) {
            $cookie = $_COOKIE['carritoInvitado'] ?? '';
            $datos = is_string($cookie) && strlen($cookie) <= 4096 ? json_decode($cookie, true, 32) : [];
        }
        $carrito = [];
        foreach (is_array($datos) ? $datos : [] as $productoId => $cantidad) {
            if (!is_int($cantidad) && !is_string($cantidad)) {
                continue;
            }
            $id = filter_var($productoId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
            $unidades = filter_var($cantidad, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
            if ($id !== false && $unidades !== false) {
                $carrito[$id] = $unidades;
            }
        }
        $_SESSION['carrito'] = $carrito;
        return $carrito;
    }

    private function guardarCarritoInvitado(array $carrito): void
    {
        $cookie = json_encode($carrito, JSON_THROW_ON_ERROR);
        if (strlen(rawurlencode($cookie)) > 3800) {
            throw new DomainException('Tu carrito tiene demasiados artículos distintos. Inicia sesión para guardar más.');
        }
        setcookie('carritoInvitado', $cookie, [
            'expires' => time() + 7 * 24 * 3600,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['carritoInvitado'] = $cookie;
        $_SESSION['carrito'] = $carrito;
    }

    /** Serializa las modificaciones del carrito con las compras del mismo usuario. */
    public function bloquearUsuario(int $usuarioId): void
    {
        $sentencia = $this->conexion->prepare('SELECT idUsuario FROM usuarios WHERE idUsuario = :usuarioId FOR UPDATE');
        $sentencia->execute([':usuarioId' => $usuarioId]);
        if (!$sentencia->fetch()) {
            throw new DomainException('Tu sesión ya no es válida. Inicia sesión de nuevo.');
        }
    }

    private function guardarCantidad(int $usuarioId, int $productoId, int $cantidad, int $stock, bool $sumar): void
    {
        $actualizacion = $sumar ? 'LEAST(:stock, cantidad + VALUES(cantidad))' : 'VALUES(cantidad)';
        $sentencia = $this->conexion->prepare(
            'INSERT INTO lineaCarrito (idUsuario, idProducto, cantidad) VALUES (:usuarioId, :productoId, :cantidad)
             ON DUPLICATE KEY UPDATE cantidad = ' . $actualizacion
        );
        $parametros = [':usuarioId' => $usuarioId, ':productoId' => $productoId, ':cantidad' => min($cantidad, $stock)];
        if ($sumar) {
            $parametros[':stock'] = $stock;
        }
        $sentencia->execute($parametros);
    }

    public function agregarProducto(int $productoId, int $cantidad): int
    {
        return $this->cambiarCantidad($productoId, $cantidad, true);
    }

    public function actualizarCantidad(int $productoId, int $cantidad): int
    {
        return $this->cambiarCantidad($productoId, $cantidad, false);
    }

    private function cambiarCantidad(int $productoId, int $cantidad, bool $sumar): int
    {
        if ($productoId <= 0 || $cantidad <= 0 || $cantidad > 2147483647) {
            throw new DomainException('Introduce un producto y una cantidad válidos.');
        }
        $usuarioId = (int)($_SESSION['usuarioId'] ?? 0);
        $this->conexion->beginTransaction();
        try {
            if ($usuarioId > 0) {
                $this->bloquearUsuario($usuarioId);
            }
            $sentencia = $this->conexion->prepare('SELECT stock FROM productos WHERE idProducto = :productoId FOR UPDATE');
            $sentencia->execute([':productoId' => $productoId]);
            $producto = $sentencia->fetch();
            if (!$producto || (int)$producto['stock'] <= 0) {
                throw new DomainException('Este producto ya no está disponible. Puedes eliminarlo del carrito.');
            }
            $stock = (int)$producto['stock'];
            if ($usuarioId > 0) {
                $this->guardarCantidad($usuarioId, $productoId, $cantidad, $stock, $sumar);
                $sentencia = $this->conexion->prepare('SELECT cantidad FROM lineaCarrito WHERE idUsuario = :usuarioId AND idProducto = :productoId');
                $sentencia->execute([':usuarioId' => $usuarioId, ':productoId' => $productoId]);
                $cantidadFinal = (int)$sentencia->fetchColumn();
            } else {
                $carrito = $this->obtenerCarritoInvitado();
                $cantidadFinal = min($stock, ($sumar ? ($carrito[$productoId] ?? 0) : 0) + $cantidad);
                $carrito[$productoId] = $cantidadFinal;
                $this->guardarCarritoInvitado($carrito);
            }
            $this->conexion->commit();
            return $cantidadFinal;
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }
    }

    public function obtenerCarritoDetallado(): array
    {
        $usuarioId = (int)($_SESSION['usuarioId'] ?? 0);
        if ($usuarioId > 0) {
            $sentencia = $this->conexion->prepare(
                'SELECT p.idProducto AS id, p.nombre, p.imagen, p.precio, c.cantidad, p.stock
                 FROM lineaCarrito c JOIN productos p ON p.idProducto = c.idProducto
                 WHERE c.idUsuario = :usuarioId ORDER BY p.idProducto'
            );
            $sentencia->execute([':usuarioId' => $usuarioId]);
            $items = $sentencia->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $carrito = $this->obtenerCarritoInvitado();
            if ($carrito === []) {
                return [];
            }
            $marcadores = implode(',', array_fill(0, count($carrito), '?'));
            $sentencia = $this->conexion->prepare('SELECT idProducto AS id, nombre, imagen, precio, stock FROM productos WHERE idProducto IN (' . $marcadores . ') ORDER BY idProducto');
            $sentencia->execute(array_keys($carrito));
            $items = $sentencia->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as &$item) {
                $item['cantidad'] = $carrito[$item['id']];
            }
            unset($item);
        }
        foreach ($items as &$item) {
            $item['id'] = (int)$item['id'];
            $item['cantidad'] = (int)$item['cantidad'];
            $item['stock'] = (int)$item['stock'];
            $item['subtotal'] = (int)round((float)$item['precio'] * 100) * $item['cantidad'] / 100;
        }
        unset($item);
        return $items;
    }

    public function eliminarProducto(int $productoId): void
    {
        $usuarioId = (int)($_SESSION['usuarioId'] ?? 0);
        if ($usuarioId <= 0) {
            $carrito = $this->obtenerCarritoInvitado();
            unset($carrito[$productoId]);
            $this->guardarCarritoInvitado($carrito);
            return;
        }
        $this->conexion->beginTransaction();
        try {
            $this->bloquearUsuario($usuarioId);
            $sentencia = $this->conexion->prepare('DELETE FROM lineaCarrito WHERE idUsuario = :usuarioId AND idProducto = :productoId');
            $sentencia->execute([':usuarioId' => $usuarioId, ':productoId' => $productoId]);
            $this->conexion->commit();
        } catch (Throwable $error) {
            $this->conexion->rollBack();
            throw $error;
        }
    }

    public function fusionarCarritoInvitadoAlLoguear(int $usuarioId): void
    {
        $carrito = $this->obtenerCarritoInvitado();
        ksort($carrito);
        $this->conexion->beginTransaction();
        try {
            $this->bloquearUsuario($usuarioId);
            $sentencia = $this->conexion->prepare('SELECT stock FROM productos WHERE idProducto = :productoId FOR UPDATE');
            foreach ($carrito as $productoId => $cantidad) {
                $sentencia->execute([':productoId' => $productoId]);
                $stock = (int)$sentencia->fetchColumn();
                if ($stock > 0) {
                    $this->guardarCantidad($usuarioId, $productoId, $cantidad, $stock, true);
                }
            }
            $this->conexion->commit();
        } catch (Throwable $error) {
            $this->conexion->rollBack();
            throw $error;
        }
        unset($_SESSION['carrito'], $_COOKIE['carritoInvitado']);
        setcookie('carritoInvitado', '', time() - 3600, '/');
    }

    /** Usa la conexión compartida para formar parte de la transacción del pedido. */
    public function vaciarCarrito(int $usuarioId): void
    {
        $sentencia = $this->conexion->prepare('DELETE FROM lineaCarrito WHERE idUsuario = :usuarioId');
        $sentencia->execute([':usuarioId' => $usuarioId]);
    }
}
