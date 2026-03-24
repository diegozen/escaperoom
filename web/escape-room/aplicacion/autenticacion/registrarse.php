<?php
require "../plantillas/cabecera.php";

$errores = [];
$datos   = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $datos["nombre"]    = trim($_POST["nombre"]     ?? "");
    $datos["apellidos"] = trim($_POST["apellidos"]  ?? "");
    $datos["email"]     = trim($_POST["email"]      ?? "");
    $contrasena         = trim($_POST["contrasena"] ?? "");
    $contrasena2        = trim($_POST["contrasena2"] ?? "");

    // Validaciones
    if (empty($datos["nombre"]) || empty($datos["apellidos"]) ||
        empty($datos["email"])  || empty($contrasena) || empty($contrasena2)) {
        $errores[] = "Debes rellenar todos los campos.";
    }

    if (!empty($datos["email"]) && !filter_var($datos["email"], FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo introducido no es válido.";
    }

    if (!empty($contrasena) && strlen($contrasena) < 8) {
        $errores[] = "La contraseña debe tener al menos 8 caracteres.";
    }

    if ($contrasena !== $contrasena2) {
        $errores[] = "Las contraseñas no coinciden.";
    }

    if (empty($errores)) {
        try {
            // Comprobar si el email ya existe
            $stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE email = :email LIMIT 1");
            $stmt->execute([":email" => $datos["email"]]);

            if ($stmt->fetch()) {
                $errores[] = "El correo ya está registrado.";
            } else {
                $sql  = "INSERT INTO usuarios (nombre, apellidos, email, contrasena)
                         VALUES (:nombre, :apellidos, :email, :contrasena)";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([
                    ":nombre"    => $datos["nombre"],
                    ":apellidos" => $datos["apellidos"],
                    ":email"     => $datos["email"],
                    ":contrasena" => password_hash($contrasena, PASSWORD_DEFAULT),
                ]);

                session_regenerate_id(true);
                $_SESSION["usuario"] = $conexion->lastInsertId();

                header("Location: /escape-room/publico/landing.php");
                exit;
            }

        } catch (PDOException $e) {
            error_log("Error DB registro: " . $e->getMessage());
            $errores[] = "No se ha podido completar el registro. Inténtalo de nuevo.";
        }
    }
}
?>

<link rel="stylesheet" href="/escape-room/aplicacion/autenticacion/css/auth.css">

<div class="auth-contenedor">
    <div class="auth-caja">

        <div class="auth-cabecera">
            <span class="auth-tag">Nueva cuenta</span>
            <h1 class="auth-titulo">Crear cuenta</h1>
            <p class="auth-subtitulo">Regístrate para acceder al laboratorio de retos.</p>
        </div>

        <?php if (!empty($errores)): ?>
            <div class="alerta alerta-error">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="auth-form" novalidate>

            <div class="form-fila">
                <div class="form-group">
                    <label for="nombre">Nombre</label>
                    <input
                        type="text"
                        name="nombre"
                        id="nombre"
                        placeholder="Juan"
                        value="<?= htmlspecialchars($datos["nombre"] ?? "") ?>"
                        autocomplete="given-name"
                        required
                    >
                </div>
                <div class="form-group">
                    <label for="apellidos">Apellidos</label>
                    <input
                        type="text"
                        name="apellidos"
                        id="apellidos"
                        placeholder="García López"
                        value="<?= htmlspecialchars($datos["apellidos"] ?? "") ?>"
                        autocomplete="family-name"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="email">Correo electrónico</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    placeholder="usuario@ejemplo.com"
                    value="<?= htmlspecialchars($datos["email"] ?? "") ?>"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="form-fila">
                <div class="form-group">
                    <label for="contrasena">Contraseña</label>
                    <input
                        type="password"
                        name="contrasena"
                        id="contrasena"
                        placeholder="Mínimo 8 caracteres"
                        autocomplete="new-password"
                        required
                    >
                </div>
                <div class="form-group">
                    <label for="contrasena2">Repetir contraseña</label>
                    <input
                        type="password"
                        name="contrasena2"
                        id="contrasena2"
                        placeholder="Repite la contraseña"
                        autocomplete="new-password"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="btn btn-primary auth-btn">
                Crear cuenta
            </button>

        </form>

        <p class="auth-enlace">
            ¿Ya tienes cuenta?
            <a href="/escape-room/aplicacion/autenticacion/iniciar_sesion.php">Inicia sesión aquí</a>
        </p>

    </div>
</div>

<?php require "../plantillas/pie.php"; ?>