<?php require_once __DIR__ . '/partials/header.php'; ?>

<div class="auth-shell auth-login page-width">
    <aside class="auth-scene" aria-labelledby="tituloEscenaLogin">
        <img src="/tienda-online-php/assets/images/cinema-hall.webp" alt="" width="1942" height="809" aria-hidden="true">
        <div class="auth-scene-copy">
            <span class="auth-star" aria-hidden="true">✦</span>
            <h2 id="tituloEscenaLogin">Las buenas historias<br> siempre te esperan.</h2>
            <p>Vuelve a tus películas, tus series y esas piezas que hacen que la historia continúe.</p>
        </div>
        <p class="auth-scene-caption">CineVerse Merch <span>Para quienes nunca pasan los créditos.</span></p>
    </aside>

    <section class="auth-form-panel" aria-labelledby="tituloLogin">
        <a class="back-link" href="/tienda-online-php/index.php">Volver a la tienda</a>
        <h1 id="tituloLogin" class="account-title">Tu butaca te espera.</h1>
        <p class="account-description">Inicia sesión para entrar en tu espacio de CineVerse.</p>

        <form class="account-form" action="/tienda-online-php/controllers/authController.php" method="post">
            <input type="hidden" name="accion" value="login">
            <div class="form-field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" required maxlength="150" autocomplete="email" placeholder="tu@email.com">
            </div>
            <div class="form-field">
                <label for="password">Contraseña</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" required autocomplete="current-password">
                    <button class="password-toggle" type="button" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Mostrar contraseña" hidden>Mostrar</button>
                </div>
            </div>
            <button class="button account-submit" type="submit">Iniciar sesión</button>
        </form>

        <p class="auth-switch">¿Es tu primera visita? <a href="/tienda-online-php/views/registro.php">Crear una cuenta</a></p>
    </section>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
