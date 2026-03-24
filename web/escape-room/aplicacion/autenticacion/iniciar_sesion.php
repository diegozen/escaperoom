<?php
require "../plantillas/cabecera.php";

$errores = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email     = trim($_POST["email"]     ?? "");
    $contrasena = trim($_POST["contrasena"] ?? "");

    if (empty($email) || empty($contrasena)) {
        $errores[] = "Debes rellenar todos los campos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo introducido no es válido.";
    }

    if (empty($errores)) {
        try {
            $sql  = "SELECT id_usuario, contrasena FROM usuarios WHERE email = :email LIMIT 1";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([":email" => $email]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($contrasena, $usuario["contrasena"])) {
                session_regenerate_id(true);
                $_SESSION["usuario"] = $usuario["id_usuario"];
                header("Location: /escape-room/publico/landing.php");
                exit;
            } else {
                $errores[] = "Correo o contraseña incorrectos.";
            }

        } catch (PDOException $e) {
            error_log("Error DB login: " . $e->getMessage());
            $errores[] = "No se ha podido iniciar sesión. Inténtalo de nuevo.";
        }
    }
}
?>

<link rel="stylesheet" href="/escape-room/aplicacion/autenticacion/css/auth.css">

<div class="auth-contenedor">
    <div class="auth-caja">

        <div class="auth-cabecera">
            <span class="auth-tag">Acceso seguro</span>
            <h1 class="auth-titulo">Iniciar sesión</h1>
            <p class="auth-subtitulo">Introduce tus credenciales para acceder al laboratorio.</p>
        </div>

        <?php if (!empty($errores)): ?>
            <div class="alerta alerta-error">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="auth-form" novalidate>

            <div class="form-group">
                <label for="email">Correo electrónico</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    placeholder="usuario@ejemplo.com"
                    value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="form-group">
                <label for="contrasena">Contraseña</label>
                <input
                    type="password"
                    name="contrasena"
                    id="contrasena"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary auth-btn">
                Iniciar sesión
            </button>

        </form>

        <p class="auth-enlace">
            ¿No tienes cuenta?
            <a href="/escape-room/aplicacion/autenticacion/registrarse.php">Regístrate aquí</a>
        </p>

    </div>
</div>

<?php require "../plantillas/pie.php"; ?>