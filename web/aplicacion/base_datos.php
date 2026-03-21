<?php
// ── Configuración de conexión ────────────────────────────────
// Rellena estos valores con los datos de tu hosting (Byethost)
$host     = "localhost";
$bd       = "escape_db";
$usuario  = "escape_user";
$contrasena = "escape_pass";

try {
    $conexion = new PDO(
        "mysql:host=$host;dbname=$bd;charset=utf8mb4",
        $usuario,
        $contrasena
    );
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conexion->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

} catch (PDOException $e) {
    // En producción nunca mostrar el error real
    error_log("Error de conexión BD: " . $e->getMessage());
    die("Error de conexión con la base de datos.");
}
?>