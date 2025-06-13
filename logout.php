<?php
// logout.php
// Endpoint para cerrar la sesión del usuario

header('Content-Type: application/json'); // Asegura que la respuesta sea JSON
header('Access-Control-Allow-Origin: *'); // Permite CORS desde cualquier origen (para desarrollo)
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Métodos permitidos
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Cabeceras permitidas

// Manejar solicitudes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Iniciar la sesión si no está ya iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se usan cookies de sesión, también se debe eliminar la cookie.
// Nota: Esto destruirá la sesión y no solo los datos de sesión.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión
session_destroy();

http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Sesión cerrada exitosamente.']);
?>
