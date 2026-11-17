<?php

require_once __DIR__ . '/../models/Pedido.php';
require_once __DIR__ . '/../models/Carrito.php';
require_once __DIR__ . '/../utils/ProteccionFormulario.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$accion = $_GET['accion'] ?? $_POST['accion'] ?? 'comprar';
if (empty($_SESSION['usuarioId'])) {
    $_SESSION['alerta'] = $accion === 'recomprar' ? 'Debes iniciar sesión para volver a comprar un pedido.' : 'Debes iniciar sesión para finalizar tu compra';
    header('Location: /tienda-online-php/views/login.php', true, 303);
    exit;
}

$destino = $accion === 'recomprar' ? 'views/perfil.php' : 'views/carrito.php';
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !ProteccionFormulario::validarToken($_POST['tokenFormulario'] ?? null)) {
        throw new DomainException('La solicitud ha caducado. Recarga la página e inténtalo de nuevo.');
    }
    if (!in_array($accion, ['comprar', 'recomprar'], true)) {
        throw new DomainException('La solicitud no es válida.');
    }
    $modeloPedidos = new Pedido();
    $usuarioId = (int)$_SESSION['usuarioId'];
    if ($accion === 'recomprar') {
        $pedidoId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        if ($pedidoId === false || !$modeloPedidos->perteneceAUsuario($pedidoId, $usuarioId)) {
            throw new DomainException('No se ha encontrado ese pedido en tu historial.');
        }
        $lineasPedido = $modeloPedidos->obtenerLineasPedido($pedidoId);
        $carrito = new Carrito();
        $productosAnadidos = 0;
        $productosNoDisponibles = 0;
        $destino = 'views/carrito.php';
        foreach ($lineasPedido as $linea) {
            try {
                $carrito->agregarProducto((int)$linea['productoId'], (int)$linea['cantidad']);
                $productosAnadidos++;
            } catch (DomainException $error) {
                // Un artículo agotado no impide recuperar los demás productos.
                $productosNoDisponibles++;
            }
        }
        $_SESSION['alerta'] = $productosAnadidos > 0
            ? 'Los productos disponibles se han añadido al carrito, sin superar el stock actual.'
            : 'Ningún producto de este pedido está disponible actualmente. Tu carrito conserva los artículos que ya tenía.';
        if ($productosAnadidos > 0 && $productosNoDisponibles > 0) {
            $_SESSION['alerta'] .= ' Algunos artículos ya no están disponibles y no se han añadido.';
        }
    } else {
        $pedidoId = $modeloPedidos->realizarCompra($usuarioId);
        // Un formulario ya consumido no puede volver a efectuar otra compra.
        unset($_SESSION['tokenFormulario']);
        $_SESSION['alerta'] = 'Tu pedido #' . $pedidoId . ' se ha realizado correctamente. ¡Gracias por tu compra!';
        $destino = 'index.php';
    }
} catch (DomainException $error) {
    $_SESSION['alerta'] = $error->getMessage();
} catch (Throwable $error) {
    error_log('Error de pedido: ' . $error->getMessage());
    $_SESSION['alerta'] = $accion === 'recomprar'
        ? 'No se ha podido completar la recompra. Revisa tu carrito antes de volver a intentarlo.'
        : 'No se ha podido completar el pedido. Tu carrito sigue guardado. Inténtalo de nuevo más tarde.';
}
header('Location: /tienda-online-php/' . $destino, true, 303);
exit;
