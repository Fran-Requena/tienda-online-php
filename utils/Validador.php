<?php

class Validador
{
    public static function validarNoVacio(mixed $texto): bool
    {
        return is_string($texto) && trim($texto) !== '';
    }

    public static function validarEmail(mixed $email): bool
    {
        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validarDni(mixed $dni): bool
    {
        if (!is_string($dni) || preg_match('/\A[0-9]{8}[a-zA-Z]\z/', $dni) !== 1) {
            return false;
        }

        $numeros = (int)substr($dni, 0, 8);
        $letraEsperada = substr('TRWAGMYFPDXBNJZSQVHLCKE', $numeros % 23, 1);

        return strtoupper(substr($dni, -1)) === $letraEsperada;
    }

    public static function validarPais(mixed $pais): bool
    {
        return in_array($pais, ['España', 'Portugal'], true);
    }

    /**
     * Admite 5 dígitos (España) o 4 y 7 dígitos (Portugal), sin separadores.
     * El código se recibe como texto para conservar los ceros iniciales.
     */
    public static function validarCodigoPostal(mixed $cp, mixed $pais = null): bool
    {
        if (!is_string($cp)) {
            return false;
        }

        $patron = match ($pais) {
            'España' => '/\A[0-9]{5}\z/',
            'Portugal' => '/\A(?:[0-9]{4}|[0-9]{7})\z/',
            null => '/\A(?:[0-9]{4}|[0-9]{5}|[0-9]{7})\z/',
            default => null,
        };

        return $patron !== null && preg_match($patron, $cp) === 1;
    }

    public static function validarFechaNacimiento(mixed $fechaNacimiento): bool
    {
        if (!is_string($fechaNacimiento) || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $fechaNacimiento) !== 1) {
            return false;
        }

        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaNacimiento);

        return $fecha !== false
            && substr($fechaNacimiento, 0, 4) !== '0000'
            && $fecha->format('Y-m-d') === $fechaNacimiento
            && $fecha <= new DateTimeImmutable('today');
    }

    /** Comprueba los campos editables y los límites de la tabla usuarios. */
    public static function validarDatosPerfil(array $datos): bool
    {
        $limitesCampos = [
            'nombre' => 100, 'apellidos' => 150, 'fechaNacimiento' => 10,
            'pais' => 50, 'provincia' => 100, 'poblacion' => 100, 'codigoPostal' => 10,
        ];

        foreach ($limitesCampos as $campo => $limite) {
            $valor = $datos[$campo] ?? null;
            if (!self::validarNoVacio($valor) || mb_strlen($valor, 'UTF-8') > $limite) {
                return false;
            }
        }

        return self::validarFechaNacimiento($datos['fechaNacimiento'])
            && self::validarPais($datos['pais'])
            && self::validarCodigoPostal($datos['codigoPostal'], $datos['pais']);
    }
}
