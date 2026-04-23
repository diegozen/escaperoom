<?php
require "../plantillas/cabecera.php";

requerir_login();
$id_usuario = (int)$_SESSION["usuario"];
requerir_suscripcion($conexion, $id_usuario);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /escape-room/aplicacion/laboratorio/index.php");
    exit;
}

csrf_validar();

// Rate limiting: máx 10 inicios de reto por usuario en 10 minutos
// Evita que alguien levante decenas de contenedores en bucle
rate_limit_o_abortar(
    "iniciar_{$id_usuario}",
    10,
    600,
    "/escape-room/aplicacion/laboratorio/index.php",
    "Demasiados intentos de inicio. Espera unos minutos."
);

$challenge_id  = $_POST["reto"] ?? null;
$retos_validos = ["reto1", "reto2", "reto3", "reto4", "reto5"];

if (!$challenge_id || !in_array($challenge_id, $retos_validos, true)) {
    header("Location: /escape-room/aplicacion/laboratorio/index.php?error=Reto+no+válido");
    exit;
}

$es_grupal = ($challenge_id === "reto5");
$endpoint  = $es_grupal
    ? "http://localhost:8000/challenge/reto5/start"
    : "http://localhost:8000/challenge/start";

$body = $es_grupal
    ? "{}"
    : json_encode(["user_id" => (string)$id_usuario, "challenge_id" => $challenge_id]);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
    CURLOPT_TIMEOUT        => 60,   // contenedor puede tardar hasta ~30s en estar healthy
]);
$respuesta = curl_exec($ch);
$codigo    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($codigo !== 200 || !$respuesta) {
    $msg = json_decode($respuesta, true)["detail"] ?? "Error al iniciar el reto.";
    header("Location: /escape-room/aplicacion/laboratorio/index.php?error=" . urlencode($msg));
    exit;
}

$datos = json_decode($respuesta, true);

if ($es_grupal && isset($datos["jugadores"])) {
    $_SESSION["reto5_creds"] = $datos["jugadores"];
}

header("Location: /escape-room/aplicacion/laboratorio/index.php?msg=Reto+iniciado+correctamente");
exit;