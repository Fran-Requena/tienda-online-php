<?php

$phpCgi = getenv('PHP_CGI_BINARY') ?: '/opt/lampp/bin/php-cgi';
if (!is_executable($phpCgi)) {
    throw new RuntimeException('Indica un ejecutable php-cgi mediante PHP_CGI_BINARY.');
}
$directorioPruebas = sys_get_temp_dir() . '/cineverse-vistas-' . bin2hex(random_bytes(8));
mkdir($directorioPruebas, 0700);
foreach (['views', 'views/partials', 'models', 'utils'] as $directorio) {
    mkdir($directorioPruebas . '/' . $directorio, 0700);
}
foreach (['views/login.php', 'views/registro.php', 'views/perfil.php', 'views/partials/header.php', 'views/partials/footer.php', 'utils/ProteccionFormulario.php'] as $archivo) {
    copy(__DIR__ . '/../' . $archivo, $directorioPruebas . '/' . $archivo);
}
file_put_contents($directorioPruebas . '/models/Usuario.php', <<<'MODELO'
<?php
class Usuario
{
    public function __construct() { $GLOBALS['llamadasModelo'][] = 'constructor'; }
    public function obtenerPorId(int $id): array|false
    {
        $GLOBALS['llamadasModelo'][] = $id;
        if (($GLOBALS['entrada']['modo'] ?? '') === 'error') {
            throw new PDOException('Error interno de MySQL');
        }
        return ($GLOBALS['entrada']['modo'] ?? '') === 'ausente' ? false : $GLOBALS['entrada']['usuario'];
    }
}
MODELO);
file_put_contents($directorioPruebas . '/models/Pedido.php', <<<'MODELO'
<?php
class Pedido
{
    public function obtenerPorUsuario(int $usuarioId): array
    {
        $GLOBALS['llamadasModelo'][] = ['pedidos', $usuarioId];
        if (($GLOBALS['entrada']['modo'] ?? '') === 'errorPedidos') {
            throw new PDOException('Error interno de MySQL');
        }
        return $GLOBALS['entrada']['pedidos'] ?? [];
    }
}
MODELO);
file_put_contents($directorioPruebas . '/peticion.php', <<<'PETICION'
<?php
$entrada = json_decode(getenv('cineVerseTestInput'), true, 512, JSON_THROW_ON_ERROR);
$_SERVER['SCRIPT_NAME'] = '/tienda-online-php/views/' . $entrada['vista'] . '.php';
session_id(bin2hex(random_bytes(16)));
session_start();
$_SESSION = $entrada['sesion'] ?? [];
$llamadasModelo = [];
register_shutdown_function(static function (): void {
    file_put_contents(__DIR__ . '/resultado.json', json_encode(['sesion' => $_SESSION, 'llamadas' => $GLOBALS['llamadasModelo']], JSON_THROW_ON_ERROR));
});
require __DIR__ . '/views/' . $entrada['vista'] . '.php';
PETICION);

