<?php

require_once __DIR__ . '/../config/Conexion.php';

class Usuario
{
    private PDO $conexion;

    public function __construct(?PDO $conexion = null)
    {
        $this->conexion = $conexion ?? Conexion::obtenerConexion();
    }

    /**
     * Registra un usuario y devuelve false si su email o DNI ya existen.
     * Recibe nombre, apellidos, email, password, dni, fechaNacimiento,
     * pais, provincia, poblacion y codigoPostal (también admite cp).
     */
    public function registrar(array $datos): bool
    {
        $sentencia = $this->conexion->prepare(
            'SELECT idUsuario FROM usuarios WHERE email = :email OR dni = :dni LIMIT 1'
        );
        $sentencia->execute([
            ':email' => $datos['email'],
            ':dni' => $datos['dni'],
        ]);

        if ($sentencia->fetch(PDO::FETCH_ASSOC) !== false) {
            return false;
        }

        $passwordHash = password_hash($datos['password'], PASSWORD_DEFAULT);
        $sentencia = $this->conexion->prepare(
            'INSERT INTO usuarios
                (nombre, apellidos, email, password, dni, fechaNacimiento, pais, provincia, poblacion, cp)
             VALUES
                (:nombre, :apellidos, :email, :passwordHash, :dni, :fechaNacimiento, :pais, :provincia, :poblacion, :codigoPostal)'
        );

        try {
            return $sentencia->execute([
                ':nombre' => $datos['nombre'],
                ':apellidos' => $datos['apellidos'],
                ':email' => $datos['email'],
                ':passwordHash' => $passwordHash,
                ':dni' => $datos['dni'],
                ':fechaNacimiento' => $datos['fechaNacimiento'],
                ':pais' => $datos['pais'],
                ':provincia' => $datos['provincia'],
                ':poblacion' => $datos['poblacion'],
                ':codigoPostal' => $datos['codigoPostal'] ?? $datos['cp'],
            ]);
        } catch (PDOException $error) {
            // El índice UNIQUE también protege frente a dos registros simultáneos.
            if ($error->getCode() === '23000' && (int)($error->errorInfo[1] ?? 0) === 1062) {
                return false;
            }

            throw $error;
        }
    }

    public function login(string $email, string $password): array|false
    {
        $sentencia = $this->conexion->prepare(
            'SELECT idUsuario, nombre, apellidos, email, password AS passwordHash, dni,
                    fechaNacimiento, pais, provincia, poblacion, cp AS codigoPostal
             FROM usuarios WHERE email = :email LIMIT 1'
        );
        $sentencia->execute([':email' => $email]);
        $usuario = $sentencia->fetch(PDO::FETCH_ASSOC);

        if ($usuario === false || !password_verify($password, $usuario['passwordHash'])) {
            return false;
        }

        return $usuario;
    }

    public function obtenerPorId(int $id): array|false
    {
        $sentencia = $this->conexion->prepare(
            'SELECT idUsuario, nombre, apellidos, email, password AS passwordHash, dni,
                    fechaNacimiento, pais, provincia, poblacion, cp AS codigoPostal
             FROM usuarios WHERE idUsuario = :id'
        );
        $sentencia->execute([':id' => $id]);

        return $sentencia->fetch(PDO::FETCH_ASSOC);
    }

    /** Actualiza el perfil sin modificar email, DNI ni contraseña. */
    public function actualizar(int $id, array $datos): bool
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE usuarios
             SET nombre = :nombre, apellidos = :apellidos, fechaNacimiento = :fechaNacimiento,
                 pais = :pais, provincia = :provincia, poblacion = :poblacion, cp = :codigoPostal
             WHERE idUsuario = :id'
        );

        return $sentencia->execute([
            ':nombre' => $datos['nombre'],
            ':apellidos' => $datos['apellidos'],
            ':fechaNacimiento' => $datos['fechaNacimiento'],
            ':pais' => $datos['pais'],
            ':provincia' => $datos['provincia'],
            ':poblacion' => $datos['poblacion'],
            ':codigoPostal' => $datos['codigoPostal'] ?? $datos['cp'],
            ':id' => $id,
        ]);
    }
}
