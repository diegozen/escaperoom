<?php
require "../plantillas/cabecera.php";

// Requiere login
if (!isset($_SESSION["usuario"])) {
    header("Location: /escape-room/aplicacion/autenticacion/iniciar_sesion.php");
    exit;
}

$id_usuario = $_SESSION["usuario"];
$errores    = [];

// Obtener datos del usuario y comprobar suscripción
try {
    $stmt = $conexion->prepare(
        "SELECT nombre, apellidos, suscrito FROM usuarios WHERE id_usuario = :id LIMIT 1"
    );
    $stmt->execute([":id" => $id_usuario]);
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error comprobando suscripción: " . $e->getMessage());
    $usuario = null;
}

// Si ya está suscrito, redirigir directamente al laboratorio
if ($usuario && $usuario["suscrito"]) {
    header("Location: /escape-room/aplicacion/laboratorio/index.php");
    exit;
}

$nombre_completo = $usuario
    ? htmlspecialchars($usuario["nombre"] . " " . $usuario["apellidos"])
    : "Usuario";

// Procesar activación
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $stmt = $conexion->prepare(
            "UPDATE usuarios SET suscrito = 1 WHERE id_usuario = :id"
        );
        $stmt->execute([":id" => $id_usuario]);
        header("Location: /escape-room/aplicacion/laboratorio/index.php?msg=¡Suscripción+activada!+Bienvenido+al+laboratorio.");
        exit;
    } catch (PDOException $e) {
        error_log("Error activando suscripción: " . $e->getMessage());
        $errores[] = "No se pudo procesar el pago. Inténtalo de nuevo.";
    }
}
?>

<link rel="stylesheet" href="/escape-room/aplicacion/autenticacion/css/auth.css">
<link rel="stylesheet" href="/escape-room/aplicacion/pagos/css/pago.css">

<div class="auth-contenedor pago-contenedor">
    <div class="auth-caja pago-caja">

        <div class="auth-cabecera">
            <span class="auth-tag">Acceso al laboratorio</span>
            <h1 class="auth-titulo">Activar suscripción</h1>
            <p class="auth-subtitulo">
                Acceso completo a todos los retos por <strong class="pago-precio">5,00 €</strong> / mes.
            </p>
        </div>

        <?php if (!empty($errores)): ?>
            <div class="alerta alerta-error">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Resumen del plan -->
        <div class="pago-plan">
            <div class="pago-plan-fila">
                <span>Plan mensual — Escape Room Tecnológico</span>
                <span class="pago-plan-precio">5,00 €</span>
            </div>
            <div class="pago-plan-fila pago-plan-total">
                <span>Total hoy</span>
                <span>5,00 €</span>
            </div>
        </div>

        <!-- Formulario de tarjeta -->
        <form action="" method="POST" class="auth-form pago-form" novalidate>

            <div class="form-group">
                <label for="nombre_tarjeta">Titular de la tarjeta</label>
                <input
                    type="text"
                    name="nombre_tarjeta"
                    id="nombre_tarjeta"
                    value="<?= $nombre_completo ?>"
                    autocomplete="cc-name"
                    readonly
                >
            </div>

            <div class="form-group">
                <label for="numero_tarjeta">Número de tarjeta</label>
                <div class="pago-input-icono">
                    <input
                        type="text"
                        name="numero_tarjeta"
                        id="numero_tarjeta"
                        value="4111 1111 1111 1111"
                        maxlength="19"
                        autocomplete="cc-number"
                        readonly
                    >
                    <span class="pago-visa-badge">VISA</span>
                </div>
            </div>

            <div class="form-fila">
                <div class="form-group">
                    <label for="caducidad">Caducidad</label>
                    <input
                        type="text"
                        name="caducidad"
                        id="caducidad"
                        value="12/28"
                        maxlength="5"
                        autocomplete="cc-exp"
                        readonly
                    >
                </div>
                <div class="form-group">
                    <label for="cvv">CVV</label>
                    <input
                        type="text"
                        name="cvv"
                        id="cvv"
                        value="123"
                        maxlength="3"
                        autocomplete="cc-csc"
                        readonly
                    >
                </div>
            </div>

            <div class="pago-aviso">
                <span class="pago-lock">🔒</span>
                Entorno de pruebas — ningún cargo real será efectuado.
            </div>

            <button type="submit" class="btn btn-primary auth-btn pago-btn">
                Activar suscripción — 5,00 €
            </button>

        </form>

        <p class="auth-enlace">
            <a href="/escape-room/publico/landing.php">Volver al inicio</a>
        </p>

    </div>
</div>

<?php require "../plantillas/pie.php"; ?>