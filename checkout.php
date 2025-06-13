<?php
// checkout.php
// Endpoint para finalizar la compra y crear un pedido

header('Content-Type: application/json'); // Asegura que la respuesta sea JSON
header('Access-Control-Allow-Origin: *'); // Permite CORS desde cualquier origen (para desarrollo)
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Métodos permitidos
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Cabeceras permitidas

// Manejar solicitudes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db_connect.php';       // Conexión a la base de datos
require_once 'auth_middleware.php'; // Middleware de autenticación

// El ID del usuario está disponible a través de la sesión
$user_id = $_SESSION['user_id'];

// Obtener los datos del cuerpo de la solicitud JSON
$input = json_decode(file_get_contents('php://input'), true);

// Datos del pago y envío (estos campos deberían venir del frontend)
// NOTA: Para esta demostración de `index.html`, estos datos no se envían
// por lo que se usarán valores por defecto o null.
$metodo_pago = $input['paymentMethod'] ?? 'Efectivo en Tienda'; // Valor por defecto para demostración
$direccion_envio = $input['shippingAddress'] ?? 'Dirección de Prueba 123';
$ciudad_envio = $input['shippingCity'] ?? 'Ciudad de Prueba';
$pais_envio = $input['shippingCountry'] ?? 'País de Prueba';
$codigo_postal_envio = $input['shippingZip'] ?? '12345';
$ultimos_cuatro_digitos = $input['lastFourDigits'] ?? null;
$marca_tarjeta = $input['cardBrand'] ?? null;

// Validar datos mínimos (ajustado para que no falle con los valores por defecto)
if (empty($metodo_pago) || empty($direccion_envio) || empty($ciudad_envio) || empty($pais_envio)) {
    // Si los datos no vienen del frontend, aún así continuamos con los valores por defecto
    // En una aplicación real, aquí se debería pedir al usuario que complete los datos.
    // http_response_code(400);
    // echo json_encode(['success' => false, 'message' => 'Faltan datos de pago o envío requeridos.']);
    // exit();
}

try {
    $pdo->beginTransaction(); // Iniciar una transacción para asegurar la atomicidad de las operaciones

    // 1. Obtener el ID del carrito del usuario
    $stmt = $pdo->prepare("SELECT id_carrito FROM Carritos WHERE id_usuario = ?");
    $stmt->execute([$user_id]);
    $cart = $stmt->fetch();

    if (!$cart) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Carrito no encontrado para el usuario.']);
        exit();
    }
    $cart_id = $cart->id_carrito;

    // 2. Obtener los ítems del carrito
    // Usamos FOR UPDATE para bloquear las filas de los productos que estamos a punto de comprar
    // Esto previene condiciones de carrera si varios usuarios intentan comprar el mismo producto simultáneamente.
    $stmt_items = $pdo->prepare("
        SELECT ic.id_item_carrito, ic.id_producto, ic.cantidad, p.precio, p.nombre AS producto_nombre, p.stock AS current_stock
        FROM Items_Carrito ic
        JOIN Productos p ON ic.id_producto = p.id_producto
        WHERE ic.id_carrito = ? FOR UPDATE
    ");
    $stmt_items->execute([$cart_id]);
    $cart_items = $stmt_items->fetchAll();

    if (empty($cart_items)) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El carrito está vacío. No se puede realizar el pedido.']);
        exit();
    }

    // Calcular el total del pedido y realizar verificación de stock final
    $total_pedido = 0;
    foreach ($cart_items as $item) {
        // **NUEVA VERIFICACIÓN DE STOCK FINAL ANTES DE PROCEDER CON LA COMPRA**
        if ($item->current_stock < $item->cantidad) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No hay suficiente stock para el producto ' . $item->producto_nombre . '. Stock disponible: ' . $item->current_stock . ' Necesario: ' . $item->cantidad]);
            exit();
        }
        $total_pedido += ($item->cantidad * $item->precio);
    }

    // 3. Insertar información de pago (si no es 'contra entrega')
    $payment_id = null;
    if ($metodo_pago !== 'contra entrega') { // Asumiendo 'contra entrega' no necesita pago inmediato
        $stmt_payment = $pdo->prepare("
            INSERT INTO Pagos (id_usuario, monto, metodo_pago, fecha_pago, estado_pago, ultimos_cuatro_digitos, marca_tarjeta)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?)
        ");
        $stmt_payment->execute([$user_id, $total_pedido, $metodo_pago, 'Exitoso', $ultimos_cuatro_digitos, $marca_tarjeta]);
        $payment_id = $pdo->lastInsertId();
    } else {
        // Para "contra entrega", el estado puede ser "Pendiente" o similar
        $stmt_payment = $pdo->prepare("
            INSERT INTO Pagos (id_usuario, monto, metodo_pago, fecha_pago, estado_pago)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?)
        ");
        $stmt_payment->execute([$user_id, $total_pedido, $metodo_pago, 'Pendiente']);
        $payment_id = $pdo->lastInsertId();
    }


    // 4. Crear el pedido
    $stmt_order = $pdo->prepare("
        INSERT INTO Pedidos (id_usuario, fecha_pedido, total, estado_pedido, direccion_envio, ciudad_envio, pais_envio, codigo_postal_envio, id_pago)
        VALUES (?, CURRENT_TIMESTAMP, ?, 'Pendiente', ?, ?, ?, ?, ?)
    ");
    $stmt_order->execute([$user_id, $total_pedido, $direccion_envio, $ciudad_envio, $pais_envio, $codigo_postal_envio, $payment_id]);
    $order_id = $pdo->lastInsertId();

    // 5. Mover los ítems del carrito al pedido y actualizar el stock
    $stmt_insert_order_item = $pdo->prepare("INSERT INTO Items_Pedido (id_pedido, id_producto, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
    $stmt_update_stock = $pdo->prepare("UPDATE Productos SET stock = stock - ? WHERE id_producto = ?");
    $stmt_delete_cart_item = $pdo->prepare("DELETE FROM Items_Carrito WHERE id_item_carrito = ?");

    foreach ($cart_items as $item) {
        // Insertar en Items_Pedido
        $stmt_insert_order_item->execute([$order_id, $item->id_producto, $item->cantidad, $item->precio]);
        // Reducir stock del producto
        $stmt_update_stock->execute([$item->cantidad, $item->id_producto]);
        // Eliminar del carrito
        $stmt_delete_cart_item->execute([$item->id_item_carrito]);
    }

    $pdo->commit(); // Confirmar todas las operaciones de la transacción

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Pedido realizado con éxito!', 'order_id' => $order_id]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); // Revertir la transacción si hay algún error
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al procesar el pedido: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocurrió un error inesperado: ' . $e->getMessage()]);
}
?>