$totalPruebas = 0;
$comprobar = static function (bool $condicion, string $mensaje) use (&$totalPruebas): void {
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
    $totalPruebas++;
};
$peticion = static function (array $entrada) use ($phpCgi, $directorioPruebas, $comprobar): array {
    $proceso = proc_open(
        [$phpCgi, '-d', 'display_errors=1', '-d', 'log_errors=0', '-d', 'session.save_path=' . $directorioPruebas],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias, null,
        ['REDIRECT_STATUS' => '1', 'REQUEST_METHOD' => 'GET', 'SCRIPT_FILENAME' => $directorioPruebas . '/peticion.php', 'cineVerseTestInput' => json_encode($entrada, JSON_THROW_ON_ERROR)]
    );
    fclose($tuberias[0]);
    $salida = stream_get_contents($tuberias[1]);
    $errores = stream_get_contents($tuberias[2]);
    fclose($tuberias[1]);
    fclose($tuberias[2]);
    $comprobar(proc_close($proceso) === 0 && $errores === '', 'Fallo CGI: ' . $salida . $errores);
    [$cabeceras, $html] = preg_split('/\r?\n\r?\n/', $salida, 2);
    $estado = json_decode(file_get_contents($directorioPruebas . '/resultado.json'), true, 512, JSON_THROW_ON_ERROR);
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    return [$cabeceras, $html, new DOMXPath($dom), $estado];
};
$usuario = [
    'idUsuario' => 7, 'nombre' => 'Ana "<img src=x onerror=alert(1)>', 'apellidos' => "O'Connor",
    'email' => 'ana@example.com', 'dni' => '12345678Z', 'fechaNacimiento' => '1995-04-12',
    'pais' => 'Portugal', 'provincia' => 'Lisboa', 'poblacion' => 'Lisboa', 'codigoPostal' => '1000001',
    'passwordHash' => 'hash-privado-que-no-debe-salir',
];
try {
    foreach (['login', 'registro'] as $vista) {
        [$cabeceras, $html, $xpath, $estado] = $peticion(['vista' => $vista]);
        $comprobar($xpath->query('//header')->length === 1 && $xpath->query('//footer')->length === 1, 'Faltan los parciales.');
        $comprobar($xpath->query('//form[@method="post" and @action="/tienda-online-php/controllers/authController.php"]')->length === 1, 'Formulario incorrecto.');
        $comprobar($xpath->query('//input[@name="accion" and @value="' . $vista . '"]')->length === 1, 'Acción incorrecta.');
        $comprobar($xpath->query('//a[@class="skip-link"]')->item(0)->getAttribute('href') === '/tienda-online-php/views/' . $vista . '.php#contenidoPrincipal', 'El salto al contenido abandona la vista.');
        $comprobar($estado['llamadas'] === [], 'El acceso público consulta usuarios.');
        if ($vista === 'registro') {
            $nombres = [];
            foreach ($xpath->query('//form//*[@name]') as $campo) {
                $nombres[] = $campo->getAttribute('name');
                if ($campo->getAttribute('type') !== 'hidden') {
                    $comprobar($campo->hasAttribute('required'), 'Falta required en ' . $campo->getAttribute('name'));
                }
            }
            sort($nombres);
            $esperados = ['accion','nombre','apellidos','email','dni','fechaNacimiento','password','pais','provincia','poblacion','codigoPostal'];
            sort($esperados);
            $comprobar($nombres === $esperados, 'Nombres del registro incorrectos.');
            $comprobar($xpath->query('//select[@name="pais"]/option[@value="España" or @value="Portugal"]')->length === 2, 'Países incorrectos.');
        } else {
            $comprobar($xpath->query('//main//a[@href="/tienda-online-php/views/registro.php"]')->length === 1, 'No hay enlace visible a registro.');
        }
        file_put_contents('/tmp/cineverse-account-' . $vista . '.html', str_replace('/tienda-online-php/assets/', 'file://' . dirname(__DIR__) . '/assets/', $html));
    }
    [$cabeceras, $html, $xpath, $estado] = $peticion(['vista' => 'perfil']);
    $comprobar(str_contains($cabeceras, 'Location: /tienda-online-php/views/login.php') && str_contains($cabeceras, 'Status: 303'), 'Perfil público sin redirección.');
    $comprobar($html === '' && $estado['llamadas'] === [], 'Un visitante recibe datos o consulta el modelo.');
    [$cabeceras, $html, $xpath, $estado] = $peticion(['vista' => 'perfil', 'sesion' => ['usuarioId' => 7], 'usuario' => $usuario]);
    $comprobar($estado['llamadas'] === ['constructor', 7, ['pedidos', 7]], 'El perfil no usa el ID de sesión.');
    $comprobar(str_contains($cabeceras, 'Cache-Control: no-store'), 'El perfil permite almacenar datos en caché.');
    $comprobar($xpath->query('//form[@method="post" and @action="/tienda-online-php/controllers/perfilController.php"]')->length === 1, 'Acción del perfil incorrecta.');
    foreach (['nombre','apellidos','email','dni','fechaNacimiento','pais','provincia','poblacion','codigoPostal'] as $campo) {
        if ($campo === 'pais') {
            $comprobar($xpath->query('//select[@name="pais"]/option[@selected]')->item(0)->getAttribute('value') === $usuario[$campo], 'No selecciona el país guardado.');
        } else {
            $comprobar($xpath->query('//input[@name="' . $campo . '"]')->item(0)->getAttribute('value') === $usuario[$campo], 'No rellena ' . $campo);
        }
    }
    foreach (['dni', 'email'] as $campo) {
        $input = $xpath->query('//input[@name="' . $campo . '"]')->item(0);
        $comprobar($input->hasAttribute('readonly') && str_contains($input->getAttribute('class'), 'bg-slate-100') && str_contains($input->getAttribute('class'), 'cursor-not-allowed'), 'Campo protegido incorrecto.');
    }
    $comprobar($xpath->query('//*[@onerror]')->length === 0 && !str_contains($html, $usuario['passwordHash']), 'Datos sin escapar o hash expuesto.');
    $comprobar($xpath->query('//section[contains(@class,"account-card")]')->length === 2, 'No hay dos tarjetas.');
    $comprobar($xpath->query('//table/thead/tr/th[@scope="col"]')->length === 4, 'Faltan las columnas de pedidos.');
    file_put_contents('/tmp/cineverse-account-perfil.html', str_replace('/tienda-online-php/assets/', 'file://' . dirname(__DIR__) . '/assets/', $html));
    [$cabeceras, $html, $xpath, $estado] = $peticion(['vista' => 'perfil', 'sesion' => ['usuarioId' => 7], 'usuario' => $usuario, 'pedidos' => [
        ['id' => 42, 'total' => '105.50', 'fechaRealizacion' => '2026-11-17 12:30:00'],
        ['id' => 9, 'total' => '20.10', 'fechaRealizacion' => '2026-10-01 08:15:00'],
    ]]);
    $comprobar($xpath->query('//table/tbody/tr')->length === 2, 'No muestra los pedidos reales.');
    $comprobar(str_contains($html, '105,50 €') && str_contains($html, '17/11/2026 12:30'), 'No formatea total y fecha.');
    $formularios = $xpath->query('//form[contains(@action,"accion=recomprar")]');
    $comprobar($formularios->length === 2 && $formularios->item(0)->getAttribute('action') === '/tienda-online-php/controllers/pedidoController.php?accion=recomprar&id=42', 'Acción de recompra incorrecta.');
    $comprobar($formularios->item(0)->getAttribute('method') === 'post' && $xpath->query('//form[contains(@action,"accion=recomprar")]/input[@name="tokenFormulario"]')->length === 2, 'Recompra sin POST y protección CSRF.');
    [$cabeceras, $html, $xpath, $estado] = $peticion(['vista' => 'perfil', 'sesion' => ['usuarioId' => 7], 'usuario' => $usuario, 'modo' => 'errorPedidos']);
    $comprobar(str_contains($html, 'No se ha podido cargar tu historial') && !str_contains($html, 'MySQL') && $xpath->query('//input[@name="nombre"]')->length === 1, 'Un fallo del historial rompe el perfil o expone errores internos.');
    foreach (['ausente' => 'views/login.php', 'error' => 'index.php'] as $modo => $destino) {
        [$cabeceras, $html, $xpath, $estado] = $peticion(['vista' => 'perfil', 'sesion' => ['usuarioId' => 7, 'usuarioNombre' => 'Ana'], 'modo' => $modo]);
        $comprobar(str_contains($cabeceras, 'Location: /tienda-online-php/' . $destino) && $html === '', 'No gestiona un perfil ausente o error de base de datos.');
        $comprobar(!empty($estado['sesion']['alerta']) && !str_contains($estado['sesion']['alerta'], 'MySQL'), 'Alerta incorrecta.');
        if ($modo === 'ausente') {
            $comprobar(!isset($estado['sesion']['usuarioId']), 'Mantiene una sesión de usuario eliminado.');
        }
    }
    echo 'OK: ' . $totalPruebas . " comprobaciones de vistas, formularios, escape y protección del perfil.\n";
} finally {
    $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorioPruebas, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($archivos as $archivo) {
        $archivo->isDir() ? rmdir($archivo->getPathname()) : unlink($archivo->getPathname());
    }
    rmdir($directorioPruebas);
}
