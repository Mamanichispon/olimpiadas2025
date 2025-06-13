<?php
// products.php
// Endpoint para obtener la lista de productos (viajes/experiencias)

header('Content-Type: application/json'); // Asegura que la respuesta sea JSON
header('Access-Control-Allow-Origin: *'); // Permite CORS desde cualquier origen (para desarrollo)
header('Access-Control-Allow-Methods: GET, OPTIONS'); // Métodos permitidos
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Cabeceras permitidas

// Manejar solicitudes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

try {
    // Preparar la consulta para obtener todos los productos
    // Se unen las tablas Productos y Categorias para obtener el nombre de la categoría
    $stmt = $pdo->prepare("SELECT p.id_producto, p.nombre, p.descripcion, p.precio, p.duracion_dias, p.ubicacion, p.url_imagen, p.stock, c.nombre AS categoria_nombre
                           FROM Productos p
                           LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
                           ORDER BY p.nombre ASC");
    $stmt->execute();
    $products = $stmt->fetchAll(); // Obtener todos los productos

    http_response_code(200); // Código de estado HTTP 200 (OK)
    echo json_encode(['success' => true, 'products' => $products]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener los productos: ' . $e->getMessage()]);
}
?>
