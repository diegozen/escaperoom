<?php
require "../plantillas/cabecera.php";

requerir_login();
$id_usuario = (int)$_SESSION["usuario"];
requerir_suscripcion($conexion, $id_usuario);

// Obtener sesiones activas del usuario
try {
    $sql  = "SELECT challenge_id, status, ssh_host, ssh_port, ssh_user, ssh_pass, started_at
             FROM sesiones_reto
             WHERE id_usuario = :id
             ORDER BY started_at DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([":id" => $id_usuario]);
    $sesiones = $stmt->fetchAll();

    $sesiones_idx = [];
    foreach ($sesiones as $s) {
        if (!isset($sesiones_idx[$s["challenge_id"]])) {
            $sesiones_idx[$s["challenge_id"]] = $s;
        }
    }
} catch (PDOException $e) {
    error_log("Error laboratorio: " . $e->getMessage());
    $sesiones_idx = [];
}

$retos = [
    [
        "id"           => "reto1",
        "titulo"       => "Reconocimiento de red",
        "descripcion"  => "Descubre los servicios activos en el sistema, analiza los puertos abiertos y encuentra el archivo oculto.",
        "dificultad"   => "Básico",
        "tiempo"       => "20 min",
        "tipo"         => "individual",
        "herramientas" => "nmap, netcat, curl",
    ],
    [
        "id"           => "reto2",
        "titulo"       => "Explotación web",
        "descripcion"  => "Explota una vulnerabilidad en el servicio web interno para extraer información sensible de la base de datos.",
        "dificultad"   => "Intermedio",
        "tiempo"       => "30 min",
        "tipo"         => "individual",
        "herramientas" => "curl, sqlmap",
    ],
    [
        "id"           => "reto3",
        "titulo"       => "Escalada de privilegios",
        "descripcion"  => "Accedes como usuario sin privilegios. Encuentra el vector de escalada y obtén acceso root al sistema.",
        "dificultad"   => "Intermedio",
        "tiempo"       => "25 min",
        "tipo"         => "individual",
        "herramientas" => "find, sudo, SUID",
    ],
    [
        "id"           => "reto4",
        "titulo"       => "Sniffing de tráfico",
        "descripcion"  => "Captura el tráfico de red interno del servidor y extrae las credenciales transmitidas en texto plano.",
        "dificultad"   => "Intermedio",
        "tiempo"       => "20 min",
        "tipo"         => "individual",
        "herramientas" => "tcpdump, tshark",
    ],
    [
        "id"           => "reto5",
        "titulo"       => "Ataque coordinado",
        "descripcion"  => "Reto grupal. Comprometed la máquina objetivo trabajando en equipo: uno escanea, otro explota, otro escala.",
        "dificultad"   => "Avanzado",
        "tiempo"       => "45 min",
        "tipo"         => "grupal",
        "herramientas" => "nmap, FTP, SSH, sudo",
    ],
];

$colores_dificultad = [
    "Básico"     => "etiqueta-verde",
    "Intermedio" => "etiqueta-aviso",
    "Avanzado"   => "etiqueta-roja",
];

$csrf = csrf_generar();
?>

<link rel="stylesheet" href="/escape-room/aplicacion/laboratorio/css/laboratorio.css">

<div class="lab-contenedor contenedor">

    <div class="lab-cabecera">
        <div>
            <span class="etiqueta etiqueta-verde">Laboratorio</span>
            <h1 class="lab-titulo">Retos disponibles</h1>
            <p class="lab-subtitulo">Selecciona un reto para iniciar tu sesión. Recibirás credenciales SSH únicas para tu entorno aislado.</p>
        </div>
    </div>

    <?php if (isset($_GET["error"])): ?>
        <div class="alerta alerta-error">
            <?= htmlspecialchars($_GET["error"]) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["msg"])): ?>
        <div class="alerta alerta-exito">
            <?= htmlspecialchars($_GET["msg"]) ?>
        </div>
    <?php endif; ?>

    <div class="retos-grid">
        <?php foreach ($retos as $reto):
            $sesion  = $sesiones_idx[$reto["id"]] ?? null;
            $activa  = $sesion && $sesion["status"] === "running";
            $col_dif = $colores_dificultad[$reto["dificultad"]] ?? "etiqueta-gris";
        ?>
        <div class="reto-card <?= $activa ? "reto-activo" : "" ?>">

            <div class="reto-card-cabecera">
                <div class="reto-meta">
                    <span class="etiqueta <?= $col_dif ?>"><?= $reto["dificultad"] ?></span>
                    <?php if ($reto["tipo"] === "grupal"): ?>
                        <span class="etiqueta etiqueta-gris">Grupal</span>
                    <?php endif; ?>
                </div>
                <?php if ($activa): ?>
                    <span class="reto-estado-activo">
                        <span class="punto-verde"></span> En curso
                    </span>
                <?php endif; ?>
            </div>

            <h2 class="reto-titulo"><?= htmlspecialchars($reto["titulo"]) ?></h2>
            <p class="reto-descripcion"><?= htmlspecialchars($reto["descripcion"]) ?></p>

            <div class="reto-info">
                <span class="reto-info-item">
                    <span class="reto-info-label">Tiempo</span>
                    <?= $reto["tiempo"] ?>
                </span>
                <span class="reto-info-item">
                    <span class="reto-info-label">Herramientas</span>
                    <?= $reto["herramientas"] ?>
                </span>
            </div>

            <?php if ($activa): ?>
                <div class="reto-credenciales">
                    <div class="cred-bloque">
                        <span class="cred-label">Conexión SSH</span>
                        <code class="cred-valor">ssh <?= htmlspecialchars($sesion["ssh_user"]) ?>@<?= htmlspecialchars($sesion["ssh_host"]) ?> -p <?= htmlspecialchars($sesion["ssh_port"]) ?></code>
                    </div>
                    <div class="cred-bloque">
                        <span class="cred-label">Contraseña</span>
                        <code class="cred-valor"><?= htmlspecialchars($sesion["ssh_pass"]) ?></code>
                    </div>
                </div>
                <div class="reto-acciones">
                    <a href="/escape-room/aplicacion/laboratorio/validar_flag.php?reto=<?= $reto["id"] ?>"
                       class="btn btn-primary">Validar flag</a>
                    <form method="POST"
                          action="/escape-room/aplicacion/laboratorio/abortar_reto.php"
                          style="display:inline"
                          onsubmit="return confirm('¿Seguro que quieres abortar este reto?')">
                        <input type="hidden" name="reto"       value="<?= htmlspecialchars($reto["id"]) ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" class="btn btn-ghost">Abortar</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="reto-acciones">
                    <form method="POST"
                          action="/escape-room/aplicacion/laboratorio/iniciar_reto.php">
                        <input type="hidden" name="reto"       value="<?= htmlspecialchars($reto["id"]) ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" class="btn btn-outline">Iniciar reto</button>
                    </form>
                </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>

</div>

<?php require "../plantillas/pie.php"; ?>