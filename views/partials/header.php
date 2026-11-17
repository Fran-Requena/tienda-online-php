<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rutaPagina = $_SERVER['SCRIPT_NAME'] ?? '/tienda-online-php/index.php';
$rutaContenidoPrincipal = str_starts_with($rutaPagina, '/tienda-online-php/')
    ? $rutaPagina . '#contenidoPrincipal'
    : '/tienda-online-php/index.php#contenidoPrincipal';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#142b39">
    <meta name="description" content="CineVerse Merch: camisetas y merchandising para llevar contigo tus películas y series favoritas.">
    <title>CineVerse Merch | Cine, series y coleccionismo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="/tienda-online-php/assets/css/main.css" rel="stylesheet">
</head>
<body>
    <a href="<?= htmlspecialchars((string)$rutaContenidoPrincipal) ?>" class="skip-link">Saltar al contenido</a>
    <header class="site-header">
        <div class="header-inner page-width">
            <a data-notification-focus-target href="/tienda-online-php/index.php" class="brand" aria-label="CineVerse Merch, inicio">
                <svg class="brand-symbol" width="38" height="38" viewBox="0 0 40 40" fill="none" aria-hidden="true"><rect x="1" y="1" width="38" height="38" rx="11" fill="currentColor"/><path d="M27 13a10 10 0 1 0 0 14" stroke="#F2F5F6" stroke-width="3"/><path d="m18 14 10 6-10 6V14Z" fill="#E7BD75"/></svg>
                <span class="brand-name">CineVerse<span class="brand-descriptor">Merch</span></span>
            </a>
            <button data-menu-toggle type="button" aria-label="Abrir menú" aria-controls="navegacionPrincipal" aria-expanded="false" class="menu-toggle" hidden>
                <span class="hamburger-icon" aria-hidden="true"><span></span><span></span><span></span></span>
            </button>
            <nav id="navegacionPrincipal" data-menu-panel class="main-nav" aria-label="Navegación principal">
                <ul>
                    <li><a href="/tienda-online-php/index.php">Inicio</a></li>
                    <li><a href="/tienda-online-php/views/carrito.php"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M5 8h14l1 13H4L5 8Z"/><path d="M8 9V6a4 4 0 0 1 8 0v3"/></svg>Carrito</a></li>
                    <?php if (isset($_SESSION['usuarioId'])): ?>
                        <li><a href="/tienda-online-php/views/perfil.php">Mi Perfil</a></li>
                        <li><a href="/tienda-online-php/controllers/authController.php?accion=logout" class="account-link">Cerrar Sesión</a></li>
                    <?php else: ?>
                        <li><a href="/tienda-online-php/views/login.php" class="account-link">Iniciar Sesión</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="theme-control" data-theme-control hidden>
                <button type="button" class="theme-toggle" data-theme-toggle popovertarget="selectorTema" aria-label="Cambiar apariencia" aria-expanded="false" aria-controls="selectorTema" title="Cambiar apariencia">
                    <svg class="theme-icon theme-icon-system" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8m-4-4v4"/></svg>
                    <svg class="theme-icon theme-icon-light" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg>
                    <svg class="theme-icon theme-icon-dark" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20.5 14A9 9 0 0 1 10 3.5 9 9 0 1 0 20.5 14Z"/></svg>
                </button>
                <div id="selectorTema" class="theme-panel" popover="auto" aria-labelledby="tituloTema">
                    <p id="tituloTema">Apariencia</p>
                    <div role="group" aria-label="Elegir tema">
                        <button type="button" data-theme-option="system" aria-pressed="true">Sistema <span aria-hidden="true">✓</span></button>
                        <button type="button" data-theme-option="light" aria-pressed="false">Claro <span aria-hidden="true">✓</span></button>
                        <button type="button" data-theme-option="dark" aria-pressed="false">Oscuro <span aria-hidden="true">✓</span></button>
                    </div>
                </div>
            </div>
            <div class="notification-slot" aria-label="Notificaciones">
                <?php if (isset($_SESSION['alerta'])): ?>
                    <div data-notification class="notification">
                        <p role="status" aria-live="polite" aria-atomic="true" tabindex="0"><?= htmlspecialchars((string)$_SESSION['alerta']) ?></p>
                        <button data-close-notification type="button" aria-label="Cerrar notificación"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <?php unset($_SESSION['alerta']); ?>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <main id="contenidoPrincipal" tabindex="-1">
