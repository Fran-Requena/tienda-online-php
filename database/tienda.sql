-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS tiendaOnline 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE tiendaOnline;

-- 1. Tabla de Usuarios
CREATE TABLE usuarios (
    idUsuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    dni VARCHAR(15) NOT NULL UNIQUE,
    fechaNacimiento DATE NOT NULL,
    pais VARCHAR(50) NOT NULL,
    provincia VARCHAR(100) NOT NULL,
    poblacion VARCHAR(100) NOT NULL,
    cp VARCHAR(10) NOT NULL
) ENGINE=InnoDB;

-- 2. Tabla de Productos
CREATE TABLE productos (
    idProducto INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    precio DECIMAL(10, 2) NOT NULL,
    stock INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- 3. Tabla Línea de Carrito (Persistencia multidispositivo)
CREATE TABLE lineaCarrito (
    idUsuario INT NOT NULL,
    idProducto INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    PRIMARY KEY (idUsuario, idProducto),
    CONSTRAINT fkCarritoUsuario FOREIGN KEY (idUsuario) 
        REFERENCES usuarios(idUsuario) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fkCarritoProducto FOREIGN KEY (idProducto) 
        REFERENCES productos(idProducto) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 4. Tabla de Pedidos (Cabecera)
CREATE TABLE pedidos (
    idPedido INT AUTO_INCREMENT PRIMARY KEY,
    idUsuario INT NOT NULL,
    fechaPedido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    gastosEnvio DECIMAL(10, 2) NOT NULL,
    totalPedido DECIMAL(10, 2) NOT NULL,
    CONSTRAINT fkPedidoUsuario FOREIGN KEY (idUsuario) 
        REFERENCES usuarios(idUsuario) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 5. Tabla Línea de Pedido (Detalle histórico para facturas y "Volver a comprar")
CREATE TABLE lineaPedido (
    idPedido INT NOT NULL,
    idProducto INT NOT NULL,
    cantidad INT NOT NULL,
    precioUnitario DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (idPedido, idProducto),
    CONSTRAINT fkLineaPedido FOREIGN KEY (idPedido) 
        REFERENCES pedidos(idPedido) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fkLineaProducto FOREIGN KEY (idProducto) 
        REFERENCES productos(idProducto) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;


