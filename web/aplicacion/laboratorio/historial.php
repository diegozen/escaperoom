<?php
require "../plantillas/cabecera.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: /escape-room/aplicacion/autenticacion/iniciar_sesion.php");
    exit;
}

$id_usuario = $_SESSION["usuario"];

try {
    $sql  = "SELECT challenge_id, status, ssh_host, ssh_port,
                    started_at, finished_at, elapsed_secs
             FROM sesiones_reto
             WHERE id_usuario = :id
             ORDER BY started_at DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([":id" => $id_usuario]);
    $sesiones = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error historial: " . $e->getMessage());
    $sesiones = [];
}

$nombres_retos = [
    "reto1" => "Reconocimiento de red",
    "reto2" => "Explotación web",
    "reto3" => "Escalada de privilegios",
    "reto4" => "Sniffing de tráfico",
    "reto5" => "Ataque coordinado",
];

function formato_tiempo($segs) {
    if ($segs === null) return "—";
    $m = floor($segs / 60);
    $s = $segs % 60;
    return "{$m}m " . str_pad($s, 2, "0", STR_PAD_LEFT) . "s";
}

function clase_estado($estado) {
    return match($estado) {
        "completed" => "etiqueta-verde",
        "running"   => "etiqueta-aviso",
        default     => "etiqueta-roja",
    };
}

function label_estado($estado) {
    return match($estado) {
        "completed" => "Completado",
        "running"   => "En curso",
        "timeout"   => "Tiempo agotado",
        "aborted"   => "Abortado",
        default     => $estado,
    };
}
?>

<link rel="stylesheet" href="/escape-room/aplicacion/laboratorio/css/laboratorio.css">

<div class="lab-contenedor contenedor">

    <div class="lab-cabecera">
        <div>
            <span class="etiqueta etiqueta-gris">Historial</span>
            <h1 class="lab-titulo">Mis partidas</h1>
            <p class="lab-subtitulo">Registro de todas tus sesiones en el laboratorio.</p>
        </div>
        <a href="/escape-room/aplicacion/laboratorio/index.php" class="btn btn-ghost">
            Volver al laboratorio
        </a>
    </div>

    <?php if (empty($sesiones)): ?>
        <div class="historial-vacio">
            <p>Todavía no has iniciado ningún reto.</p>
            <a href="/escape-room/aplicacion/laboratorio/index.php" class="btn btn-outline">
                Ir al laboratorio
            </a>
        </div>
    <?php else: ?>
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Reto</th>
                        <th>Estado</th>
                        <th>Tiempo</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sesiones as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($nombres_retos[$s["challenge_id"]] ?? $s["challenge_id"]) ?></td>
                        <td>
                            <span class="etiqueta <?= clase_estado($s["status"]) ?>">
                                <?= label_estado($s["status"]) ?>
                            </span>
                        </td>
                        <td><?= formato_tiempo($s["elapsed_secs"]) ?></td>
                        <td><?= htmlspecialchars(substr($s["started_at"], 0, 16)) ?></td>
                        <td><?= $s["finished_at"] ? htmlspecialchars(substr($s["finished_at"], 0, 16)) : "—" ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<?php require "../plantillas/pie.php"; ?>