<?php
// ── api/proxy.php ─────────────────────────────────────────────
// Proxy restringido entre el JS del navegador y el orquestador.
// Solo permite consultas de LECTURA (GET) a endpoints autorizados.
// El orquestador escucha en localhost:8000 y no debe exponerse al exterior.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../base_datos.php";
require_once __DIR__ . "/../seguridad.php";

// Solo usuarios autenticados pueden usar el proxy
if (!isset($_SESSION["usuario"])) {
    http_response_code(401);
    echo json_encode(["error" => "No autenticado"]);
    exit;
}

$id_usuario = (int)$_SESSION["usuario"];

// Rate limiting: 60 consultas por minuto por usuario (polling de status)
if (!rate_limit_check("proxy_{$id_usuario}", 60, 60)) {
    http_response_code(429);
    echo json_encode(["error" => "Demasiadas peticiones"]);
    exit;
}

// ── Endpoints permitidos (solo GET, solo lectura) ─────────────
$ENDPOINTS_PERMITIDOS = [
    'status' => '/challenge/status',
    'ranking' => '/ranking',
];

$accion       = $_GET["accion"]       ?? '';
$challenge_id = $_GET["challenge_id"] ?? '';
$retos_validos = ["reto1", "reto2", "reto3", "reto4", "reto5"];

// Validar acción
if (!isset($ENDPOINTS_PERMITIDOS[$accion])) {
    http_response_code(400);
    echo json_encode(["error" => "Acción no permitida"]);
    exit;
}

// Construir URL según la acción
switch ($accion) {
    case 'status':
        // Validar challenge_id
        if (!in_array($challenge_id, $retos_validos, true)) {
            http_response_code(400);
            echo json_encode(["error" => "Reto no válido"]);
            exit;
        }
        $url = "http://localhost:8000/challenge/status/{$id_usuario}/{$challenge_id}";
        break;

    case 'ranking':
        $url = "http://localhost:8000/ranking";
        break;

    default:
        http_response_code(400);
        echo json_encode(["error" => "Acción no válida"]);
        exit;
}

// ── Llamada al orquestador ────────────────────────────────────
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET        => true,
    CURLOPT_TIMEOUT        => 5,
    CURLOPT_CONNECTTIMEOUT => 3,
]);
$respuesta = curl_exec($ch);
$codigo    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error     = curl_error($ch);
curl_close($ch);

if ($error || !$respuesta) {
    http_response_code(502);
    echo json_encode(["error" => "El orquestador no responde"]);
    exit;
}

// Reenviar el código HTTP del orquestador y la respuesta JSON
http_response_code($codigo);
header("Content-Type: application/json");
echo $respuesta;