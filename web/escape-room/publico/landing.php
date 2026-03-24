<?php
require "../aplicacion/plantillas/cabecera.php";
?>

<link rel="stylesheet" href="/escape-room/publico/css/landing.css">

<!-- ── Hero ──────────────────────────────────────────────────── -->
<section class="hero">
    <div class="hero-fondo">
        <div class="hero-grid"></div>
    </div>
    <div class="hero-contenido contenedor">
        <div class="hero-etiqueta">
            <span class="punto-verde"></span>
            Sistema activo — retos disponibles
        </div>
        <h1 class="hero-titulo">
            Escape Room<br><span class="acento">Tecnológico</span>
        </h1>
        <p class="hero-subtitulo">
            Retos reales de redes y ciberseguridad en entornos aislados.<br>
            Conéctate, explota, escala. Compite por el mejor tiempo.
        </p>
        <div class="hero-acciones">
            <?php if (isset($_SESSION["usuario"])): ?>
                <a class="btn btn-primary" href="/escape-room/aplicacion/pagos/pago.php">
                    Acceder al laboratorio
                </a>
            <?php else: ?>
                <a class="btn btn-primary" href="/escape-room/aplicacion/autenticacion/registrarse.php">
                    Crear cuenta
                </a>
                <a class="btn btn-ghost" href="/escape-room/aplicacion/autenticacion/iniciar_sesion.php">
                    Iniciar sesión
                </a>
            <?php endif; ?>
        </div>
        <div class="hero-stats">
            <div class="stat">
                <span class="stat-valor">5</span>
                <span class="stat-label">Retos</span>
            </div>
            <div class="stat-divisor"></div>
            <div class="stat">
                <span class="stat-valor">SSH</span>
                <span class="stat-label">Acceso remoto</span>
            </div>
            <div class="stat-divisor"></div>
            <div class="stat">
                <span class="stat-valor">Docker</span>
                <span class="stat-label">Entorno aislado</span>
            </div>
        </div>
    </div>
</section>

<!-- ── Cómo funciona ─────────────────────────────────────────── -->
<section class="como-funciona">
    <div class="contenedor">
        <div class="seccion-cabecera">
            <span class="etiqueta etiqueta-verde">Proceso</span>
            <h2 class="seccion-titulo">Cómo funciona</h2>
            <p class="seccion-subtitulo">
                Cada reto se ejecuta en un contenedor Docker aislado.<br>
                Accedes por SSH con credenciales únicas y compites contra el tiempo.
            </p>
        </div>

        <div class="pasos">
            <div class="paso">
                <div class="paso-numero">01</div>
                <div class="paso-contenido">
                    <h3>Regístrate</h3>
                    <p>Crea tu cuenta y accede al laboratorio. Cada usuario tiene su propia sesión independiente.</p>
                </div>
            </div>

            <div class="paso-conector"></div>

            <div class="paso">
                <div class="paso-numero">02</div>
                <div class="paso-contenido">
                    <h3>Selecciona un reto</h3>
                    <p>Elige entre los retos disponibles. El sistema levanta un contenedor exclusivo para ti.</p>
                </div>
            </div>

            <div class="paso-conector"></div>

            <div class="paso">
                <div class="paso-numero">03</div>
                <div class="paso-contenido">
                    <h3>Conéctate por SSH</h3>
                    <p>Recibes credenciales únicas. Accede al entorno y resuelve el reto usando tus conocimientos.</p>
                </div>
            </div>

            <div class="paso-conector"></div>

            <div class="paso">
                <div class="paso-numero">04</div>
                <div class="paso-contenido">
                    <h3>Valida la flag</h3>
                    <p>Encuentra la flag oculta, introdúcela en la plataforma y queda registrado en el ranking.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require "../aplicacion/plantillas/pie.php";
?>