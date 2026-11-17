<?php

// Ejecuta peticiones CGI reales en un entorno aislado, con un modelo de prueba.
$phpCgi = getenv('PHP_CGI_BINARY') ?: '/opt/lampp/bin/php-cgi';
if (!is_executable($phpCgi)) {
    throw new RuntimeException('Indica un ejecutable php-cgi mediante PHP_CGI_BINARY.');
}
$directorioPruebas = sys_get_temp_dir() . '/cineverse-controladores-' . bin2hex(random_bytes(8));
mkdir($directorioPruebas, 0700);
foreach (['controllers', 'models', 'utils'] as $directorio) {
    mkdir($directorioPruebas . '/' . $directorio, 0700);
}
foreach (['controllers/carritoController.php', 'controllers/pedidoController.php', 'utils/ProteccionFormulario.php'] as $archivo) {
    copy(__DIR__ . '/../' . $archivo, $directorioPruebas . '/' . $archivo);
}
file_put_contents($directorioPruebas . '/models/Carrito.php', <<<'MODELO'
<?php
class Carrito
{
    public function agregarProducto(int $productoId, int $cantidad): int { return $this->actualizarCantidad($productoId, $cantidad); }
    public function actualizarCantidad(int $productoId, int $cantidad): int
    {
        $GLOBALS['llamadasModelo'][] = [$productoId, $cantidad];
        if (($GLOBALS['entrada']['modo'] ?? '') === 'error') { throw new PDOException('Detalle interno MySQL'); }
        if (($GLOBALS['entrada']['modo'] ?? '') === 'todosAgotados' || (($GLOBALS['entrada']['modo'] ?? '') === 'unoAgotado' && $productoId === 1)) { throw new DomainException('Producto agotado.'); }
        return min(3, $cantidad);
    }
    public function eliminarProducto(int $productoId): void { $GLOBALS['llamadasModelo'][] = [$productoId]; }
}
MODELO);
file_put_contents($directorioPruebas . '/models/Pedido.php', <<<'MODELO'
<?php
class Pedido
{
    public function perteneceAUsuario(int $pedidoId, int $usuarioId): bool
    {
        $GLOBALS['llamadasModelo'][] = ['pertenece', $pedidoId, $usuarioId];
        return $pedidoId === 42 && $usuarioId === 7 && ($GLOBALS['entrada']['modo'] ?? '') !== 'ajeno';
    }
    public function obtenerLineasPedido(int $pedidoId): array
    {
        $GLOBALS['llamadasModelo'][] = ['lineas', $pedidoId];
        return [['productoId' => 1, 'cantidad' => 5], ['productoId' => 2, 'cantidad' => 2]];
    }
    public function realizarCompra(int $usuarioId): int
    {
        $GLOBALS['llamadasModelo'][] = [$usuarioId];
        if (($GLOBALS['entrada']['modo'] ?? '') === 'stock') { throw new DomainException('No hay stock suficiente.'); }
        if (($GLOBALS['entrada']['modo'] ?? '') === 'error') { throw new PDOException('Detalle interno MySQL'); }
        return 42;
    }
}
MODELO);
file_put_contents($directorioPruebas . '/peticion.php', <<<'PETICION'
<?php
$entrada = json_decode(getenv('cineVerseTestInput'), true, 512, JSON_THROW_ON_ERROR);
$_POST = $entrada['post'] ?? [];
$_GET = $entrada['get'] ?? [];
$_COOKIE = $entrada['cookies'] ?? [];
$llamadasModelo = [];
session_id(bin2hex(random_bytes(16)));
session_start();
$_SESSION = $entrada['sesion'] ?? [];
$sessionIdAnterior = session_id();
register_shutdown_function(static function () use ($sessionIdAnterior): void {
    echo json_encode([
        'sesion' => $_SESSION,
        'sesionRenovada' => session_id() !== $sessionIdAnterior,
        'llamadas' => $GLOBALS['llamadasModelo'],
    ], JSON_THROW_ON_ERROR);
});
require __DIR__ . '/controllers/' . $entrada['controlador'] . '.php';
PETICION);

