<?php

require_once __DIR__ . '/../models/Carrito.php';
require_once __DIR__ . '/../models/Pedido.php';
require_once __DIR__ . '/../utils/ProteccionFormulario.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$itemsCarrito = [];
$subtotal = 0.0;
$costeEnvio = 0.0;
$total = 0.0;
$pais = 'España';
$usuarioLogueado = !empty($_SESSION['usuarioId']);
$errorCarrito = '';
$hayFaltaStock = false;

try {
    $itemsCarrito = (new Carrito())->obtenerCarritoDetallado();
    $subtotal = array_sum(array_column($itemsCarrito, 'subtotal'));
    if ($usuarioLogueado) {
        $usuario = (new Usuario())->obtenerPorId((int)$_SESSION['usuarioId']);
        if (!$usuario) {
            throw new DomainException('Tu sesión ya no es válida. Inicia sesión de nuevo.');
        }
        $pais = $usuario['pais'];
    }
    if ($itemsCarrito !== []) {
        $costeEnvio = Pedido::calcularCosteEnvio($subtotal, $pais);
    }
    $total = $subtotal + $costeEnvio;
    foreach ($itemsCarrito as $item) {
        $hayFaltaStock = $hayFaltaStock || $item['cantidad'] > $item['stock'];
    }
} catch (DomainException $error) {
    $errorCarrito = $error->getMessage();
} catch (Throwable $error) {
    error_log('Error al consultar carrito: ' . $error->getMessage());
    $errorCarrito = 'No se ha podido cargar tu carrito. Recarga la página para volver a intentarlo.';
}
$tokenFormulario = ProteccionFormulario::obtenerToken();
require_once __DIR__ . '/partials/header.php';
?>

