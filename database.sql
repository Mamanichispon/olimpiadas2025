-- SQL DDL (Data Definition Language) para crear la base de datos de Eiro
-- Compatible con MySQL

-- NOTA IMPORTANTE PARA MySQL:
-- 1. Los "AUTO_INCREMENT" son la forma estándar de MySQL para IDs autoincrementables.
-- 2. "BOOLEAN" es un alias para TINYINT(1) en MySQL, y funciona correctamente.
-- 3. Las cláusulas "CHECK" son soportadas en MySQL 8.0.16+. En versiones anteriores, serán ignoradas.
-- 4. CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP es la sintaxis correcta para actualizar automáticamente la marca de tiempo.

-- Se recomienda crear una base de datos nueva antes de ejecutar estas sentencias.
-- Ejemplo: CREATE DATABASE eiro_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Luego, selecciona esa base de datos: USE eiro_db;

-- 1. Tabla: Usuarios
-- Almacena la información de los usuarios registrados.
CREATE TABLE Usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único del usuario, autoincrementable
    nombre_usuario VARCHAR(50) UNIQUE NOT NULL, -- Nombre de usuario para iniciar sesión, debe ser único
    email VARCHAR(100) UNIQUE NOT NULL,        -- Dirección de correo electrónico, debe ser única
    password_hash VARCHAR(255) NOT NULL,       -- Hash seguro de la contraseña (nunca almacenar contraseñas en texto plano)
    nombre VARCHAR(100),                       -- Nombre real del usuario
    apellido VARCHAR(100),                     -- Apellido real del usuario
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora de registro
    ultima_sesion TIMESTAMP,                   -- Última vez que el usuario inició sesión
    es_admin BOOLEAN DEFAULT FALSE,            -- Indica si el usuario tiene privilegios de administrador
    telefono VARCHAR(20)                       -- Número de teléfono del usuario
);

-- 2. Tabla: Categorias
-- Organiza los productos por tipo de viaje o experiencia.
CREATE TABLE Categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único de la categoría
    nombre VARCHAR(100) UNIQUE NOT NULL,         -- Nombre de la categoría (ej. "Aventura", "Relax"), debe ser único
    descripcion TEXT                             -- Descripción breve de la categoría
);

-- 3. Tabla: Productos (Viajes/Experiencias)
-- Contiene los detalles de los viajes o experiencias que se ofrecen.
CREATE TABLE Productos (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,  -- Identificador único del producto
    nombre VARCHAR(255) NOT NULL,                -- Nombre del viaje o experiencia
    descripcion TEXT NOT NULL,                   -- Descripción detallada del producto
    precio DECIMAL(10, 2) NOT NULL CHECK (precio > 0), -- Precio unitario, debe ser mayor que 0
    duracion_dias INT NOT NULL CHECK (duracion_dias > 0), -- Duración en días, debe ser mayor que 0
    ubicacion VARCHAR(255) NOT NULL,             -- País/Ciudad principal del viaje
    url_imagen VARCHAR(255),                     -- URL de la imagen principal del producto
    stock INT NOT NULL CHECK (stock >= 0),       -- Número de plazas disponibles, no puede ser negativo
    id_categoria INT,                            -- Clave foránea a la tabla Categorias
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha de creación del producto
    ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Última actualización
    FOREIGN KEY (id_categoria) REFERENCES Categorias(id_categoria)
);

-- 4. Tabla: Carritos
-- Representa el carrito de compras activo para cada usuario.
-- Cada usuario debe tener un único carrito activo.
CREATE TABLE Carritos (
    id_carrito INT AUTO_INCREMENT PRIMARY KEY,   -- Identificador único del carrito
    id_usuario INT UNIQUE NOT NULL,              -- Clave foránea al usuario propietario, debe ser única
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha de creación del carrito
    ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Última actualización
    FOREIGN KEY (id_usuario) REFERENCES Usuarios(id_usuario)
);

-- 5. Tabla: Items_Carrito
-- Almacena los productos individuales dentro de un carrito específico.
CREATE TABLE Items_Carrito (
    id_item_carrito INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único del ítem del carrito
    id_carrito INT NOT NULL,                     -- Clave foránea al carrito al que pertenece
    id_producto INT NOT NULL,                    -- Clave foránea al producto añadido
    cantidad INT NOT NULL CHECK (cantidad > 0),  -- Cantidad del producto, debe ser mayor que 0
    precio_al_agregar DECIMAL(10, 2) NOT NULL,   -- Precio del producto en el momento de añadirlo (para consistencia)
    fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha de adición al carrito
    FOREIGN KEY (id_carrito) REFERENCES Carritos(id_carrito),
    FOREIGN KEY (id_producto) REFERENCES Productos(id_producto)
);

