<?php

require_once __DIR__ . '/../models/Carrito.php';
require_once __DIR__ . '/../models/Pedido.php';
require_once __DIR__ . '/../utils/ProteccionFormulario.php';

// Tablas TEMPORARY de MySQL: ocultan las reales solo en esta conexión.
// Nunca se modifican productos, usuarios, carritos ni pedidos de la tienda.
$conexion = Conexion::obtenerConexion();
$conexion->exec('CREATE TEMPORARY TABLE usuarios (idUsuario INT PRIMARY KEY, nombre VARCHAR(100), apellidos VARCHAR(100), email VARCHAR(150), password VARCHAR(255), dni VARCHAR(15), fechaNacimiento DATE, pais VARCHAR(50), provincia VARCHAR(100), poblacion VARCHAR(100), cp VARCHAR(10)) ENGINE=InnoDB');
$conexion->exec('CREATE TEMPORARY TABLE productos (idProducto INT PRIMARY KEY, nombre VARCHAR(150), precio DECIMAL(10,2), stock INT NOT NULL, imagen VARCHAR(500) NULL) ENGINE=InnoDB');
$conexion->exec('CREATE TEMPORARY TABLE lineaCarrito (idUsuario INT, idProducto INT, cantidad INT NOT NULL, PRIMARY KEY (idUsuario, idProducto)) ENGINE=InnoDB');
$conexion->exec('CREATE TEMPORARY TABLE pedidos (idPedido INT AUTO_INCREMENT PRIMARY KEY, idUsuario INT, fechaPedido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, subtotal DECIMAL(10,2), gastosEnvio DECIMAL(10,2), totalPedido DECIMAL(10,2)) ENGINE=InnoDB');
// El CHECK permite provocar un fallo real en una línea, después de insertar la cabecera.
$conexion->exec('CREATE TEMPORARY TABLE lineaPedido (idPedido INT, idProducto INT, cantidad INT CHECK (cantidad < 5), precioUnitario DECIMAL(10,2), PRIMARY KEY (idPedido, idProducto)) ENGINE=InnoDB');
$conexion->exec("INSERT INTO usuarios (idUsuario, pais) VALUES (1, 'España'), (2, 'Portugal')");
$conexion->exec("INSERT INTO productos VALUES (1, 'Camiseta <Alien>', 25.00, 8, NULL), (2, 'Twin Peaks', 0.10, 10, NULL), (3, 'Agotado', 10, 0, NULL)");
$carrito = new Carrito($conexion);
$pedido = new Pedido($conexion);
$totalPruebas = 0;
$comprobar = static function (bool $condicion, string $mensaje) use (&$totalPruebas): void {
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
    $totalPruebas++;
};
$esperarError = static function (callable $accion, string $tipo) use ($comprobar): void {
    try {
        $accion();
    } catch (Throwable $error) {
        $comprobar($error instanceof $tipo, 'Tipo de error inesperado: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Se esperaba un error ' . $tipo);
};
$valor = static fn (string $sql): mixed => $conexion->query($sql)->fetchColumn();
$_SESSION = [];
$_COOKIE = [];

$comprobar($carrito->agregarProducto(1, 3) === 3, 'No añade al invitado.');
$comprobar($_SESSION['carrito'] === [1 => 3], 'Sesión invitada incorrecta.');
$comprobar(json_decode($_COOKIE['carritoInvitado'], true) === [1 => 3], 'Cookie incorrecta.');
$comprobar($carrito->agregarProducto(1, 9) === 8, 'No limita el stock acumulado.');
$comprobar($carrito->actualizarCantidad(1, 2) === 2, 'Actualizar debe reemplazar, no sumar.');
$detalle = $carrito->obtenerCarritoDetallado()[0];
$comprobar($detalle['id'] === 1 && $detalle['nombre'] === 'Camiseta <Alien>' && $detalle['subtotal'] == 50 && $detalle['stock'] === 8, 'Detalle incorrecto.');
$comprobar((int)$valor('SELECT COUNT(*) FROM lineaCarrito') === 0, 'Invitado escribe en MySQL.');
$esperarError(fn () => $carrito->agregarProducto(3, 1), DomainException::class);
$esperarError(fn () => $carrito->agregarProducto(999, 1), DomainException::class);
$esperarError(fn () => $carrito->agregarProducto(1, -1), DomainException::class);
unset($_SESSION['carrito']);
$comprobar($carrito->obtenerCarritoDetallado()[0]['cantidad'] === 2, 'No recupera cookie sin sesión.');
$carrito->eliminarProducto(1);
$comprobar($carrito->obtenerCarritoDetallado() === [], 'No elimina invitado.');
$_SESSION = [];
$_COOKIE['carritoInvitado'] = '{"1":-1,"2":[],"3":true,"4":1.5,"x":3}';
$comprobar($carrito->obtenerCarritoDetallado() === [], 'Acepta cookie malformada.');
$_SESSION = [];
$_COOKIE['carritoInvitado'] = 'json inválido';
$comprobar($carrito->obtenerCarritoDetallado() === [], 'Falla cookie no JSON.');

$_SESSION = ['usuarioId' => 1];
$_COOKIE = [];
$comprobar($carrito->agregarProducto(1, 3) === 3, 'No guarda carrito autenticado.');
$comprobar($carrito->agregarProducto(1, 7) === 8, 'Upsert no limita stock.');
$comprobar($carrito->actualizarCantidad(1, 2) === 2, 'No actualiza cantidad autenticada.');
$_SESSION = ['usuarioId' => 2];
$comprobar($carrito->obtenerCarritoDetallado() === [], 'Mezcla carritos de usuarios.');
$carrito->agregarProducto(2, 1);
$carrito->eliminarProducto(1);
$comprobar((int)$valor('SELECT cantidad FROM lineaCarrito WHERE idUsuario=1 AND idProducto=1') === 2, 'Borra artículos de otro usuario.');
$_SESSION = ['usuarioId' => 1, 'carrito' => [1 => 7, 2 => 1, 3 => 10, 999 => 1]];
$_COOKIE['carritoInvitado'] = json_encode([1 => 7, 2 => 1]);
$carrito->fusionarCarritoInvitadoAlLoguear(1);
$comprobar(!isset($_SESSION['carrito'], $_COOKIE['carritoInvitado']), 'No elimina temporales tras fusionar.');
$comprobar((int)$valor('SELECT cantidad FROM lineaCarrito WHERE idUsuario=1 AND idProducto=1') === 8, 'Fusión supera stock.');
$comprobar((int)$valor('SELECT cantidad FROM lineaCarrito WHERE idUsuario=1 AND idProducto=2') === 1, 'Suma dos veces sesión y cookie.');
$_SESSION = ['usuarioId' => 1];
$_COOKIE['carritoInvitado'] = json_encode([2 => 1]);
$carrito->fusionarCarritoInvitadoAlLoguear(1);
$comprobar((int)$valor('SELECT cantidad FROM lineaCarrito WHERE idUsuario=1 AND idProducto=2') === 2, 'No fusiona desde cookie.');
$_SESSION['carrito'] = [1 => 1];
$_COOKIE['carritoInvitado'] = '{"1":1}';
$esperarError(fn () => $carrito->fusionarCarritoInvitadoAlLoguear(999), DomainException::class);
$comprobar(isset($_SESSION['carrito'], $_COOKIE['carritoInvitado']), 'Pierde temporales cuando falla la fusión.');
unset($_SESSION['carrito']);
$_COOKIE = [];

foreach ([['España', 99.99, 5], ['España', 100, 5], ['España', 100.01, 0], ['Portugal', 99.99, 20], ['Portugal', 100, 20], ['Portugal', 100.01, 10]] as [$pais, $subtotal, $envio]) {
    $comprobar(Pedido::calcularCosteEnvio($subtotal, $pais) == $envio, 'Envío incorrecto en el umbral de 100 €.');
}
$esperarError(fn () => Pedido::calcularCosteEnvio(120, 'Francia'), DomainException::class);
$carrito->actualizarCantidad(1, 4);
$carrito->actualizarCantidad(2, 1);
$pedidoId = $pedido->realizarCompra(1);
$cabecera = $conexion->query('SELECT * FROM pedidos WHERE idPedido=' . $pedidoId)->fetch();
$comprobar((float)$cabecera['subtotal'] === 100.10 && (float)$cabecera['gastosEnvio'] === 0.0 && (float)$cabecera['totalPedido'] === 100.10, 'Importes incorrectos.');
$comprobar((int)$valor('SELECT COUNT(*) FROM lineaPedido WHERE idPedido=' . $pedidoId) === 2, 'No guarda todas las líneas.');
$comprobar((int)$valor('SELECT stock FROM productos WHERE idProducto=1') === 4, 'Stock incorrecto tras compra.');
$comprobar((int)$valor('SELECT COUNT(*) FROM lineaCarrito WHERE idUsuario=1') === 0, 'No vacía el carrito comprado.');
$comprobar((int)$valor('SELECT COUNT(*) FROM lineaCarrito WHERE idUsuario=2') === 1, 'Vacía el carrito ajeno.');
$esperarError(fn () => $pedido->realizarCompra(1), DomainException::class);
$comprobar((int)$valor('SELECT COUNT(*) FROM pedidos') === 1, 'Repetir compra crea pedido vacío.');

$carrito->agregarProducto(1, 1);
$carrito->agregarProducto(2, 5);
$esperarError(fn () => $pedido->realizarCompra(1), PDOException::class);
$comprobar((int)$valor('SELECT stock FROM productos WHERE idProducto=1') === 4, 'No revierte stock tras fallar segunda línea.');
$comprobar((int)$valor('SELECT COUNT(*) FROM pedidos') === 1, 'No revierte cabecera.');
$comprobar((int)$valor('SELECT COUNT(*) FROM lineaPedido') === 2, 'No revierte primera línea.');
$comprobar((int)$valor('SELECT COUNT(*) FROM lineaCarrito WHERE idUsuario=1') === 2, 'Pierde carrito después del fallo.');
$conexion->exec('UPDATE productos SET stock=0 WHERE idProducto=1');
$esperarError(fn () => $pedido->realizarCompra(1), DomainException::class);
$comprobar((int)$valor('SELECT COUNT(*) FROM pedidos') === 1, 'Stock insuficiente crea pedido.');
$esperarError(fn () => $pedido->realizarCompra(2), DomainException::class);
$_SESSION = [];
$esperarError(fn () => $pedido->realizarCompra(1), DomainException::class);

$_SESSION = ['usuarioId' => 1];
$conexion->exec('UPDATE productos SET stock=4 WHERE idProducto=1');
$carrito->actualizarCantidad(1, 4);
$carrito->eliminarProducto(2);
$pedidoEspana = $pedido->realizarCompra(1);
$comprobar((float)$valor('SELECT totalPedido FROM pedidos WHERE idPedido=' . $pedidoEspana) === 105.0, 'Pedido de exactamente 100 € debe pagar 5 € de envío.');
$_SESSION = ['usuarioId' => 2];
$pedidoPortugal = $pedido->realizarCompra(2);
$comprobar((float)$valor('SELECT gastosEnvio FROM pedidos WHERE idPedido=' . $pedidoPortugal) === 20.0, 'No utiliza el país del perfil al comprar.');
$comprobar((float)$valor('SELECT totalPedido FROM pedidos WHERE idPedido=' . $pedidoPortugal) === 20.10, 'Total portugués incorrecto.');


$historialPortugal = $pedido->obtenerPorUsuario(2);
$comprobar(count($historialPortugal) === 1 && (int)$historialPortugal[0]['id'] === $pedidoPortugal, 'El historial incluye pedidos de otro usuario.');
$comprobar($pedido->obtenerPorUsuario(999) === [], 'Usuario sin pedidos recibe historial.');
$conexion->exec("UPDATE pedidos SET fechaPedido='2026-01-01 12:30:00'");
$conexion->exec("UPDATE pedidos SET fechaPedido='2026-02-01 12:30:00' WHERE idPedido=" . $pedidoId);
$historialEspana = $pedido->obtenerPorUsuario(1);
$comprobar(array_map('intval', array_column($historialEspana, 'id')) === [$pedidoId, $pedidoEspana], 'El historial no ordena por fecha descendente.');
$comprobar((float)$historialEspana[0]['total'] === 100.10 && $historialEspana[0]['fechaRealizacion'] === '2026-02-01 12:30:00', 'Faltan los importes o fecha del histórico.');
$conexion->exec("UPDATE pedidos SET fechaPedido='2026-01-01 12:30:00'");
$comprobar(array_map('intval', array_column($pedido->obtenerPorUsuario(1), 'id')) === [$pedidoEspana, $pedidoId], 'Fechas iguales no ordenan por ID descendente.');
$comprobar($pedido->perteneceAUsuario($pedidoId, 1) && !$pedido->perteneceAUsuario($pedidoId, 2) && !$pedido->perteneceAUsuario(999, 1), 'Falla la verificación del propietario.');
$_SESSION = ['usuarioId' => 1];
$lineas = $pedido->obtenerLineasPedido($pedidoId);
$comprobar(count($lineas) === 2 && (int)$lineas[0]['productoId'] === 1 && (int)$lineas[0]['cantidad'] === 4 && (float)$lineas[0]['precioUnitario'] === 25.0, 'Líneas históricas incorrectas.');
$comprobar($pedido->obtenerLineasPedido($pedidoPortugal) === [] && $pedido->obtenerLineasPedido(999) === [], 'Consulta líneas de otro usuario o inexistentes.');
$_SESSION = [];
$comprobar($pedido->obtenerLineasPedido($pedidoId) === [], 'Un invitado puede consultar líneas de pedido.');

$_SESSION = ['usuarioId' => 1];
$conexion->exec('UPDATE productos SET stock=2 WHERE idProducto=1');
$carrito->agregarProducto(1, 1);
foreach ($pedido->obtenerLineasPedido($pedidoId) as $linea) {
    $carrito->agregarProducto((int)$linea['productoId'], (int)$linea['cantidad']);
}
$comprobar((int)$valor('SELECT cantidad FROM lineaCarrito WHERE idUsuario=1 AND idProducto=1') === 2, 'La recompra no suma al carrito existente o supera stock.');
$comprobar((int)$valor('SELECT cantidad FROM lineaCarrito WHERE idUsuario=1 AND idProducto=2') === 1, 'No recupera todas las líneas disponibles.');
$comprobar((int)$valor('SELECT stock FROM productos WHERE idProducto=1') === 2 && (int)$valor('SELECT COUNT(*) FROM pedidos') === 3, 'La recompra descuenta stock o crea pedidos antes de finalizar.');
$carrito->vaciarCarrito(1);
$conexion->exec('UPDATE productos SET stock=0 WHERE idProducto=1');
foreach ($pedido->obtenerLineasPedido($pedidoId) as $linea) {
    try {
        $carrito->agregarProducto((int)$linea['productoId'], (int)$linea['cantidad']);
    } catch (DomainException $error) {
        // Igual que el controlador, continúa con las líneas disponibles.
    }
}
$comprobar(count($carrito->obtenerCarritoDetallado()) === 1 && $carrito->obtenerCarritoDetallado()[0]['id'] === 2, 'Un producto agotado impide recomprar los demás.');



$token = ProteccionFormulario::obtenerToken();
$comprobar(ProteccionFormulario::validarToken($token), 'Token correcto rechazado.');
$comprobar(!ProteccionFormulario::validarToken('otro') && !ProteccionFormulario::validarToken([]), 'Token incorrecto aceptado.');

echo 'OK: ' . $totalPruebas . " comprobaciones de carrito, fusión, envíos y transacciones en MySQL aislado.\n";
