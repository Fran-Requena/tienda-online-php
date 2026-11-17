<?php

class ProteccionFormulario
{
    public static function obtenerToken(): string
    {
        if (empty($_SESSION['tokenFormulario'])) {
            $_SESSION['tokenFormulario'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['tokenFormulario'];
    }

    public static function validarToken(mixed $token): bool
    {
        return is_string($token) && isset($_SESSION['tokenFormulario'])
            && hash_equals($_SESSION['tokenFormulario'], $token);
    }
}
