<?php

require_once __DIR__ . '/../models/Pedido.php';
require_once __DIR__ . '/../utils/ProteccionFormulario.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['usuarioId'])) {
    $_SESSION['alerta'] = 'Debes iniciar sesión para finalizar tu compra';
    header('Location: /tienda-online-php/views/login.php', true, 303);
    exit;
}

$destino = 'views/carrito.php';
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !ProteccionFormulario::validarToken($_POST['tokenFormulario'] ?? null)) {
        throw new DomainException('La solicitud ha caducado. Vuelve al carrito e inténtalo de nuevo.');
    }
    $pedidoId = (new Pedido())->realizarCompra((int)$_SESSION['usuarioId']);
    // Un formulario ya consumido no puede volver a efectuar otra compra.
    unset($_SESSION['tokenFormulario']);
    $_SESSION['alerta'] = 'Tu pedido #' . $pedidoId . ' se ha realizado correctamente. ¡Gracias por tu compra!';
    $destino = 'index.php';
} catch (DomainException $error) {
    $_SESSION['alerta'] = $error->getMessage();
} catch (Throwable $error) {
    error_log('Error de pedido: ' . $error->getMessage());
    $_SESSION['alerta'] = 'No se ha podido completar el pedido. Tu carrito sigue guardado. Inténtalo de nuevo más tarde.';
}
header('Location: /tienda-online-php/' . $destino, true, 303);
exit;
