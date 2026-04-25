<?php
// ── seguridad.php — helper central de seguridad ──────────────
// Incluir DESPUÉS de iniciar sesión, ANTES de cualquier output.

// ── Cabeceras de seguridad HTTP ───────────────────────────────
function aplicar_cabeceras_seguridad(): void {
    // Evita que el navegador detecte el MIME type por su cuenta
    header('X-Content-Type-Options: nosniff');
    // Evita clickjacking
    header('X-Frame-Options: DENY');
    // Habilita el filtro XSS en navegadores antiguos
    header('X-XSS-Protection: 1; mode=block');
    // Solo HTTPS (activar cuando el VPS tenga certificado)
    // header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    // Referrer mínimo
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Content Security Policy: ajustar si se añaden CDNs
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
}

// ── CSRF ──────────────────────────────────────────────────────

function csrf_generar(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_campo(): void {
    $token = csrf_generar();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function csrf_validar(): void {
    $token_enviado = $_POST['csrf_token'] ?? '';
    $token_sesion  = $_SESSION['csrf_token'] ?? '';

    if (empty($token_sesion) ||
        !hash_equals($token_sesion, $token_enviado)) {
        http_response_code(403);
        die("Token CSRF inválido.");
    }
    // Rotar token tras cada uso exitoso
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Rate limiting con APCu ────────────────────────────────────
// Requiere ext-apcu habilitado en PHP.
// Si APCu no está disponible, loguea el aviso pero no bloquea
// (mejor que romper la aplicación en un entorno sin APCu).

function _apcu_disponible(): bool {
    return function_exists('apcu_fetch') && ini_get('apc.enabled');
}

/**
 * Comprueba el rate limit para una acción e IP dados.
 *
 * @param string $accion     Identificador de la acción (ej: 'login', 'flag')
 * @param int    $max        Número máximo de intentos permitidos
 * @param int    $ventana    Ventana de tiempo en segundos
 * @param string $ip         IP del cliente (por defecto, la detectada automáticamente)
 * @return bool  true si se permite la petición, false si se debe bloquear
 */
function rate_limit_check(string $accion, int $max, int $ventana, string $ip = ''): bool {
    if (!_apcu_disponible()) {
        error_log("[SEGURIDAD] APCu no disponible — rate limiting desactivado");
        return true;
    }

    if (empty($ip)) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
        // Tomar solo la primera IP si viene una cadena (proxies)
        $ip = trim(explode(',', $ip)[0]);
    }

    $clave   = "rl:{$accion}:" . md5($ip);
    $intentos = apcu_fetch($clave, $existe);

    if (!$existe) {
        apcu_store($clave, 1, $ventana);
        return true;
    }

    if ($intentos >= $max) {
        return false;
    }

    apcu_inc($clave);
    return true;
}

/**
 * Igual que rate_limit_check pero termina la ejecución si se supera el límite.
 * Redirige al destino indicado con un mensaje de error.
 */
function rate_limit_o_abortar(
    string $accion,
    int    $max,
    int    $ventana,
    string $redirigir_a = '',
    string $mensaje     = 'Demasiados intentos. Espera unos minutos.'
): void {
    if (!rate_limit_check($accion, $max, $ventana)) {
        if ($redirigir_a) {
            header('Location: ' . $redirigir_a . '?error=' . urlencode($mensaje));
            exit;
        }
        http_response_code(429);
        die(htmlspecialchars($mensaje));
    }
}

// ── Verificación de sesión y suscripción ─────────────────────

/**
 * Redirige al login si el usuario no está autenticado.
 */
function requerir_login(): void {
    if (!isset($_SESSION['usuario'])) {
        header('Location: /escape-room/aplicacion/autenticacion/iniciar_sesion.php');
        exit;
    }
}

/**
 * Redirige a la página de pago si el usuario no tiene suscripción activa.
 * Asume que $conexion está disponible en el scope global.
 */
function requerir_suscripcion(PDO $conexion, int $id_usuario): void {
    try {
        $stmt = $conexion->prepare(
            "SELECT suscrito FROM usuarios WHERE id_usuario = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id_usuario]);
        $fila = $stmt->fetch();

        if (!$fila || !$fila['suscrito']) {
            header('Location: /escape-room/aplicacion/pagos/pago.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error verificando suscripción: " . $e->getMessage());
        http_response_code(500);
        die("Error interno del servidor.");
    }
}
?>