<?php
// db_connect.php
// Archivo de conexión a la base de datos MySQL

// Configuración de la base de datos
define('DB_HOST', 'localhost'); // Host de la base de datos (generalmente localhost)
define('DB_NAME', 'eiro_db');   // Nombre de la base de datos (cambia esto si usas otro nombre)
define('DB_USER', 'root');      // Usuario de la base de datos (cambia esto por tu usuario)
define('DB_PASS', '');          // Contraseña del usuario de la base de datos (cambia esto por tu contraseña)

try {
    // Crear una nueva instancia de PDO para la conexión a MySQL
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);

    // Configurar el modo de error de PDO para que lance excepciones
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Configurar PDO para que devuelva los resultados como objetos
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

    // Si la conexión es exitosa, no se necesita hacer nada más aquí.
    // Este archivo será incluido en otros scripts que necesiten la conexión.

} catch (PDOException $e) {
    // Si la conexión falla, se captura la excepción y se envía un mensaje de error
    // En un entorno de producción, evita mostrar detalles del error directamente al usuario.
    http_response_code(500); // Código de estado HTTP 500 (Error interno del servidor)
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()]);
    exit(); // Termina la ejecución del script
}
?>