<?php
// register.php
// Endpoint para el registro de nuevos usuarios

header('Content-Type: application/json'); // Asegura que la respuesta sea JSON
header('Access-Control-Allow-Origin: *'); // Permite CORS desde cualquier origen (para desarrollo, en producción restringir)
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Métodos permitidos
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Cabeceras permitidas

// Manejar solicitudes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

// Obtener los datos del cuerpo de la solicitud JSON
$input = json_decode(file_get_contents('php://input'), true);

$username = $input['username'] ?? '';
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

// Validar que los campos no estén vacíos
if (empty($username) || empty($email) || empty($password)) {
    http_response_code(400); // Código de estado HTTP 400 (Solicitud incorrecta)
    echo json_encode(['success' => false, 'message' => 'Por favor, completa todos los campos: nombre de usuario, email y contraseña.']);
    exit();
}

// Validar formato de email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Formato de email inválido.']);
    exit();
}

// Hashear la contraseña de forma segura
$password_hash = password_hash($password, PASSWORD_BCRYPT);

try {
    // Iniciar una transacción para asegurar la atomicidad de la operación
    $pdo->beginTransaction();

    // 1. Verificar si el nombre de usuario o email ya existen
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Usuarios WHERE nombre_usuario = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetchColumn() > 0) {
        $pdo->rollBack(); // Deshacer la transacción
        http_response_code(409); // Código de estado HTTP 409 (Conflicto)
        echo json_encode(['success' => false, 'message' => 'El nombre de usuario o el email ya están en uso.']);
        exit();
    }

    // 2. Insertar el nuevo usuario en la tabla Usuarios
    $stmt = $pdo->prepare("INSERT INTO Usuarios (nombre_usuario, email, password_hash) VALUES (?, ?, ?)");
    $stmt->execute([$username, $email, $password_hash]);
    $user_id = $pdo->lastInsertId(); // Obtener el ID del usuario recién insertado

    // 3. Crear un carrito vacío para el nuevo usuario
    $stmt = $pdo->prepare("INSERT INTO Carritos (id_usuario) VALUES (?)");
    $stmt->execute([$user_id]);

    $pdo->commit(); // Confirmar la transacción

    http_response_code(201); // Código de estado HTTP 201 (Creado)
    echo json_encode(['success' => true, 'message' => 'Registro exitoso. Ahora puedes iniciar sesión.']);

} catch (PDOException $e) {
    $pdo->rollBack(); // Si algo falla, deshacer la transacción
    http_response_code(500); // Error interno del servidor
    echo json_encode(['success' => false, 'message' => 'Error al registrar el usuario: ' . $e->getMessage()]);
}
?>
