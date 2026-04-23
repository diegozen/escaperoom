<?php
// ── Credenciales desde variables de entorno ──────────────────
// Las variables se definen en /etc/apache2/envvars o en el
// VirtualHost con SetEnv, nunca hardcodeadas aquí.
// En desarrollo local usa un .env cargado por el servidor.

function _require_env(string $key): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        error_log("Variable de entorno '$key' no definida");
        http_response_code(500);
        die("Error de configuración del servidor.");
    }
    return $value;
}

$host      = getenv('DB_HOST')     ?: '127.0.0.1';
$puerto    = getenv('DB_PORT')     ?: '3306';
$bd        = _require_env('DB_NAME');
$usuario   = _require_env('DB_USER');
$contrasena = _require_env('DB_PASSWORD');

try {
    $conexion = new PDO(
        "mysql:host=$host;port=$puerto;dbname=$bd;charset=utf8mb4",
        $usuario,
        $contrasena
    );
    $conexion->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conexion->setAttribute(PDO::ATTR_EMULATE_PREPARES,   false);

} catch (PDOException $e) {
    error_log("Error de conexión BD: " . $e->getMessage());
    http_response_code(500);
    die("Error de conexión con la base de datos.");
}
?>