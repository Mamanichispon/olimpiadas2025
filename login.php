<?php
// login.php
// Endpoint para el inicio de sesión de usuarios

header('Content-Type: application/json'); // Asegura que la respuesta sea JSON
header('Access-Control-Allow-Origin: *'); // Permite CORS desde cualquier origen (para desarrollo)
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Métodos permitidos
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Cabeceras permitidas

// Manejar solicitudes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

// Iniciar la sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Obtener los datos del cuerpo de la solicitud JSON
$input = json_decode(file_get_contents('php://input'), true);

$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

// Validar que los campos no estén vacíos
if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Por favor, ingresa tu nombre de usuario y contraseña.']);
    exit();
}

try {
    // Buscar al usuario por nombre de usuario o email
    $stmt = $pdo->prepare("SELECT id_usuario, password_hash FROM Usuarios WHERE nombre_usuario = ? OR email = ?");
    $stmt->execute([$username, $username]); // Se busca tanto por nombre de usuario como por email
    $user = $stmt->fetch();

    // Verificar si el usuario existe y la contraseña es correcta
    if ($user && password_verify($password, $user->password_hash)) {
        // Credenciales válidas: iniciar sesión
        $_SESSION['user_id'] = $user->id_usuario; // Almacenar el ID del usuario en la sesión
        $_SESSION['username'] = $username; // Opcional: almacenar el nombre de usuario
        // Actualizar la última fecha de sesión
        $stmt_update = $pdo->prepare("UPDATE Usuarios SET ultima_sesion = CURRENT_TIMESTAMP WHERE id_usuario = ?");
        $stmt_update->execute([$user->id_usuario]);

        http_response_code(200); // Código de estado HTTP 200 (OK)
        // Devolver un token simple o un mensaje de éxito. Para SPA, JWT sería mejor, pero para PHP sessions es suficiente.
        echo json_encode(['success' => true, 'message' => 'Inicio de sesión exitoso.', 'token' => session_id()]);
        // Nota: En una aplicación real con JWT, el token JWT sería generado aquí y enviado al cliente.
        // session_id() se usa aquí como un token de ejemplo para mostrar que algo se devuelve.

    } else {
        http_response_code(401); // Código de estado HTTP 401 (No autorizado)
        echo json_encode(['success' => false, 'message' => 'Nombre de usuario o contraseña incorrectos.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al iniciar sesión: ' . $e->getMessage()]);
}
?>
