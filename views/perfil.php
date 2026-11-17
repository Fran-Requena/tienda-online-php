<?php

require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Pedido.php';
require_once __DIR__ . '/../utils/ProteccionFormulario.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuarioId = filter_var($_SESSION['usuarioId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($usuarioId === false) {
    $_SESSION['alerta'] = 'Inicia sesión para acceder a tu perfil.';
    header('Location: /tienda-online-php/views/login.php', true, 303);
    exit;
}

try {
    $modeloUsuarios = new Usuario();
    $usuario = $modeloUsuarios->obtenerPorId($usuarioId);
} catch (PDOException $error) {
    $_SESSION['alerta'] = 'No se ha podido cargar tu perfil. Inténtalo de nuevo más tarde.';
    header('Location: /tienda-online-php/index.php', true, 303);
    exit;
}

if ($usuario === false) {
    unset($_SESSION['usuarioId'], $_SESSION['usuarioNombre']);
    session_regenerate_id(true);
    $_SESSION['alerta'] = 'Tu cuenta no está disponible. Inicia sesión de nuevo.';
    header('Location: /tienda-online-php/views/login.php', true, 303);
    exit;
}

$pedidos = [];
$errorPedidos = '';
try {
    $pedidos = (new Pedido())->obtenerPorUsuario($usuarioId);
} catch (PDOException $error) {
    $errorPedidos = 'No se ha podido cargar tu historial. Recarga la página para volver a intentarlo.';
}
$tokenFormulario = ProteccionFormulario::obtenerToken();

header('Cache-Control: no-store');
require_once __DIR__ . '/partials/header.php';
?>