-- 6. Tabla: Pedidos
-- Registra las compras completadas de los usuarios.
CREATE TABLE Pedidos (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,    -- Identificador único del pedido
    id_usuario INT NOT NULL,                     -- Clave foránea al usuario que realizó el pedido
    fecha_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora de realización del pedido
    monto_total DECIMAL(10, 2) NOT NULL CHECK (monto_total > 0), -- Suma total del pedido
    estado_pedido VARCHAR(50) NOT NULL,          -- Estado actual del pedido (ej. 'Pendiente', 'Completado', 'Cancelado')
    id_pago INT UNIQUE,                          -- Clave foránea al registro de pago asociado (puede ser NULL inicialmente)
    direccion_envio TEXT,                        -- Dirección completa de envío (si aplica)
    ciudad_envio VARCHAR(100),                   -- Ciudad de envío
    pais_envio VARCHAR(100),                     -- País de envío
    codigo_postal_envio VARCHAR(20),             -- Código postal de envío
    FOREIGN KEY (id_usuario) REFERENCES Usuarios(id_usuario)
);

-- 7. Tabla: Items_Pedido
-- Detalla los productos individuales dentro de un pedido completado.
CREATE TABLE Items_Pedido (
    id_item_pedido INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único del ítem del pedido
    id_pedido INT NOT NULL,                      -- Clave foránea al pedido al que pertenece
    id_producto INT NOT NULL,                    -- Clave foránea al producto comprado
    cantidad INT NOT NULL CHECK (cantidad > 0),  -- Cantidad del producto en el pedido
    precio_al_compra DECIMAL(10, 2) NOT NULL,    -- Precio del producto en el momento de la compra
    FOREIGN KEY (id_pedido) REFERENCES Pedidos(id_pedido),
    FOREIGN KEY (id_producto) REFERENCES Productos(id_producto)
);

-- 8. Tabla: Pagos
-- Almacena los detalles de las transacciones de pago.
CREATE TABLE Pagos (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,      -- Identificador único de la transacción de pago
    id_pedido INT UNIQUE NOT NULL,               -- Clave foránea al pedido asociado a este pago, debe ser único
    metodo_pago VARCHAR(50) NOT NULL,            -- Método de pago (ej. 'Tarjeta de Crédito', 'PayPal')
    id_transaccion_gateway VARCHAR(255) UNIQUE,  -- ID de transacción de la pasarela de pago, debe ser único
    monto DECIMAL(10, 2) NOT NULL CHECK (monto > 0), -- Monto total del pago
    fecha_pago TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora en que se procesó el pago
    estado_pago VARCHAR(50) NOT NULL,            -- Estado del pago (ej. 'Exitoso', 'Fallido', 'Reembolsado')
    ultimos_cuatro_digitos VARCHAR(4),           -- Últimos cuatro dígitos de la tarjeta (solo para referencia, NUNCA el número completo)
    marca_tarjeta VARCHAR(50),                   -- Marca de la tarjeta (ej. 'Visa', 'Mastercard')
    FOREIGN KEY (id_pedido) REFERENCES Pedidos(id_pedido)
);

-- Actualizar la referencia de Pedidos a Pagos, ahora que Pagos está creada
ALTER TABLE Pedidos
ADD CONSTRAINT fk_pedido_pago
FOREIGN KEY (id_pago) REFERENCES Pagos(id_pago);



-- 9. Tabla: Resenas (Opcional, para completar la experiencia del usuario)
-- Permite a los usuarios dejar valoraciones y comentarios sobre los viajes.
CREATE TABLE Resenas (
    id_resena INT AUTO_INCREMENT PRIMARY KEY,    -- Identificador único de la reseña
    id_producto INT NOT NULL,                    -- Clave foránea al producto reseñado
    id_usuario INT NOT NULL,                     -- Clave foránea al usuario que escribió la reseña
    calificacion INT NOT NULL CHECK (calificacion >= 1 AND calificacion <= 5), -- Calificación (1 a 5 estrellas)
    comentario TEXT,                             -- Texto del comentario de la reseña
    fecha_resena TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha de envío de la reseña
    FOREIGN KEY (id_producto) REFERENCES Productos(id_producto),
    FOREIGN KEY (id_usuario) REFERENCES Usuarios(id_usuario)
);