$totalPruebas = 0;
$comprobar = static function (bool $condicion, string $mensaje) use (&$totalPruebas): void {
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
    $totalPruebas++;
};
$peticion = static function (array $entrada, string $destino) use ($phpCgi, $directorioPruebas, $comprobar): array {
    $entorno = [
        'REDIRECT_STATUS' => '1', 'REQUEST_METHOD' => $entrada['metodo'] ?? 'POST',
        'SCRIPT_FILENAME' => $directorioPruebas . '/peticion.php',
        'cineVerseTestInput' => json_encode($entrada, JSON_THROW_ON_ERROR),
    ];
    $proceso = proc_open(
        [$phpCgi, '-d', 'display_errors=1', '-d', 'log_errors=0', '-d', 'error_log=' . $directorioPruebas . '/errores.log', '-d', 'session.save_path=' . $directorioPruebas],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $tuberias, null, $entorno
    );
    fclose($tuberias[0]);
    $salida = stream_get_contents($tuberias[1]);
    $errores = stream_get_contents($tuberias[2]);
    fclose($tuberias[1]);
    fclose($tuberias[2]);
    $codigoSalida = proc_close($proceso);
    $comprobar($codigoSalida === 0 && $errores === '', 'Error CGI: ' . $errores . $salida);
    [$cabeceras, $cuerpo] = preg_split('/\r?\n\r?\n/', $salida, 2);
    $comprobar(str_contains($cabeceras, 'Status: 303'), 'No usa redirección 303: ' . $salida);
    $comprobar(str_contains($cabeceras, 'Location: /tienda-online-php/' . $destino), 'Destino incorrecto: ' . $salida);
    $resultado = json_decode($cuerpo, true, 512, JSON_THROW_ON_ERROR);
    $comprobar(!empty($resultado['sesion']['alerta']), 'Falta la alerta.');
    return $resultado;
};

