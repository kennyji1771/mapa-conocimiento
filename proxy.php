<?php
/**
 * proxy.php
 * Proxy local para comunicarse con Google Apps Script.
 * 
 * Este proxy actúa como un puente entre tu aplicación PHP y la Web App de Google,
 * resolviendo problemas de CORS y manejando correctamente las redirecciones de Google.
 * 
 * Configuración:
 * - Asegura que cURL siga redirecciones y mantenga el método POST (CURLOPT_FOLLOWLOCATION + CURLOPT_POSTREDIR).
 * - Forza HTTP/1.1 para evitar problemas de protocolo.
 * 
 * Actualizado con la nueva URL de la Web App.
 */

// Configuración inicial: Indicamos que la respuesta será en formato JSON
// y permitimos peticiones desde cualquier origen (CORS).
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Responder a preflight CORS (petición previa de verificación)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// ==== NUEVA URL DE TU WEB APP (proporcionada por el usuario) ====
$apiUrl = 'https://script.google.com/macros/s/AKfycbw4n0QjugrNKXgDrH9NAJMdOH0ONWO4eCUwl9fOE4R3oLjIAH6YlRqEIRu8jcfmEoevPg/exec';

// -------------------------------------------------------------------
// Manejo de peticiones GET (para leer notas)
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = $_SERVER['QUERY_STRING'];
    $url = $apiUrl . ($query ? '?' . $query : '');
    
    // Usar file_get_contents para GET (simple)
    $response = @file_get_contents($url);
    if ($response === false) {
        echo json_encode(['error' => 'No se pudo obtener respuesta del servidor GET']);
    } else {
        echo $response;
    }
    exit;
}

// -------------------------------------------------------------------
// Manejo de peticiones POST (para guardar notas)
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Leer los datos JSON enviados desde el frontend
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Validación básica: asegurarse de que todos los campos requeridos estén presentes
    if (!$data || !isset($data['idNodo']) || !isset($data['idSlide']) || !isset($data['usuario'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios en la petición POST']);
        exit;
    }
    
    // Inicializar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    // --- Configuraciones críticas para evitar el error 302/400 ---
    // 1. Seguir redirecciones (como hace un navegador)
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // 2. Mantener el método POST después de una redirección 302
    curl_setopt($ch, CURLOPT_POSTREDIR, CURL_REDIR_POST_302);
    // 3. Forzar el método POST explícitamente (por si acaso)
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    // 4. Deshabilitar verificación SSL (solo para desarrollo local; en producción habilítala)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    // 5. Forzar HTTP/1.1 para evitar errores de protocolo
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    // 6. Timeout para evitar esperas largas
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Ejecutar la petición
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Manejo de errores de cURL
    if ($response === false) {
        http_response_code(500);
        echo json_encode(['error' => "cURL error: $error"]);
        exit;
    }
    
    // Si la respuesta no es JSON (p.ej., HTML de error), devolver error
    if (json_decode($response) === null && $response !== '') {
        http_response_code(500);
        echo json_encode(['error' => 'Respuesta no JSON', 'raw' => substr($response, 0, 200)]);
        exit;
    }
    
    // Devolver la respuesta del Apps Script (incluyendo el código HTTP que recibió)
    http_response_code($httpCode);
    echo $response;
    exit;
}

// Si llegamos aquí, el método HTTP no es válido
http_response_code(405);
echo json_encode(['error' => 'Método HTTP no permitido']);
?>