<?php

require_once __DIR__ . '/../models/Usuario.php';

// Base de datos aislada: nunca se accede a los usuarios reales de MySQL.
$conexion = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$conexion->exec('CREATE TABLE usuarios (
    idUsuario INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL, apellidos TEXT NOT NULL, email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL, dni TEXT NOT NULL UNIQUE, fechaNacimiento TEXT NOT NULL,
    pais TEXT NOT NULL, provincia TEXT NOT NULL, poblacion TEXT NOT NULL, cp TEXT NOT NULL
)');

$modeloUsuarios = new Usuario($conexion);
$totalPruebas = 0;
$comprobar = function (bool $condicion, string $mensaje) use (&$totalPruebas): void {
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
    $totalPruebas++;
};

$datos = [
    'nombre' => 'Ana', 'apellidos' => "O'Connor", 'email' => 'ana@example.com',
    'password' => 'Una contraseña de prueba 123!', 'dni' => '12345678Z',
    'fechaNacimiento' => '1995-04-12', 'pais' => 'España',
    'provincia' => 'Álava', 'poblacion' => 'Vitoria', 'codigoPostal' => '01001',
];

$comprobar($modeloUsuarios->registrar($datos), 'No se registra el usuario.');
$usuario = $modeloUsuarios->login($datos['email'], $datos['password']);
$comprobar(is_array($usuario), 'Falla el acceso con credenciales válidas.');
$id = (int)$usuario['idUsuario'];
$comprobar($usuario['passwordHash'] !== $datos['password'], 'Se ha guardado la contraseña en claro.');
$comprobar(password_verify($datos['password'], $usuario['passwordHash']), 'El hash no verifica.');
$comprobar($usuario['apellidos'] === "O'Connor", 'No se conservan las comillas.');
$comprobar($usuario['codigoPostal'] === '01001', 'Se pierden ceros del código postal.');
$comprobar($modeloUsuarios->obtenerPorId($id) === $usuario, 'El perfil no devuelve todos los datos.');
$comprobar($modeloUsuarios->obtenerPorId(999) === false, 'Devuelve un perfil inexistente.');
$comprobar($modeloUsuarios->login($datos['email'], 'incorrecta') === false, 'Acepta una contraseña incorrecta.');
$comprobar($modeloUsuarios->login('nadie@example.com', $datos['password']) === false, 'Acepta un email inexistente.');
$comprobar($modeloUsuarios->login("' OR 1=1 --", $datos['password']) === false, 'Permite inyección SQL en login.');
$comprobar(!$modeloUsuarios->registrar($datos), 'Acepta un duplicado completo.');
$comprobar(!$modeloUsuarios->registrar(array_replace($datos, ['dni' => '00000000T'])), 'Acepta email duplicado.');
$comprobar(!$modeloUsuarios->registrar(array_replace($datos, ['email' => 'otra@example.com'])), 'Acepta DNI duplicado.');
$comprobar((int)$conexion->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 1, 'Los duplicados crean filas.');

$perfil = [
    'nombre' => "Ana'; DELETE FROM usuarios; --", 'apellidos' => 'Silva',
    'fechaNacimiento' => '1996-05-23', 'pais' => 'Portugal', 'provincia' => 'Lisboa',
    'poblacion' => 'Lisboa', 'codigoPostal' => '1000001',
    'email' => 'cambiado@example.com', 'dni' => '00000000T', 'password' => 'cambiada',
    'passwordHash' => 'hash falso',
];
$comprobar($modeloUsuarios->actualizar($id, $perfil), 'No actualiza el perfil.');
$actualizado = $modeloUsuarios->obtenerPorId($id);
foreach (['nombre', 'apellidos', 'fechaNacimiento', 'pais', 'provincia', 'poblacion', 'codigoPostal'] as $campo) {
    $comprobar($actualizado[$campo] === $perfil[$campo], 'No actualiza ' . $campo);
}
foreach (['email', 'dni', 'passwordHash'] as $campo) {
    $comprobar($actualizado[$campo] === $usuario[$campo], 'Modifica el campo protegido ' . $campo);
}
$comprobar($modeloUsuarios->login($datos['email'], $datos['password']) !== false, 'La actualización cambia las credenciales.');
$comprobar((int)$conexion->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 1, 'Se ejecuta SQL aportado como nombre.');

$otrosDatos = array_replace($datos, ['email' => 'otra@example.com', 'dni' => '00000000T']);
unset($otrosDatos['codigoPostal']);
$otrosDatos['cp'] = '0001';
$comprobar($modeloUsuarios->registrar($otrosDatos), 'No admite cp como clave de entrada.');
$otroUsuario = $modeloUsuarios->login($otrosDatos['email'], $otrosDatos['password']);
$comprobar($otroUsuario['codigoPostal'] === '0001', 'No conserva el formato de cp.');
$otroPerfil = $perfil;
unset($otroPerfil['codigoPostal']);
$otroPerfil['cp'] = '0000001';
$modeloUsuarios->actualizar((int)$otroUsuario['idUsuario'], $otroPerfil);
$comprobar($modeloUsuarios->obtenerPorId((int)$otroUsuario['idUsuario'])['codigoPostal'] === '0000001', 'No actualiza cp.');
$comprobar($modeloUsuarios->obtenerPorId($id) === $actualizado, 'La actualización afecta a otro usuario.');

echo 'OK: ' . $totalPruebas . " comprobaciones del modelo Usuario.\n";