$sesion = ['usuarioId' => 7, 'tokenFormulario' => 'tokenDePrueba'];
$post = ['tokenFormulario' => 'tokenDePrueba', 'productoId' => 1, 'cantidad' => 2];
try {
    foreach (['agregar' => 'index.php', 'actualizar' => 'views/carrito.php', 'eliminar' => 'views/carrito.php'] as $accion => $destino) {
        $resultado = $peticion(['controlador' => 'carritoController', 'post' => $post + ['accion' => $accion], 'sesion' => $sesion], $destino);
        $comprobar($resultado['llamadas'][0][0] === 1, 'No envía el producto al modelo.');
    }
    foreach ([['tokenFormulario' => 'otro'], ['tokenFormulario' => []], ['productoId' => []], ['cantidad' => -1], ['cantidad' => '1.5'], ['accion' => []]] as $cambio) {
        $resultado = $peticion(['controlador' => 'carritoController', 'post' => array_replace($post, $cambio), 'sesion' => $sesion], isset($cambio['accion']) ? 'views/carrito.php' : 'index.php');
        $comprobar($resultado['llamadas'] === [], 'Entrada inválida accede al modelo.');
    }
    $resultado = $peticion(['controlador' => 'carritoController', 'metodo' => 'GET', 'sesion' => $sesion], 'index.php');
    $comprobar($resultado['llamadas'] === [], 'GET modifica carrito.');
    $resultado = $peticion(['controlador' => 'pedidoController'], 'views/login.php');
    $comprobar($resultado['sesion']['alerta'] === 'Debes iniciar sesión para finalizar tu compra' && $resultado['llamadas'] === [], 'Invitado puede comprar.');
    foreach ([['metodo' => 'GET'], ['post' => ['tokenFormulario' => 'incorrecto']], ['post' => ['tokenFormulario' => []]]] as $entrada) {
        $resultado = $peticion($entrada + ['controlador' => 'pedidoController', 'sesion' => $sesion], 'views/carrito.php');
        $comprobar($resultado['llamadas'] === [], 'Compra sin POST y token válido.');
    }
    $resultado = $peticion(['controlador' => 'pedidoController', 'post' => $post + ['usuarioId' => 99, 'total' => 0], 'sesion' => $sesion], 'index.php');
    $comprobar($resultado['llamadas'] === [[7]] && !isset($resultado['sesion']['tokenFormulario']), 'Usa el ID enviado o no consume token.');
    foreach (['stock', 'error'] as $modo) {
        $resultado = $peticion(['controlador' => 'pedidoController', 'post' => $post, 'sesion' => $sesion, 'modo' => $modo], 'views/carrito.php');
        $comprobar(isset($resultado['sesion']['tokenFormulario']) && !str_contains($resultado['sesion']['alerta'], 'MySQL'), 'Fallo pierde token o expone datos internos.');
    }
    $recompra = ['controlador' => 'pedidoController', 'metodo' => 'POST', 'get' => ['accion' => 'recomprar', 'id' => 42], 'post' => $post, 'sesion' => $sesion];
    $resultado = $peticion($recompra, 'views/carrito.php');
    $comprobar($resultado['llamadas'] === [['pertenece', 42, 7], ['lineas', 42], [1, 5], [2, 2]], 'No comprueba propiedad antes de recuperar y añadir las líneas.');
    $comprobar(isset($resultado['sesion']['tokenFormulario']), 'La recompra consume el token de finalizar compra.');
    foreach (['unoAgotado', 'todosAgotados'] as $modo) {
        $resultado = $peticion($recompra + ['modo' => $modo], 'views/carrito.php');
        $comprobar(count($resultado['llamadas']) === 4, 'Un agotado impide intentar el resto de líneas.');
        $comprobar(str_contains($resultado['sesion']['alerta'], $modo === 'unoAgotado' ? 'Algunos artículos' : 'Ningún producto'), 'No informa de los artículos agotados.');
    }
    $resultado = $peticion($recompra + ['modo' => 'ajeno'], 'views/perfil.php');
    $comprobar($resultado['llamadas'] === [['pertenece', 42, 7]], 'Lee líneas o cambia el carrito de un pedido ajeno.');
    foreach ([0, -1, 'texto', [], 999] as $id) {
        $resultado = $peticion(array_replace($recompra, ['get' => ['accion' => 'recomprar', 'id' => $id]]), 'views/perfil.php');
        $comprobar($resultado['llamadas'] === ($id === 999 ? [['pertenece', 999, 7]] : []), 'Un ID inválido accede a líneas.');
    }
    foreach ([['metodo' => 'GET'], ['post' => ['tokenFormulario' => 'otro']]] as $cambio) {
        $resultado = $peticion(array_replace($recompra, $cambio), 'views/perfil.php');
        $comprobar($resultado['llamadas'] === [], 'La recompra acepta GET o una petición sin token válido.');
    }
    $resultado = $peticion(array_replace($recompra, ['sesion' => []]), 'views/login.php');
    $comprobar($resultado['llamadas'] === [], 'Un invitado accede a una recompra.');
    $resultado = $peticion($recompra + ['modo' => 'error'], 'views/carrito.php');
    $comprobar(!str_contains($resultado['sesion']['alerta'], 'MySQL'), 'Un fallo de recompra expone datos internos.');
    echo 'OK: ' . $totalPruebas . " comprobaciones de controladores de carrito y compra.\n";
} finally {
    $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorioPruebas, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($archivos as $archivo) {
        $archivo->isDir() ? rmdir($archivo->getPathname()) : unlink($archivo->getPathname());
    }
    rmdir($directorioPruebas);
}
