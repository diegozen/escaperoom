<?php
require "../plantillas/cabecera.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: /escape-room/aplicacion/autenticacion/iniciar_sesion.php");
    exit;
}

$id_usuario  = $_SESSION["usuario"];
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

// Llamar al orquestador
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

// Guardar sesión en BD
try {
    if ($es_grupal) {
        // Para el reto grupal guardamos una sesión por jugador
        foreach ($datos["jugadores"] as $jugador => $creds) {
            $sql  = "INSERT INTO sesiones_reto
                        (id_usuario, challenge_id, ssh_host, ssh_port, ssh_user, ssh_pass, status)
                     VALUES
                        (:id_usuario, :challenge_id, :ssh_host, :ssh_port, :ssh_user, :ssh_pass, 'running')
                     ON DUPLICATE KEY UPDATE
                        ssh_port = VALUES(ssh_port),
                        ssh_user = VALUES(ssh_user),
                        ssh_pass = VALUES(ssh_pass),
                        status   = 'running',
                        started_at = NOW()";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([
                ":id_usuario"   => $id_usuario,
                ":challenge_id" => $challenge_id,
                ":ssh_host"     => $datos["host"] ?? "localhost",
                ":ssh_port"     => $creds["ssh_port"],
                ":ssh_user"     => $creds["ssh_user"],
                ":ssh_pass"     => $creds["ssh_pass"],
            ]);
        }
        // Redirigir con las credenciales del primer jugador disponible
        $primera = array_values($datos["jugadores"])[0];
        $_SESSION["reto5_creds"] = $datos["jugadores"];
    } else {
        $sql  = "INSERT INTO sesiones_reto
                    (id_usuario, challenge_id, ssh_host, ssh_port, ssh_user, ssh_pass, status)
                 VALUES
                    (:id_usuario, :challenge_id, :ssh_host, :ssh_port, :ssh_user, :ssh_pass, 'running')";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ":id_usuario"   => $id_usuario,
            ":challenge_id" => $challenge_id,
            ":ssh_host"     => $datos["host"] ?? "localhost",
            ":ssh_port"     => $datos["port"],
            ":ssh_user"     => $datos["user"],
            ":ssh_pass"     => $datos["password"],
        ]);
    }
} catch (PDOException $e) {
    error_log("Error guardando sesión reto: " . $e->getMessage());
}

header("Location: /escape-room/aplicacion/laboratorio/index.php?msg=Reto+iniciado+correctamente");
exit;