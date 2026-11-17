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

$usuarioId = filter_var($_SESSION['usuarioId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($usuarioId === false) {
    $redirigirConAlerta('views/login.php', 'Inicia sesión para acceder a tu perfil.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $redirigirConAlerta('views/perfil.php', 'Envía los cambios desde el formulario de tu perfil.');
}

// Solo se leen los campos editables; el ID procede siempre de la sesión.
$datos = [];
foreach (['nombre', 'apellidos', 'fechaNacimiento', 'pais', 'provincia', 'poblacion', 'codigoPostal'] as $campo) {
    $valor = $campo === 'codigoPostal'
        ? ($_POST['codigoPostal'] ?? $_POST['cp'] ?? '')
        : ($_POST[$campo] ?? '');
    $datos[$campo] = is_string($valor) ? trim($valor) : '';
}

if (!Validador::validarDatosPerfil($datos)) {
    $redirigirConAlerta('views/perfil.php', 'Revisa los campos del perfil: son obligatorios y deben tener un formato válido para el país seleccionado.');
}

try {
    $modeloUsuarios = new Usuario();
    $actualizado = $modeloUsuarios->actualizar($usuarioId, $datos);
} catch (PDOException $error) {
    $redirigirConAlerta('views/perfil.php', 'No se han podido guardar los cambios. Inténtalo de nuevo más tarde.');
}

if (!$actualizado) {
    $redirigirConAlerta('views/perfil.php', 'No se han podido guardar los cambios.');
}

$_SESSION['usuarioNombre'] = $datos['nombre'];
$redirigirConAlerta('views/perfil.php', 'Tu perfil se ha actualizado correctamente.');
