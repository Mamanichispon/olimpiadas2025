<?php
// cart.php
// Endpoint para gestionar el carrito de compras (obtener y añadir productos)

header('Content-Type: application/json'); // Asegura que la respuesta sea JSON
header('Access-Control-Allow-Origin: *'); // Permite CORS desde cualquier origen (para desarrollo)
header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); // Métodos permitidos
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Cabeceras permitidas

// Manejar solicitudes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db_connect.php';       // Conexión a la base de datos
require_once 'auth_middleware.php'; // Middleware de autenticación (requerido para todas las operaciones del carrito)

// El ID del usuario está disponible a través de la sesión después del middleware
$user_id = $_SESSION['user_id'];

try {
    // Obtener el ID del carrito para el usuario actual
    $stmt = $pdo->prepare("SELECT id_carrito FROM Carritos WHERE id_usuario = ?");
    $stmt->execute([$user_id]);
    $cart = $stmt->fetch();

    if (!$cart) {
        // Si el usuario no tiene un carrito (lo cual no debería pasar si el registro es exitoso),
        // se podría crear uno aquí o lanzar un error. Para este diseño, se crea al registrar.
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Carrito no encontrado para el usuario.']);
        exit();
    }
    $cart_id = $cart->id_carrito;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Lógica para obtener ítems del carrito
        $stmt_items = $pdo->prepare("
            SELECT ic.id_item_carrito, ic.id_producto, ic.cantidad, ic.precio_al_agregar,
                   p.nombre AS producto_nombre, p.url_imagen AS producto_imagen, p.stock AS producto_stock
            FROM Items_Carrito ic
            JOIN Productos p ON ic.id_producto = p.id_producto
            WHERE ic.id_carrito = ?
        ");
        $stmt_items->execute([$cart_id]);
        $cart_items = $stmt_items->fetchAll();

        http_response_code(200);
        echo json_encode(['success' => true, 'cart_items' => $cart_items]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Lógica para añadir/actualizar producto en el carrito
        $input = json_decode(file_get_contents('php://input'), true);

        $product_id = $input['product_id'] ?? null;
        $quantity = $input['quantity'] ?? 1; // Cantidad por defecto 1

        if (empty($product_id) || !is_numeric($product_id) || $quantity <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos de producto inválidos.']);
            exit();
        }

        // Obtener el precio actual del producto y verificar stock
        $stmt_product = $pdo->prepare("SELECT precio, stock FROM Productos WHERE id_producto = ?");
        $stmt_product->execute([$product_id]);
        $product_info = $stmt_product->fetch();

        if (!$product_info) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado.']);
            exit();
        }

        $precio_al_agregar = $product_info->precio;
        $available_stock = $product_info->stock;

        // **NUEVA VERIFICACIÓN DE STOCK AQUÍ**
        if ($quantity > $available_stock) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No hay suficiente stock disponible para añadir esta cantidad. Stock actual: ' . $available_stock]);
            exit();
        }

        $pdo->beginTransaction(); // Iniciar transacción

        // Verificar si el producto ya está en el carrito
        $stmt_check_item = $pdo->prepare("SELECT id_item_carrito, cantidad FROM Items_Carrito WHERE id_carrito = ? AND id_producto = ?");
        $stmt_check_item->execute([$cart_id, $product_id]);
        $existing_item = $stmt_check_item->fetch();

        if ($existing_item) {
            // Si el producto ya está, actualizar la cantidad
            $new_quantity = $existing_item->cantidad + $quantity;
            // **RE-VERIFICACIÓN DE STOCK DESPUÉS DE SUMAR AL EXISTENTE**
            if ($new_quantity > $available_stock) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'La cantidad total en el carrito excede el stock disponible. Stock actual: ' . $available_stock]);
                exit();
            }
            $stmt_update_item = $pdo->prepare("UPDATE Items_Carrito SET cantidad = ?, precio_al_agregar = ? WHERE id_item_carrito = ?");
            $stmt_update_item->execute([$new_quantity, $precio_al_agregar, $existing_item->id_item_carrito]);
        } else {
            // Si el producto no está, insertarlo como un nuevo ítem
            $stmt_insert_item = $pdo->prepare("INSERT INTO Items_Carrito (id_carrito, id_producto, cantidad, precio_al_agregar) VALUES (?, ?, ?, ?)");
            $stmt_insert_item->execute([$cart_id, $product_id, $quantity, $precio_al_agregar]);
        }

        // Opcional: Actualizar la última_actualizacion del carrito
        $stmt_update_cart = $pdo->prepare("UPDATE Carritos SET ultima_actualizacion = CURRENT_TIMESTAMP WHERE id_carrito = ?");
        $stmt_update_cart->execute([$cart_id]);

        $pdo->commit(); // Confirmar la transacción

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Producto añadido/actualizado en el carrito.']);

    } else {
        http_response_code(405); // Código de estado HTTP 405 (Método no permitido)
        echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); // Revertir la transacción si algo sale mal
    }
    http_response_code(500); // Error interno del servidor
    echo json_encode(['success' => false, 'message' => 'Error al procesar el carrito: ' . $e->getMessage()]);
}
?>