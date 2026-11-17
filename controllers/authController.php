<?php

require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../utils/Validador.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$redirigirConAlerta = static function (string $ruta, string $mensaje): void {
    $_SESSION['alerta'] = $mensaje;
    header('Location: /tienda-online-php/' . $ruta, true, 303);
    exit;
};

$leerTexto = static function (string $campo): string {
    $valor = $_POST[$campo] ?? '';
    return is_string($valor) ? trim($valor) : '';
};

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($accion === 'logout' && in_array($metodo, ['POST', 'GET'], true)) {
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);

    $redirigirConAlerta('index.php', 'Has cerrado sesión. ¡Hasta pronto!');
}

if ($metodo !== 'POST' || !in_array($accion, ['registro', 'login'], true)) {
    $redirigirConAlerta('views/login.php', 'La solicitud no es válida.');
}

if ($accion === 'registro') {
    $datos = [];
    foreach (['nombre', 'apellidos', 'email', 'password', 'dni', 'fechaNacimiento', 'pais', 'provincia', 'poblacion'] as $campo) {
        $datos[$campo] = $leerTexto($campo);
    }
    $datos['dni'] = strtoupper($datos['dni']);
    $datos['codigoPostal'] = $leerTexto(array_key_exists('codigoPostal', $_POST) ? 'codigoPostal' : 'cp');

    if (!Validador::validarDatosPerfil($datos)) {
        $redirigirConAlerta('views/registro.php', 'Completa los datos personales y revisa la fecha de nacimiento, el país y el código postal.');
    }
    if (!Validador::validarEmail($datos['email']) || mb_strlen($datos['email'], 'UTF-8') > 150) {
        $redirigirConAlerta('views/registro.php', 'Introduce un email válido de hasta 150 caracteres.');
    }
    if (!Validador::validarDni($datos['dni'])) {
        $redirigirConAlerta('views/registro.php', 'Introduce un DNI válido, con ocho dígitos y su letra correcta.');
    }
    if (!Validador::validarNoVacio($datos['password']) || str_contains($datos['password'], "\0")) {
        $redirigirConAlerta('views/registro.php', 'Introduce una contraseña válida.');
    }

    try {
        $modeloUsuarios = new Usuario();
        $registrado = $modeloUsuarios->registrar($datos);
    } catch (PDOException $error) {
        $redirigirConAlerta('views/registro.php', 'No se ha podido completar el registro. Inténtalo de nuevo más tarde.');
    }

    if (!$registrado) {
        $redirigirConAlerta('views/registro.php', 'Ya existe un usuario con ese email o DNI.');
    }

    $redirigirConAlerta('views/login.php', 'Registro completado. Ya puedes iniciar sesión.');
}

$email = $leerTexto('email');
$password = $leerTexto('password');
if (!Validador::validarEmail($email) || !Validador::validarNoVacio($password)) {
    $redirigirConAlerta('views/login.php', 'Introduce un email válido y tu contraseña.');
}

try {
    $modeloUsuarios = new Usuario();
    $usuario = $modeloUsuarios->login($email, $password);
} catch (PDOException $error) {
    $redirigirConAlerta('views/login.php', 'No se ha podido iniciar sesión. Inténtalo de nuevo más tarde.');
}

if ($usuario === false) {
    $redirigirConAlerta('views/login.php', 'El email o la contraseña no son correctos.');
}

session_regenerate_id(true);
$_SESSION['usuarioId'] = (int)$usuario['idUsuario'];
$_SESSION['usuarioNombre'] = $usuario['nombre'];
if (!empty($_SESSION['carrito']) || !empty($_COOKIE['carritoInvitado'])) {
    require_once __DIR__ . '/../models/Carrito.php';
    try {
        (new Carrito())->fusionarCarritoInvitadoAlLoguear((int)$usuario['idUsuario']);
    } catch (Throwable $error) {
        error_log('Error de fusión de carrito: ' . $error->getMessage());
        // No se pierde el carrito invitado ni se deja una autenticación a medias.
        unset($_SESSION['usuarioId'], $_SESSION['usuarioNombre']);
        $redirigirConAlerta('views/login.php', 'No se ha podido recuperar tu carrito. Sigue guardado; vuelve a iniciar sesión.');
    }
}
$redirigirConAlerta('index.php', 'Has iniciado sesión. ¡Bienvenido a CineVerse Merch!');
