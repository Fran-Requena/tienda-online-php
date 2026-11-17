<?php

require_once __DIR__ . '/../models/Carrito.php';
require_once __DIR__ . '/../utils/ProteccionFormulario.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$accion = $_POST['accion'] ?? 'agregar';
$destino = $accion === 'agregar' ? 'index.php' : 'views/carrito.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !ProteccionFormulario::validarToken($_POST['tokenFormulario'] ?? null)) {
        throw new DomainException('La solicitud ha caducado. Recarga la página e inténtalo de nuevo.');
    }
    $productoId = filter_var($_POST['productoId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
    $cantidad = filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
    if (!$productoId || !in_array($accion, ['agregar', 'actualizar', 'eliminar'], true) || ($accion !== 'eliminar' && !$cantidad)) {
        throw new DomainException('Introduce un producto y una cantidad válidos.');
    }
    $carrito = new Carrito();
    if ($accion === 'eliminar') {
        $carrito->eliminarProducto($productoId);
        $_SESSION['alerta'] = 'Producto eliminado del carrito.';
    } else {
        $cantidadFinal = $accion === 'agregar'
            ? $carrito->agregarProducto($productoId, $cantidad)
            : $carrito->actualizarCantidad($productoId, $cantidad);
        $_SESSION['alerta'] = 'Carrito actualizado: ' . $cantidadFinal . ' unidades de este producto. La cantidad se limita al stock disponible.';
    }
} catch (DomainException $error) {
    $_SESSION['alerta'] = $error->getMessage();
} catch (Throwable $error) {
    error_log('Error de carrito: ' . $error->getMessage());
    $_SESSION['alerta'] = 'No se ha podido actualizar el carrito. Inténtalo de nuevo más tarde.';
}
header('Location: /tienda-online-php/' . $destino, true, 303);
exit;
