<?php
require "../plantillas/cabecera.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: /escape-room/aplicacion/autenticacion/iniciar_sesion.php");
    exit;
}

$id_usuario   = $_SESSION["usuario"];
$challenge_id = $_GET["reto"] ?? null;

$retos_validos = ["reto1", "reto2", "reto3", "reto4", "reto5"];

if (!$challenge_id || !in_array($challenge_id, $retos_validos)) {
    header("Location: /escape-room/aplicacion/laboratorio/index.php?error=Reto+no+válido");
    exit;
}

// Determinar endpoint según tipo de reto
$es_grupal = ($challenge_id === "reto5");
$endpoint  = $es_grupal
    ? "http://localhost:8000/challenge/reto5/start"
    : "http://localhost:8000/challenge/start";

$body = $es_grupal
    ? "{}"
    : json_encode(["user_id" => (string)$id_usuario, "challenge_id" => $challenge_id]);

// Llamar al orquestador — él crea la sesión en BD
$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
    CURLOPT_TIMEOUT        => 15,
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

// Para el reto5 guardamos las credenciales de cada jugador en sesión PHP
// para que el administrador pueda distribuirlas (no se persiste en BD desde aquí)
if ($es_grupal && isset($datos["jugadores"])) {
    $_SESSION["reto5_creds"] = $datos["jugadores"];
}

header("Location: /escape-room/aplicacion/laboratorio/index.php?msg=Reto+iniciado+correctamente");
exit;