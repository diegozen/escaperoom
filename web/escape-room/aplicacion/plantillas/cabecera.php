<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../base_datos.php";
require_once __DIR__ . "/../seguridad.php";

// Aplicar cabeceras de seguridad en todas las páginas
aplicar_cabeceras_seguridad();

$usuarioLogueado = null;

if (isset($_SESSION["usuario"])) {
    try {
        $sql  = "SELECT nombre FROM usuarios WHERE id_usuario = :id";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([":id" => $_SESSION["usuario"]]);
        $usuarioLogueado = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $usuarioLogueado = null;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Escape Room Tecnológico — Retos de redes y ciberseguridad.">
    <title>Escape Room Tecnológico</title>
    <link rel="stylesheet" href="/escape-room/publico/css/global.css">
</head>
<body>

<header class="cabecera">
    <nav>
        <a class="logo" href="/escape-room/publico/landing.php">
            Escape<span>Room</span>
        </a>

        <a href="/escape-room/aplicacion/ranking/ranking.php">Ranking</a>

        <?php if ($usuarioLogueado): ?>
            <div class="menu-usuario" id="menuUsuario">
                <span class="saludo" id="menuTrigger">
                    <?= htmlspecialchars($usuarioLogueado["nombre"]) ?> &#9660;
                </span>
                <div class="submenu" id="submenuUsuario">
                    <a href="/escape-room/aplicacion/laboratorio/historial.php">Mis partidas</a>
                    <a href="/escape-room/aplicacion/ranking/ranking_personal.php">Estadísticas</a>
                    <a href="/escape-room/aplicacion/autenticacion/cerrar_sesion.php">Cerrar sesión</a>
                </div>
            </div>
        <?php else: ?>
            <a href="/escape-room/aplicacion/autenticacion/iniciar_sesion.php">Iniciar sesión</a>
            <a href="/escape-room/aplicacion/autenticacion/registrarse.php">Registrarse</a>
        <?php endif; ?>
    </nav>
</header>

<main>

<script>
(function () {
    const trigger = document.getElementById("menuTrigger");
    const menu    = document.getElementById("menuUsuario");

    if (!trigger || !menu) return;

    trigger.addEventListener("click", function (e) {
        e.stopPropagation();
        menu.classList.toggle("abierto");
    });

    document.addEventListener("click", function (e) {
        if (!menu.contains(e.target)) {
            menu.classList.remove("abierto");
        }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") menu.classList.remove("abierto");
    });
})();
</script>