<?php
require "../plantillas/cabecera.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: /escape-room/aplicacion/autenticacion/iniciar_sesion.php");
    exit;
}

$id_usuario = $_SESSION["usuario"];

$retos_nombres = [
    "reto1" => "Reconocimiento de red",
    "reto2" => "Explotación web",
    "reto3" => "Escalada de privilegios",
    "reto4" => "Sniffing de tráfico",
    "reto5" => "Ataque coordinado",
];

try {
    // Mejor tiempo por reto completado del usuario
    $sql  = "SELECT
                 challenge_id,
                 MIN(elapsed_secs)  AS mejor_tiempo,
                 COUNT(*)           AS intentos,
                 MAX(finished_at)   AS ultima_vez
             FROM sesiones_reto
             WHERE id_usuario = :id
               AND status = 'completed'
               AND elapsed_secs IS NOT NULL
             GROUP BY challenge_id
             ORDER BY challenge_id ASC";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([":id" => $id_usuario]);
    $completados = $stmt->fetchAll();

    // Retos intentados pero no completados
    $sql2  = "SELECT DISTINCT challenge_id
              FROM sesiones_reto
              WHERE id_usuario = :id
                AND status IN ('aborted', 'timeout')
                AND challenge_id NOT IN (
                    SELECT DISTINCT challenge_id
                    FROM sesiones_reto
                    WHERE id_usuario = :id2 AND status = 'completed'
                )";
    $stmt2 = $conexion->prepare($sql2);
    $stmt2->execute([":id" => $id_usuario, ":id2" => $id_usuario]);
    $pendientes = $stmt2->fetchAll();

} catch (PDOException $e) {
    error_log("Error ranking personal: " . $e->getMessage());
    $completados = [];
    $pendientes  = [];
}

function formato_tiempo($segs) {
    if ($segs === null) return "—";
    $m = floor($segs / 60);
    $s = $segs % 60;
    return "{$m}m " . str_pad($s, 2, "0", STR_PAD_LEFT) . "s";
}

$completados_ids = array_column($completados, "challenge_id");
$total_retos     = 5;
$completados_n   = count($completados_ids);
$porcentaje      = round(($completados_n / $total_retos) * 100);
?>

<link rel="stylesheet" href="/escape-room/aplicacion/ranking/css/ranking.css">

<div class="ranking-contenedor contenedor">

    <div class="lab-cabecera">
        <div>
            <span class="etiqueta etiqueta-gris">Perfil</span>
            <h1 class="lab-titulo">Mi progreso</h1>
            <p class="lab-subtitulo">Resumen de tus retos completados y mejores tiempos.</p>
        </div>
        <a href="/escape-room/aplicacion/ranking/ranking.php" class="btn btn-ghost">
            Ranking global
        </a>
    </div>

    <!-- Resumen -->
    <div class="progreso-resumen">
        <div class="progreso-stat">
            <span class="progreso-valor"><?= $completados_n ?> / <?= $total_retos ?></span>
            <span class="progreso-label">Retos completados</span>
        </div>
        <div class="progreso-barra-contenedor">
            <div class="progreso-barra" style="width: <?= $porcentaje ?>%"></div>
        </div>
        <span class="progreso-pct"><?= $porcentaje ?>%</span>
    </div>

    <!-- Tabla de completados -->
    <?php if (empty($completados)): ?>
        <div class="ranking-placeholder">
            <p>Todavía no has completado ningún reto.</p>
            <a href="/escape-room/aplicacion/laboratorio/index.php" class="btn btn-outline">
                Ir al laboratorio
            </a>
        </div>
    <?php else: ?>
        <div class="tabla-contenedor" style="margin-bottom: 2rem;">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Reto</th>
                        <th>Mejor tiempo</th>
                        <th>Intentos</th>
                        <th>Última vez</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completados as $r): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($retos_nombres[$r["challenge_id"]] ?? $r["challenge_id"]) ?>
                        </td>
                        <td class="tiempo-valor"><?= formato_tiempo($r["mejor_tiempo"]) ?></td>
                        <td><?= (int)$r["intentos"] ?></td>
                        <td><?= htmlspecialchars(substr($r["ultima_vez"], 0, 16)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Retos pendientes -->
    <?php if (!empty($pendientes)): ?>
        <div class="pendientes-cabecera">
            <span class="etiqueta etiqueta-roja">Pendientes</span>
            <span class="pendientes-subtitulo">Retos intentados pero no completados</span>
        </div>
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Reto</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendientes as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($retos_nombres[$p["challenge_id"]] ?? $p["challenge_id"]) ?></td>
                        <td><span class="etiqueta etiqueta-roja">Sin completar</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<?php require "../plantillas/pie.php"; ?>