<section class="cart-page page-width" aria-labelledby="tituloCarrito">
    <a class="cart-back" href="/tienda-online-php/index.php#catalogo"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m14 6-6 6 6 6M8 12h13"/></svg> Seguir comprando</a>
    <header class="cart-heading">
        <img class="cart-heading-image" src="/tienda-online-php/assets/images/cinema-hall.webp" alt="" width="1942" height="809">
        <div class="cart-heading-copy">
            <h1 id="tituloCarrito">Tu carrito</h1>
            <p>Tus historias favoritas, un poco más cerca.</p>
        </div>
        <p class="cart-count"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 8h14l1 13H4L5 8Z"/><path d="M8 9V6a4 4 0 0 1 8 0v3"/></svg><?= htmlspecialchars((string)count($itemsCarrito)) ?> <?= htmlspecialchars((string)(count($itemsCarrito) === 1 ? 'artículo seleccionado' : 'artículos seleccionados')) ?></p>
    </header>

    <?php if ($errorCarrito !== ''): ?>
        <div class="cart-notice" role="alert">
            <p><?= htmlspecialchars((string)$errorCarrito) ?></p>
            <a class="text-link" href="/tienda-online-php/views/carrito.php">Volver a cargar el carrito</a>
        </div>
    <?php elseif ($itemsCarrito === []): ?>
        <div class="cart-empty">
            <svg width="64" height="64" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M10 16h28l3 27H7l3-27Z"/><path d="M17 19V12a7 7 0 0 1 14 0v7"/><path d="m21 25 9 5-9 5V25Z"/></svg>
            <h2>¿Qué historia te llevas?</h2>
            <p>Tu carrito está vacío. Encuentra tu próxima pieza entre nuestras películas y series favoritas.</p>
            <a class="button account-submit" href="/tienda-online-php/index.php#catalogo">Explorar la colección</a>
        </div>
    <?php else: ?>
        <?php if ($hayFaltaStock): ?>
            <p class="cart-notice" role="alert">El stock de algún artículo ha cambiado. Actualiza su cantidad o elimínalo para continuar.</p>
        <?php endif; ?>
        <div class="cart-layout">
            <section class="cart-items" aria-labelledby="tituloSeleccion">
                <div class="cart-items-heading"><h2 id="tituloSeleccion">Tu selección</h2><span><?= htmlspecialchars((string)count($itemsCarrito)) ?> <?= htmlspecialchars((string)(count($itemsCarrito) === 1 ? 'artículo' : 'artículos')) ?></span></div>
                <div class="cart-table-scroll">
                    <table class="cart-table" role="table">
                        <caption class="visually-hidden">Artículos de merchandising, precios, cantidades y subtotales</caption>
                        <thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Producto</th><th scope="col" role="columnheader">Cantidad</th><th scope="col" role="columnheader">Subtotal</th></tr></thead>
                        <tbody role="rowgroup">
                            <?php foreach ($itemsCarrito as $item): ?>
                                <?php
                                $imagenUrl = trim((string)($item['imagen'] ?? ''));
                                $imagenValida = filter_var($imagenUrl, FILTER_VALIDATE_URL)
                                    && in_array(strtolower((string)parse_url($imagenUrl, PHP_URL_SCHEME)), ['https', 'http'], true);
                                ?>
                                <tr role="row">
                                    <th scope="row" role="rowheader">
                                        <div class="cart-product">
                                            <div class="cart-product-media">
                                                <?php if ($imagenValida): ?>
                                                    <img data-product-image src="<?= htmlspecialchars((string)$imagenUrl) ?>" alt="" width="112" height="136" loading="lazy" referrerpolicy="no-referrer">
                                                <?php endif; ?>
                                                <span data-product-fallback class="cart-image-fallback" <?php if ($imagenValida): ?>hidden<?php endif; ?> aria-hidden="true"><svg width="36" height="36" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="3" y="6" width="26" height="20" rx="2"/><path d="M9 6v20M23 6v20m-9-13 6 3-6 3v-6Z"/></svg></span>
                                            </div>
                                            <div class="cart-product-details">
                                                <span class="cart-product-name"><?= htmlspecialchars((string)$item['nombre']) ?></span>
                                                <span class="cart-unit-price"><?= htmlspecialchars((string)number_format((float)$item['precio'], 2, ',', '.')) ?> € <span>/ unidad</span></span>
                                                <span class="cart-stock <?= htmlspecialchars((string)($item['cantidad'] > $item['stock'] ? 'cart-stock-warning' : '')) ?>"><?= htmlspecialchars((string)($item['stock'] > 0 ? $item['stock'] . ' disponibles' : 'Agotado')) ?></span>
                                                <form class="cart-remove-form" action="/tienda-online-php/controllers/carritoController.php" method="post">
                                                    <input type="hidden" name="tokenFormulario" value="<?= htmlspecialchars((string)$tokenFormulario) ?>">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="productoId" value="<?= htmlspecialchars((string)$item['id']) ?>">
                                                    <button class="cart-remove" type="submit" aria-label="Eliminar <?= htmlspecialchars((string)$item['nombre']) ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M6 6l1 15h10l1-15M10 10v7m4-7v7"/></svg>Eliminar</button>
                                                </form>
                                            </div>
                                        </div>
                                    </th>
                                    <td class="cart-quantity-cell" role="cell">
                                        <form class="cart-quantity" action="/tienda-online-php/controllers/carritoController.php" method="post" data-cart-quantity>
                                            <input type="hidden" name="tokenFormulario" value="<?= htmlspecialchars((string)$tokenFormulario) ?>">
                                            <input type="hidden" name="accion" value="actualizar">
                                            <input type="hidden" name="productoId" value="<?= htmlspecialchars((string)$item['id']) ?>">
                                            <label class="visually-hidden" for="cantidad<?= htmlspecialchars((string)$item['id']) ?>">Cantidad de <?= htmlspecialchars((string)$item['nombre']) ?></label>
                                            <div class="cart-stepper">
                                                <button type="button" data-quantity-step="-1" aria-label="Reducir cantidad de <?= htmlspecialchars((string)$item['nombre']) ?>" hidden>−</button>
                                                <input id="cantidad<?= htmlspecialchars((string)$item['id']) ?>" name="cantidad" type="number" min="1" max="<?= htmlspecialchars((string)max(1, $item['stock'])) ?>" value="<?= htmlspecialchars((string)$item['cantidad']) ?>" required <?php if ($item['stock'] <= 0): ?>disabled<?php endif; ?>>
                                                <button type="button" data-quantity-step="1" aria-label="Aumentar cantidad de <?= htmlspecialchars((string)$item['nombre']) ?>" hidden>+</button>
                                            </div>
                                            <button class="cart-update" type="submit" <?php if ($item['stock'] <= 0): ?>disabled<?php endif; ?>>Actualizar<span class="visually-hidden"> cantidad de <?= htmlspecialchars((string)$item['nombre']) ?></span></button>
                                        </form>
                                    </td>
                                    <td class="cart-line-total" role="cell"><span class="cart-mobile-label">Subtotal</span><?= htmlspecialchars((string)number_format((float)$item['subtotal'], 2, ',', '.')) ?> €</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="cart-help"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></svg>La disponibilidad se comprueba de nuevo al finalizar la compra.</p>
            </section>
            <aside class="cart-summary" aria-labelledby="tituloResumen">
                <div class="cart-summary-body">
                    <h2 id="tituloResumen">Resumen del pedido</h2>
                    <dl class="cart-amounts">
                        <div><dt>Subtotal</dt><dd><?= htmlspecialchars((string)number_format($subtotal, 2, ',', '.')) ?> €</dd></div>
                        <div><dt><?= htmlspecialchars((string)($usuarioLogueado ? 'Envío a ' : 'Envío estimado a ')) ?><?= htmlspecialchars((string)$pais) ?></dt><dd><?= htmlspecialchars((string)($costeEnvio === 0.0 ? 'Gratis' : number_format($costeEnvio, 2, ',', '.') . ' €')) ?></dd></div>
                    </dl>
                    <div class="cart-shipping-note"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 5h12v12H2V5Zm12 4h4l4 5v3h-8"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg><p><?= htmlspecialchars((string)($pais === 'Portugal' ? 'Envío de 20 €. Solo 10 € en pedidos superiores a 100 €.' : 'Envío de 5 €. Gratis en pedidos superiores a 100 €.')) ?></p></div>
                </div>
                <div class="cart-summary-stub">
                    <dl class="cart-total"><dt><?= htmlspecialchars((string)($usuarioLogueado ? 'Total' : 'Total estimado')) ?></dt><dd><?= htmlspecialchars((string)number_format($total, 2, ',', '.')) ?> €</dd></dl>
                    <form action="/tienda-online-php/controllers/pedidoController.php" method="post" data-confirm-purchase>
                        <input type="hidden" name="tokenFormulario" value="<?= htmlspecialchars((string)$tokenFormulario) ?>">
                        <button class="button cart-checkout" type="submit" <?php if ($hayFaltaStock): ?>disabled<?php endif; ?>>Finalizar Compra <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5"/></svg></button>
                    </form>
                    <?php if (!$usuarioLogueado): ?>
                        <p class="cart-login-note"><a href="/tienda-online-php/views/login.php">Inicia sesión para comprar.</a> El envío se ajustará al país de tu perfil.</p>
                    <?php endif; ?>
                </div>
                <div class="cart-summary-footer"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>Revisa tu selección antes de confirmar</div>
            </aside>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
