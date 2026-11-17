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
foreach (['controllers/authController.php', 'controllers/perfilController.php', 'utils/Validador.php'] as $archivo) {
    copy(__DIR__ . '/../' . $archivo, $directorioPruebas . '/' . $archivo);
}
file_put_contents($directorioPruebas . '/models/Usuario.php', <<<'MODELO'
<?php
class Usuario
{
    public function __construct()
    {
        $GLOBALS['llamadasModelo'][] = ['metodo' => 'constructor'];
        if (($GLOBALS['entrada']['modo'] ?? '') === 'error') {
            throw new PDOException('Detalle interno de MySQL que no debe mostrarse');
        }
    }
    public function registrar(array $datos): bool
    {
        $GLOBALS['llamadasModelo'][] = ['metodo' => 'registrar', 'datos' => $datos];
        return ($GLOBALS['entrada']['modo'] ?? '') !== 'duplicado';
    }
    public function login(string $email, string $password): array|false
    {
        $GLOBALS['llamadasModelo'][] = ['metodo' => 'login', 'email' => $email, 'password' => $password];
        return ($GLOBALS['entrada']['modo'] ?? '') === 'incorrecto' ? false : ['idUsuario' => 7, 'nombre' => 'Ana'];
    }
    public function actualizar(int $id, array $datos): bool
    {
        $GLOBALS['llamadasModelo'][] = ['metodo' => 'actualizar', 'id' => $id, 'datos' => $datos];
        return ($GLOBALS['entrada']['modo'] ?? '') !== 'fallo';
    }
}
MODELO);
file_put_contents($directorioPruebas . '/peticion.php', <<<'PETICION'
<?php
$entrada = json_decode(getenv('cineVerseTestInput'), true, 512, JSON_THROW_ON_ERROR);
$_POST = $entrada['post'] ?? [];
$_GET = $entrada['get'] ?? [];
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
        [$phpCgi, '-d', 'display_errors=1', '-d', 'log_errors=0', '-d', 'session.save_path=' . $directorioPruebas],
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
$datos = [
    'accion' => 'registro', 'nombre' => ' Ana ', 'apellidos' => ' Silva ',
    'email' => ' ana@example.com ', 'password' => ' clave de prueba ', 'dni' => ' 12345678z ',
    'fechaNacimiento' => ' 1995-04-12 ', 'pais' => ' España ', 'provincia' => ' Álava ',
    'poblacion' => ' Vitoria ', 'codigoPostal' => ' 01001 ',
];

try {
    $resultado = $peticion(['controlador' => 'authController', 'post' => $datos], 'views/login.php');
    $registro = $resultado['llamadas'][1]['datos'];
    $comprobar($registro['nombre'] === 'Ana' && $registro['dni'] === '12345678Z' && $registro['codigoPostal'] === '01001', 'No normaliza el registro.');
    $comprobar(!isset($resultado['sesion']['usuarioId']), 'El registro inicia sesión sin solicitarlo.');
    $resultado = $peticion(['controlador' => 'authController', 'post' => $datos, 'modo' => 'duplicado'], 'views/registro.php');
    $comprobar(str_contains($resultado['sesion']['alerta'], 'email o DNI'), 'No informa de duplicados.');

    foreach ([
        ['nombre' => []], ['apellidos' => ''], ['email' => 'incorrecto'], ['dni' => '12345678A'],
        ['password' => "clave\0invalida"], ['fechaNacimiento' => '2024-02-30'],
        ['fechaNacimiento' => '2999-01-01'], ['pais' => 'Francia'], ['provincia' => ''],
        ['poblacion' => []], ['codigoPostal' => '1000'], ['nombre' => str_repeat('a', 101)],
    ] as $cambio) {
        $resultado = $peticion(['controlador' => 'authController', 'post' => array_replace($datos, $cambio)], 'views/registro.php');
        $comprobar($resultado['llamadas'] === [], 'Una entrada inválida accede al modelo.');
    }
    $datosPortugal = array_replace($datos, ['pais' => 'Portugal', 'cp' => '1000001']);
    unset($datosPortugal['codigoPostal']);
    $resultado = $peticion(['controlador' => 'authController', 'post' => $datosPortugal], 'views/login.php');
    $comprobar($resultado['llamadas'][1]['datos']['codigoPostal'] === '1000001', 'No admite cp portugués.');

    $credenciales = ['accion' => 'login', 'email' => 'ana@example.com', 'password' => 'clave de prueba'];
    $resultado = $peticion(['controlador' => 'authController', 'post' => $credenciales], 'index.php');
    $comprobar($resultado['sesion']['usuarioId'] === 7 && $resultado['sesion']['usuarioNombre'] === 'Ana', 'No autentica al usuario.');
    $comprobar($resultado['sesionRenovada'], 'El login no renueva la sesión.');
    $resultado = $peticion(['controlador' => 'authController', 'post' => $credenciales, 'modo' => 'incorrecto'], 'views/login.php');
    $comprobar(!isset($resultado['sesion']['usuarioId']), 'Credenciales erróneas inician sesión.');
    foreach (['registro', 'login'] as $accion) {
        $resultado = $peticion(['controlador' => 'authController', 'metodo' => 'GET', 'get' => ['accion' => $accion]], 'views/login.php');
        $comprobar($resultado['llamadas'] === [], 'Una solicitud GET accede al modelo.');
    }
    $resultado = $peticion(['controlador' => 'authController', 'post' => ['accion' => []]], 'views/login.php');
    $comprobar($resultado['llamadas'] === [], 'Una acción de tipo array accede al modelo.');

    $resultado = $peticion([
        'controlador' => 'authController', 'metodo' => 'GET', 'get' => ['accion' => 'logout'],
        'sesion' => ['usuarioId' => 7, 'usuarioNombre' => 'Ana', 'otroDato' => 'anterior'],
    ], 'index.php');
    $comprobar(array_keys($resultado['sesion']) === ['alerta'], 'El logout conserva datos de la sesión anterior.');
    $comprobar($resultado['sesionRenovada'] && $resultado['llamadas'] === [], 'El logout no renueva sesión o toca el modelo.');

    $resultado = $peticion(['controlador' => 'perfilController', 'post' => $datos], 'views/login.php');
    $comprobar($resultado['llamadas'] === [], 'Un visitante actualiza perfiles.');
    $sesion = ['usuarioId' => 7, 'usuarioNombre' => 'Antes'];
    $resultado = $peticion(['controlador' => 'perfilController', 'sesion' => $sesion, 'metodo' => 'GET'], 'views/perfil.php');
    $comprobar($resultado['llamadas'] === [], 'GET modifica el perfil.');
    $perfil = array_replace($datos, ['usuarioId' => 99, 'id' => 99, 'email' => 'otro@example.com', 'dni' => 'otro', 'passwordHash' => 'otro']);
    $resultado = $peticion(['controlador' => 'perfilController', 'sesion' => $sesion, 'post' => $perfil], 'views/perfil.php');
    $actualizacion = $resultado['llamadas'][1];
    $comprobar($actualizacion['id'] === 7, 'Acepta un ID externo.');
    $comprobar(array_keys($actualizacion['datos']) === ['nombre', 'apellidos', 'fechaNacimiento', 'pais', 'provincia', 'poblacion', 'codigoPostal'], 'Acepta campos protegidos.');
    $comprobar($resultado['sesion']['usuarioNombre'] === 'Ana', 'No actualiza el nombre de sesión.');
    $resultado = $peticion(['controlador' => 'perfilController', 'sesion' => $sesion, 'post' => array_replace($perfil, ['codigoPostal' => 'abc'])], 'views/perfil.php');
    $comprobar($resultado['llamadas'] === [] && $resultado['sesion']['usuarioNombre'] === 'Antes', 'Un perfil inválido toca el modelo o la sesión.');
    foreach ([['authController', 'registro', 'views/registro.php'], ['authController', 'login', 'views/login.php'], ['perfilController', null, 'views/perfil.php']] as [$controlador, $accion, $destino]) {
        $post = $accion === 'login' ? $credenciales : $datos;
        $resultado = $peticion(['controlador' => $controlador, 'post' => $post, 'sesion' => $sesion, 'modo' => 'error'], $destino);
        $comprobar(!str_contains($resultado['sesion']['alerta'], 'MySQL'), 'Muestra un error interno.');
    }
    $resultado = $peticion(['controlador' => 'perfilController', 'post' => $perfil, 'sesion' => $sesion, 'modo' => 'fallo'], 'views/perfil.php');
    $comprobar($resultado['sesion']['usuarioNombre'] === 'Antes', 'Un fallo modifica el nombre de sesión.');
    echo 'OK: ' . $totalPruebas . " comprobaciones de autenticación, perfil, sesiones y redirecciones.\n";
} finally {
    $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorioPruebas, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($archivos as $archivo) {
        $archivo->isDir() ? rmdir($archivo->getPathname()) : unlink($archivo->getPathname());
    }
    rmdir($directorioPruebas);
}
