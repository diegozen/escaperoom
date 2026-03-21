<?php
require "../plantillas/cabecera.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: /escape-room/aplicacion/autenticacion/iniciar_sesion.php");
    exit;
}

$id_usuario   = $_SESSION["usuario"];
$challenge_id = $_GET["reto"] ?? null;
$errores      = [];
$exito        = false;
$resultado    = null;

$retos_validos = ["reto1", "reto2", "reto3", "reto4", "reto5"];
if (!$challenge_id || !in_array($challenge_id, $retos_validos)) {
    header("Location: /escape-room/aplicacion/laboratorio/index.php?error=Reto+no+válido");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $flag = trim($_POST["flag"] ?? "");

    if (empty($flag)) {
        $errores[] = "Introduce la flag antes de validar.";
    }

    if (empty($errores)) {
        $es_grupal = ($challenge_id === "reto5");
        $endpoint  = $es_grupal
            ? "http://localhost:8000/challenge/reto5/validate"
            : "http://localhost:8000/challenge/validate";

        $body = json_encode([
            "user_id"      => (string)$id_usuario,
            "challenge_id" => $challenge_id,
            "flag"         => $flag,
        ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $respuesta = curl_exec($ch);
        $codigo    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resultado = json_decode($respuesta, true);

        if ($resultado && $resultado["success"] === true) {
            $exito = true;

            // Actualizar estado en BD
            try {
                $elapsed = $resultado["elapsed_seconds"] ?? null;
                $sql  = "UPDATE sesiones_reto
                         SET status = 'completed', finished_at = NOW(), elapsed_secs = :elapsed
                         WHERE id_usuario = :id AND challenge_id = :challenge AND status = 'running'";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([
                    ":elapsed"   => $elapsed,
                    ":id"        => $id_usuario,
                    ":challenge" => $challenge_id,
                ]);

                // Insertar en ranking si no existe
                $sqlCheck = "SELECT 1 FROM ranking
                             JOIN partidas ON ranking.id_partida = partidas.id_partida
                             WHERE ranking.id_usuario = :id";
                // Registro simplificado en ranking usando sesiones_reto
                $sqlRank = "INSERT INTO ranking (id_usuario, id_partida, tiempo_total, estado)
                            SELECT :id_usuario, p.id_partida, :tiempo, 'completada'
                            FROM partidas p
                            WHERE p.id_usuario = :id_usuario2
                            ORDER BY p.fecha DESC
                            LIMIT 1";
                // Solo insertamos si hay partida asociada
                $stmtRank = $conexion->prepare($sqlRank);
                $stmtRank->execute([
                    ":id_usuario"  => $id_usuario,
                    ":tiempo"      => $elapsed ?? 0,
                    ":id_usuario2" => $id_usuario,
                ]);
            } catch (PDOException $e) {
                error_log("Error actualizando estado flag: " . $e->getMessage());
            }
        } else {
            $errores[] = $resultado["message"] ?? "Flag incorrecta. Sigue intentándolo.";
        }
    }
}

$nombres_retos = [
    "reto1" => "Reconocimiento de red",
    "reto2" => "Explotación web",
    "reto3" => "Escalada de privilegios",
    "reto4" => "Sniffing de tráfico",
    "reto5" => "Ataque coordinado",
];
?>

<link rel="stylesheet" href="/escape-room/aplicacion/laboratorio/css/laboratorio.css">

<div class="lab-contenedor contenedor">

    <div class="lab-cabecera">
        <a href="/escape-room/aplicacion/laboratorio/index.php" class="btn btn-ghost btn-sm">
            &larr; Volver al laboratorio
        </a>
    </div>

    <div class="validar-caja">

        <div class="auth-cabecera">
            <span class="auth-tag"><?= htmlspecialchars($nombres_retos[$challenge_id] ?? $challenge_id) ?></span>
            <h1 class="auth-titulo">Validar flag</h1>
            <p class="auth-subtitulo">Introduce la flag que has encontrado en el sistema para completar el reto.</p>
        </div>

        <?php if ($exito): ?>
            <div class="alerta alerta-exito">
                <strong>Reto completado.</strong>
                <?php if (isset($resultado["elapsed_human"])): ?>
                    Tiempo: <?= htmlspecialchars($resultado["elapsed_human"]) ?>
                <?php endif; ?>
            </div>
            <div class="validar-acciones">
                <a href="/escape-room/aplicacion/laboratorio/index.php" class="btn btn-primary">
                    Volver al laboratorio
                </a>
                <a href="/escape-room/aplicacion/ranking/ranking.php" class="btn btn-ghost">
                    Ver ranking
                </a>
            </div>
        <?php else: ?>

            <?php if (!empty($errores)): ?>
                <div class="alerta alerta-error">
                    <?php foreach ($errores as $e): ?>
                        <p><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-form">
                <div class="form-group">
                    <label for="flag">Flag</label>
                    <input
                        type="text"
                        name="flag"
                        id="flag"
                        placeholder="FLAG{...}"
                        autocomplete="off"
                        spellcheck="false"
                        required
                    >
                </div>
                <button type="submit" class="btn btn-primary auth-btn">
                    Validar
                </button>
            </form>

        <?php endif; ?>

    </div>
</div>

<?php require "../plantillas/pie.php"; ?>