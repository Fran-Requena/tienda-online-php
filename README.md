# CineVerse Merch

Tienda de merchandising con PHP 8, MySQL (PDO) y Tailwind CDN.

## Base de datos

Para una instalación nueva, importa `database/tienda.sql` y configura las credenciales en `config/config.php` siguiendo `config/config.example.php`.

En una instalación anterior al paso 3, ejecuta una sola vez `database/migrations/003_subtotal_pedidos.sql` sobre la base existente. Añade el subtotal a los pedidos y lo calcula para el histórico. La migración ya se ha aplicado en este entorno local.

## Carrito y compra

El invitado guarda su carrito en sesión y en una cookie `carritoInvitado` de siete días. Al iniciar sesión se fusiona una sola copia con `lineaCarrito`, respetando el stock. Los usuarios autenticados guardan cada cambio directamente en MySQL.

`views/carrito.php` permite actualizar cantidades, eliminar artículos y finalizar la compra. Los formularios usan POST y token CSRF. El servidor obtiene precios, cantidades y país desde la base de datos; la compra bloquea el stock y registra cabecera, líneas y vaciado del carrito en una misma transacción.

| País | Subtotal hasta 100 € inclusive | Subtotal superior a 100 € |
| --- | --- | --- |
| España | 5 € | Gratis |
| Portugal | 20 € | 10 € |

El invitado ve la estimación para España y debe iniciar sesión para comprar.

## Historial y recompra

El perfil muestra los pedidos del usuario del más reciente al más antiguo, con ID, total y fecha. «Volver a comprar» envía un formulario POST protegido con CSRF a `controllers/pedidoController.php?accion=recomprar&id=...`. El servidor comprueba el propietario antes de consultar las líneas y añade al carrito los productos disponibles con las cantidades limitadas al stock actual. Los agotados se omiten sin impedir recuperar los demás; la recompra no crea un pedido ni descuenta stock hasta finalizar la compra.

## Pruebas

```sh
/opt/lampp/bin/php tests/ValidadorTest.php
/opt/lampp/bin/php tests/UsuarioTest.php
/opt/lampp/bin/php tests/ControladoresUsuarioTest.php
/opt/lampp/bin/php tests/VistasCuentaTest.php
/opt/lampp/bin/php tests/ControladoresCarritoTest.php
/opt/lampp/bin/php tests/CarritoPedidoTest.php
```

Las pruebas de carrito y pedidos requieren MySQL local y permiso para crear tablas temporales. Esas tablas solo existen en la conexión de pruebas y no modifican los datos reales de la tienda. Incluyen un fallo provocado en la segunda línea del pedido para verificar el rollback completo.
