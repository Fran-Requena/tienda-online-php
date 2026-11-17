<?php require_once __DIR__ . '/partials/header.php'; ?>

<div class="registration-page page-width">
    <div class="registration-heading">
        <img src="/tienda-online-php/assets/images/cinema-hall.webp" alt="" width="1942" height="809" aria-hidden="true">
        <a class="back-link" href="/tienda-online-php/index.php">Volver a la tienda</a>
        <h1 id="tituloRegistro" class="account-title">Entra en CineVerse.</h1>
        <p>Crea tu cuenta y haz sitio a tus historias favoritas.</p>
    </div>

    <section class="auth-form-panel" aria-labelledby="tituloRegistro">
        <p class="registration-required">Todos los campos son obligatorios.</p>

        <form class="account-form" action="/tienda-online-php/controllers/authController.php" method="post">
            <input type="hidden" name="accion" value="registro">
            <fieldset>
                <legend>Tus datos</legend>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="nombre">Nombre</label>
                        <input id="nombre" name="nombre" type="text" required maxlength="100" autocomplete="given-name">
                    </div>
                    <div class="form-field">
                        <label for="apellidos">Apellidos</label>
                        <input id="apellidos" name="apellidos" type="text" required maxlength="150" autocomplete="family-name">
                    </div>
                    <div class="form-field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" required maxlength="150" autocomplete="email" placeholder="tu@email.com">
                    </div>
                    <div class="form-field">
                        <label for="password">Contraseña</label>
                        <div class="password-field">
                            <input id="password" name="password" type="password" required autocomplete="new-password">
                            <button class="password-toggle" type="button" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Mostrar contraseña" hidden>Mostrar</button>
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="dni">DNI</label>
                        <input id="dni" name="dni" type="text" required maxlength="9" pattern="[0-9]{8}[a-zA-Z]" autocapitalize="characters" spellcheck="false" aria-describedby="ayudaDni">
                        <p id="ayudaDni" class="field-hint">8 dígitos y una letra, sin espacios.</p>
                    </div>
                    <div class="form-field">
                        <label for="fechaNacimiento">Fecha de nacimiento</label>
                        <input id="fechaNacimiento" name="fechaNacimiento" type="date" required max="<?= htmlspecialchars((string)date('Y-m-d')) ?>" autocomplete="bday">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Dónde encontrarte</legend>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="pais">País</label>
                        <select id="pais" name="pais" required autocomplete="country-name" data-country-field>
                            <option value="España">España</option>
                            <option value="Portugal">Portugal</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="provincia">Provincia</label>
                        <input id="provincia" name="provincia" type="text" required maxlength="100" autocomplete="address-level1">
                    </div>
                    <div class="form-field">
                        <label for="poblacion">Población</label>
                        <input id="poblacion" name="poblacion" type="text" required maxlength="100" autocomplete="address-level2">
                    </div>
                    <div class="form-field">
                        <label for="codigoPostal">Código postal</label>
                        <input id="codigoPostal" name="codigoPostal" type="text" required inputmode="numeric" maxlength="7" pattern="[0-9]{4}|[0-9]{5}|[0-9]{7}" autocomplete="postal-code" data-postal-field aria-describedby="ayudaCodigoPostal">
                        <p id="ayudaCodigoPostal" class="field-hint" data-postal-hint>España: 5 dígitos. Portugal: 4 o 7, sin guion.</p>
                    </div>
                </div>
            </fieldset>

            <button class="button account-submit" type="submit">Crear mi cuenta</button>
        </form>
        <p class="auth-switch">¿Ya tienes cuenta? <a href="/tienda-online-php/views/login.php">Iniciar sesión</a></p>
    </section>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
