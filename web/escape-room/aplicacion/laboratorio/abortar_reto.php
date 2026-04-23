<?php
require "../plantillas/cabecera.php";

requerir_login();
$id_usuario = (int)$_SESSION["usuario"];
requerir_suscripcion($conexion, $id_usuario);

// Abortar solo se puede hacer por POST con token CSRF válido
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /escape-room/aplicacion/laboratorio/index.php");
    exit;
}

csrf_validar();

$challenge_id  = $_POST["reto"] ?? null;
$retos_validos = ["reto1", "reto2", "reto3", "reto4", "reto5"];

if (!$challenge_id || !in_array($challenge_id, $retos_validos, true)) {
    header("Location: /escape-room/aplicacion/laboratorio/index.php?error=Reto+no+válido");
    exit;
}

$es_grupal = ($challenge_id === "reto5");
$endpoint  = $es_grupal
    ? "http://localhost:8000/challenge/reto5/abort"
    : "http://localhost:8000/challenge/abort";

$body = $es_grupal
    ? "{}"
    : json_encode(["user_id" => (string)$id_usuario, "challenge_id" => $challenge_id]);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
    CURLOPT_TIMEOUT        => 10,
]);
curl_exec($ch);
curl_close($ch);

// Sincronización local por si el orquestador falla
try {
    $sql  = "UPDATE sesiones_reto
             SET status = 'aborted', finished_at = NOW()
             WHERE id_usuario = :id AND challenge_id = :challenge AND status = 'running'";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([":id" => $id_usuario, ":challenge" => $challenge_id]);
} catch (PDOException $e) {
    error_log("Error abortando reto: " . $e->getMessage());
}

header("Location: /escape-room/aplicacion/laboratorio/index.php?msg=Reto+abortado");
exit;