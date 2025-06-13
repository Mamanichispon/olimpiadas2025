<?php
// auth_middleware.php
// Middleware para verificar si el usuario está autenticado

// Inicia la sesión si no está ya iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verifica si el 'user_id' está establecido en la sesión.
// Este 'user_id' se establecerá durante el proceso de inicio de sesión exitoso.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Código de estado HTTP 401 (No autorizado)
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Se requiere autenticación.']);
    exit(); // Termina la ejecución del script si el usuario no está autenticado
}

// Si llega hasta aquí, el usuario está autenticado.
// Puedes acceder al ID del usuario logueado con $_SESSION['user_id'] en los scripts que incluyan este middleware.
?>