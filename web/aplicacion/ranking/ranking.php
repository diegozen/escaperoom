<?php
require "../plantillas/cabecera.php";

$id_usuario_actual = $_SESSION["usuario"] ?? null;

// Ranking global — mejor tiempo por usuario en retos completados
// Agrupado por challenge_id para mostrar un ranking por reto
$challenge_id = $_GET["reto"] ?? null;

$retos_validos = [
    "reto1" => "Reconocimiento de red",
    "reto2" => "Explotación web",
    "reto3" => "Escalada de privilegios",
    "reto4" => "Sniffing de tráfico",
    "reto5" => "Ataque coordinado",
];

$ranking      = [];
$pos_usuario  = null;
$contexto     = [];

if ($challenge_id && isset($retos_validos[$challenge_id])) {
    try {
        // Mejor tiempo completado por usuario para este reto
        $sql = "SELECT
                    u.id_usuario,
                    u.nombre,
                    u.apellidos,
                    MIN(s.elapsed_secs) AS mejor_tiempo,
                    MAX(s.finished_at)  AS fecha
                FROM sesiones_reto s
                JOIN usuarios u ON s.id_usuario = u.id_usuario
                WHERE s.challenge_id = :reto
                  AND s.status = 'completed'
                  AND s.elapsed_secs IS NOT NULL
                GROUP BY u.id_usuario, u.nombre, u.apellidos
                ORDER BY mejor_tiempo ASC";

        $stmt = $conexion->prepare($sql);
        $stmt->execute([":reto" => $challenge_id]);
        $todos = $stmt->fetchAll();

        // Asignar posiciones
        $pos = 1;
        foreach ($todos as &$fila) {
            $fila["posicion"] = $pos++;
        }
        unset($fila);

        // Top 10
        $ranking = array_slice($todos, 0, 10);

        // Posición del usuario actual si existe
        if ($id_usuario_actual) {
            foreach ($todos as $fila) {
                if ((int)$fila["id_usuario"] === (int)$id_usuario_actual) {
                    $pos_usuario = $fila["posicion"];
                    break;
                }
            }

            // Si no está en el top 10, mostrar contexto alrededor de su posición
            if ($pos_usuario !== null && $pos_usuario > 10) {
                $inicio  = max(0, $pos_usuario - 3);
                $fin     = min(count($todos), $pos_usuario + 2);
                $contexto = array_slice($todos, $inicio, $fin - $inicio);
            }
        }

    } catch (PDOException $e) {
        error_log("Error ranking: " . $e->getMessage());
    }
}

function formato_tiempo($segs) {
    if ($segs === null) return "—";
    $m = floor($segs / 60);
    $s = $segs % 60;
    return "{$m}m " . str_pad($s, 2, "0", STR_PAD_LEFT) . "s";
}
?>

<link rel="stylesheet" href="/escape-room/aplicacion/ranking/css/ranking.css">

<div class="ranking-contenedor contenedor">

    <div class="lab-cabecera">
        <div>
            <span class="etiqueta etiqueta-gris">Clasificación</span>
            <h1 class="lab-titulo">Ranking global</h1>
            <p class="lab-subtitulo">Mejores tiempos por reto. Solo se contabilizan retos completados.</p>
        </div>
    </div>

    <!-- Selector de reto -->
    <div class="ranking-selector">
        <?php foreach ($retos_validos as $id => $nombre): ?>
            <a href="?reto=<?= $id ?>"
               class="btn <?= $challenge_id === $id ? 'btn-primary' : 'btn-ghost' ?> btn-sm">
                <?= htmlspecialchars($nombre) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$challenge_id): ?>
        <div class="ranking-placeholder">
            <p>Selecciona un reto para ver su clasificación.</p>
        </div>

    <?php elseif (empty($ranking)): ?>
        <div class="ranking-placeholder">
            <p>Todavía no hay tiempos registrados para este reto.</p>
        </div>

    <?php else: ?>

        <div class="tabla-contenedor">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Jugador</th>
                        <th>Tiempo</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ranking as $fila):
                        $es_yo   = $id_usuario_actual && (int)$fila["id_usuario"] === (int)$id_usuario_actual;
                        $pos     = $fila["posicion"];
                        $medalla = match($pos) {
                            1 => "pos-oro",
                            2 => "pos-plata",
                            3 => "pos-bronce",
                            default => ""
                        };
                    ?>
                    <tr class="<?= $es_yo ? "fila-yo" : "" ?>">
                        <td>
                            <span class="posicion <?= $medalla ?>"><?= $pos ?></span>
                        </td>
                        <td>
                            <?= htmlspecialchars($fila["nombre"] . " " . $fila["apellidos"]) ?>
                            <?php if ($es_yo): ?>
                                <span class="etiqueta etiqueta-verde" style="margin-left:0.4rem">Tú</span>
                            <?php endif; ?>
                        </td>
                        <td class="tiempo-valor"><?= formato_tiempo($fila["mejor_tiempo"]) ?></td>
                        <td><?= htmlspecialchars(substr($fila["fecha"], 0, 16)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Contexto del usuario si está fuera del top 10 -->
        <?php if (!empty($contexto)): ?>
            <div class="ranking-contexto-label">Tu posición</div>
            <div class="tabla-contenedor">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Jugador</th>
                            <th>Tiempo</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contexto as $fila):
                            $es_yo = (int)$fila["id_usuario"] === (int)$id_usuario_actual;
                        ?>
                        <tr class="<?= $es_yo ? "fila-yo" : "" ?>">
                            <td><span class="posicion"><?= $fila["posicion"] ?></span></td>
                            <td>
                                <?= htmlspecialchars($fila["nombre"] . " " . $fila["apellidos"]) ?>
                                <?php if ($es_yo): ?>
                                    <span class="etiqueta etiqueta-verde" style="margin-left:0.4rem">Tú</span>
                                <?php endif; ?>
                            </td>
                            <td class="tiempo-valor"><?= formato_tiempo($fila["mejor_tiempo"]) ?></td>
                            <td><?= htmlspecialchars(substr($fila["fecha"], 0, 16)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php require "../plantillas/pie.php"; ?>