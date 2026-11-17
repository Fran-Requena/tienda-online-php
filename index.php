<?php
require_once __DIR__ . '/models/Producto.php';
require_once __DIR__ . '/utils/ProteccionFormulario.php';

$modeloProductos = new Producto();
$listaProductos = $modeloProductos->obtenerTodos();

require_once __DIR__ . '/views/partials/header.php';
?>

<div class="hero-wrap page-width">
    <section class="cinema-hero" aria-labelledby="tituloHero">
        <img class="hero-image" src="/tienda-online-php/assets/images/cinema-hall.webp" alt="" aria-hidden="true" width="1942" height="809" fetchpriority="high">
        <div class="hero-content">
            <p class="hero-kicker"><span aria-hidden="true">✦</span> Fuera de la pantalla. Dentro de tu vida.</p>
            <h1 id="tituloHero">El cine se vive.<br>También se lleva.</h1>
            <p class="hero-description">Camisetas y piezas de tus películas y series favoritas. Para que la historia siga cuando acaben los créditos.</p>
            <a class="button hero-button" href="/tienda-online-php/index.php#catalogo">Explorar la colección <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 4v16m-6-6 6 6 6-6"/></svg></a>
        </div>
        <div class="cinema-ticket" aria-hidden="true">
            <span class="ticket-heading">CineVerse</span>
            <span class="ticket-text">Una historia.<br>Mil formas de vivirla.</span>
            <span class="ticket-stub">Admit one <span>✦</span> Fan de por vida</span>
        </div>
        <div class="hero-caption" aria-hidden="true"><span class="projection-dot"></span> La función continúa contigo</div>
    </section>
    <div class="collection-note"><span>Tu película de culto. Tu serie de siempre.</span><span>Tu próxima camiseta favorita.</span></div>
</div>

<section id="catalogo" class="catalog-section page-width" aria-labelledby="tituloCatalogo">
    <div class="catalog-heading">
        <div>
            <h2 id="tituloCatalogo">Historias para llevar.</h2>
            <p>Encuentra un lugar para tus universos favoritos.</p>
        </div>
        <p class="product-count"><?= htmlspecialchars((string)count($listaProductos)) ?> <?= htmlspecialchars((string)(count($listaProductos) === 1 ? 'pieza en la colección' : 'piezas en la colección')) ?></p>
    </div>
    <?php if (empty($listaProductos)): ?>
        <div class="empty-catalog">
            <h3>El catálogo estará disponible pronto</h3>
            <p>Estamos preparando la colección. Vuelve más adelante para encontrar tu próxima pieza.</p>
        </div>
    <?php else: ?>
        <div class="product-grid grid">
            <?php foreach ($listaProductos as $producto): ?>
                <article class="product-card">
                    <?php
                    $imagenUrl = trim((string)($producto['imagen'] ?? ''));
                    $imagenValida = filter_var($imagenUrl, FILTER_VALIDATE_URL)
                        && in_array(strtolower((string)parse_url($imagenUrl, PHP_URL_SCHEME)), ['https', 'http'], true);
                    ?>
                    <div class="product-media">
                        <?php if ($imagenValida): ?>
                            <img data-product-image class="product-image" src="<?= htmlspecialchars((string)$imagenUrl) ?>" alt="<?= htmlspecialchars((string)$producto['nombre']) ?>" width="800" height="600" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                        <?php endif; ?>
                        <div data-product-fallback class="product-image-fallback" <?php if ($imagenValida): ?>hidden<?php endif; ?>>
                            <svg width="42" height="42" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="6" y="9" width="36" height="30" rx="3"/><path d="M14 9v30m20-30v30M6 18h8m-8 12h8m20-12h8m-8 12h8"/><path d="m21 18 9 6-9 6V18Z"/></svg>
                            <span>Imagen no disponible</span>
                        </div>
                    </div>
                    <div class="product-copy">
                        <div class="product-availability">
                            <?php if ($producto['stock'] > 0): ?>
                                <span class="stock-label"><span aria-hidden="true"></span>Disponible</span>
                            <?php else: ?>
                                <span class="sold-out-label">Agotado</span>
                            <?php endif; ?>
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><path d="m14 3 7 7-11 11-7-7V3h11Z"/><circle cx="8" cy="8" r="1.5"/></svg>
                        </div>
                        <h3><?= htmlspecialchars((string)$producto['nombre']) ?></h3>
                        <p class="product-description"><?= htmlspecialchars((string)$producto['descripcion']) ?></p>
                    </div>
                    <div class="product-purchase">
                        <p class="product-price tabular-nums"><?= htmlspecialchars((string)number_format((float)$producto['precio'], 2, ',', '.')) ?> <span>€</span></p>
                        <?php if ($producto['stock'] > 0): ?>
                            <form action="/tienda-online-php/controllers/carritoController.php" method="post" class="purchase-form">
                                <input type="hidden" name="tokenFormulario" value="<?= htmlspecialchars((string)ProteccionFormulario::obtenerToken()) ?>">
                                <input type="hidden" name="productoId" value="<?= htmlspecialchars((string)$producto['idProducto']) ?>">
                                <label class="quantity-label">Cantidad
                                    <select name="cantidad" required>
                                        <?php for ($cantidad = 1; $cantidad <= (int)$producto['stock']; $cantidad++): ?>
                                            <option value="<?= htmlspecialchars((string)$cantidad) ?>"><?= htmlspecialchars((string)$cantidad) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </label>
                                <button class="button add-to-cart" type="submit">Añadir al carrito<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M5 8h14l1 13H4L5 8Z"/><path d="M8 9V6a4 4 0 0 1 8 0v3"/></svg></button>
                            </form>
                        <?php else: ?>
                            <div class="sold-out"><span>Sin stock / Agotado</span><p>Esta pieza no está disponible ahora.</p></div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
