    </main>
    <footer class="site-footer">
        <div class="footer-main page-width">
            <div class="footer-intro">
                <a href="/tienda-online-php/index.php" class="brand footer-brand"><span class="brand-name">CineVerse<span class="brand-descriptor">Merch</span></span></a>
                <p>Las buenas historias se quedan contigo.<br>Las favoritas, también se llevan.</p>
            </div>
            <nav aria-label="Explorar la tienda">
                <h2>Tu próxima pieza</h2>
                <a href="/tienda-online-php/index.php">Inicio</a>
                <a href="/tienda-online-php/index.php#catalogo">Explorar la colección</a>
            </nav>
            <nav aria-label="Tu cuenta">
                <h2>Tu espacio</h2>
                <a href="/tienda-online-php/views/carrito.php">Carrito</a>
                <?php if (isset($_SESSION['usuarioId'])): ?>
                    <a href="/tienda-online-php/views/perfil.php">Mi Perfil</a>
                <?php else: ?>
                    <a href="/tienda-online-php/views/login.php">Iniciar Sesión</a>
                <?php endif; ?>
            </nav>
        </div>
        <div class="footer-bottom page-width">
            <p>&copy; <?= htmlspecialchars((string)date('Y')) ?> CineVerse Merch. Todos los derechos reservados.</p>
            <p>Para quienes nunca pasan los créditos.</p>
        </div>
    </footer>
    <script src="/tienda-online-php/assets/js/main.js" defer></script>
</body>
</html>