<div class="profile-page page-width">
    <div class="profile-heading">
        <div>
            <h1 class="account-title">Tu espacio en CineVerse.</h1>
            <p class="account-description">Gestiona tus datos y consulta tus pedidos.</p>
        </div>
        <a class="back-link" href="/tienda-online-php/index.php#catalogo">Volver a la colección</a>
    </div>

    <section class="account-card" aria-labelledby="tituloDatos">
        <div class="account-card-heading">
            <h2 id="tituloDatos">Tus datos personales</h2>
            <p>Mantén tu información al día. El email y el DNI identifican tu cuenta y no se pueden modificar.</p>
        </div>
        <form class="account-form profile-form" action="/tienda-online-php/controllers/perfilController.php" method="post">
            <div class="form-grid">
                <div class="form-field">
                    <label for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" type="text" required maxlength="100" autocomplete="given-name" value="<?= htmlspecialchars((string)$usuario['nombre']) ?>">
                </div>
                <div class="form-field">
                    <label for="apellidos">Apellidos</label>
                    <input id="apellidos" name="apellidos" type="text" required maxlength="150" autocomplete="family-name" value="<?= htmlspecialchars((string)$usuario['apellidos']) ?>">
                </div>
                <div class="form-field">
                    <label for="email">Email <span class="readonly-label">No editable</span></label>
                    <input id="email" name="email" type="email" readonly class="bg-slate-100 cursor-not-allowed" autocomplete="email" value="<?= htmlspecialchars((string)$usuario['email']) ?>">
                </div>
                <div class="form-field">
                    <label for="dni">DNI <span class="readonly-label">No editable</span></label>
                    <input id="dni" name="dni" type="text" readonly class="bg-slate-100 cursor-not-allowed" value="<?= htmlspecialchars((string)$usuario['dni']) ?>">
                </div>
                <div class="form-field">
                    <label for="fechaNacimiento">Fecha de nacimiento</label>
                    <input id="fechaNacimiento" name="fechaNacimiento" type="date" required autocomplete="bday" max="<?= htmlspecialchars((string)date('Y-m-d')) ?>" value="<?= htmlspecialchars((string)$usuario['fechaNacimiento']) ?>">
                </div>
                <div class="form-field">
                    <label for="pais">País</label>
                    <select id="pais" name="pais" required autocomplete="country-name" data-country-field>
                        <option value="España" <?php if ($usuario['pais'] === 'España'): ?>selected<?php endif; ?>>España</option>
                        <option value="Portugal" <?php if ($usuario['pais'] === 'Portugal'): ?>selected<?php endif; ?>>Portugal</option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="provincia">Provincia</label>
                    <input id="provincia" name="provincia" type="text" required maxlength="100" autocomplete="address-level1" value="<?= htmlspecialchars((string)$usuario['provincia']) ?>">
                </div>
                <div class="form-field">
                    <label for="poblacion">Población</label>
                    <input id="poblacion" name="poblacion" type="text" required maxlength="100" autocomplete="address-level2" value="<?= htmlspecialchars((string)$usuario['poblacion']) ?>">
                </div>
                <div class="form-field">
                    <label for="codigoPostal">Código postal</label>
                    <input id="codigoPostal" name="codigoPostal" type="text" required inputmode="numeric" maxlength="7" pattern="[0-9]{4}|[0-9]{5}|[0-9]{7}" autocomplete="postal-code" data-postal-field aria-describedby="ayudaCodigoPostal" value="<?= htmlspecialchars((string)$usuario['codigoPostal']) ?>">
                    <p id="ayudaCodigoPostal" class="field-hint" data-postal-hint>España: 5 dígitos. Portugal: 4 o 7, sin guion.</p>
                </div>
            </div>
            <div class="profile-form-actions">
                <p>Revisa tus datos antes de guardarlos.</p>
                <button class="button account-submit" type="submit">Guardar cambios</button>
            </div>
        </form>
    </section>

    <section class="account-card orders-card" aria-labelledby="tituloPedidos">
        <div class="account-card-heading">
            <h2 id="tituloPedidos">Tu historial de pedidos</h2>
            <p>Un lugar para las piezas que ya forman parte de tu historia.</p>
        </div>
        <div class="orders-table-scroll" role="region" aria-labelledby="tituloPedidos" tabindex="0">
            <table class="orders-table">
                <caption class="visually-hidden">Historial de pedidos de tu cuenta</caption>
                <thead><tr><th scope="col">ID Pedido</th><th scope="col">Total</th><th scope="col">Fecha de realización</th><th scope="col">Acciones</th></tr></thead>
                <tbody>
                    <?php if ($errorPedidos !== ''): ?>
                        <tr><td colspan="4"><p role="alert"><?= htmlspecialchars((string)$errorPedidos) ?></p></td></tr>
                    <?php elseif ($pedidos !== []): ?>
                        <?php foreach ($pedidos as $pedido): ?>
                            <?php $fechaRealizacion = new DateTimeImmutable($pedido['fechaRealizacion']); ?>
                            <tr>
                                <th scope="row">#<?= htmlspecialchars((string)$pedido['id']) ?></th>
                                <td class="orders-total"><?= htmlspecialchars((string)number_format((float)$pedido['total'], 2, ',', '.')) ?> €</td>
                                <td><time datetime="<?= htmlspecialchars((string)$fechaRealizacion->format('Y-m-d\TH:i:s')) ?>"><?= htmlspecialchars((string)$fechaRealizacion->format('d/m/Y H:i')) ?></time></td>
                                <td>
                                    <form action="/tienda-online-php/controllers/pedidoController.php?accion=recomprar&amp;id=<?= htmlspecialchars((string)$pedido['id']) ?>" method="post">
                                        <input type="hidden" name="tokenFormulario" value="<?= htmlspecialchars((string)$tokenFormulario) ?>">
                                        <button class="button account-submit orders-rebuy" type="submit" aria-label="Volver a comprar el pedido <?= htmlspecialchars((string)$pedido['id']) ?>">Volver a comprar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="4" class="orders-empty">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><path d="M5 8h14l1 13H4L5 8Z"/><path d="M8 9V6a4 4 0 0 1 8 0v3"/></svg>
                        <h3>Tu próxima historia está por llegar.</h3>
                        <p>Aún no has realizado ningún pedido. Explora la colección para encontrar tu próxima pieza.</p>
                        <a class="text-link" href="/tienda-online-php/index.php#catalogo">Explorar la colección</a>
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
