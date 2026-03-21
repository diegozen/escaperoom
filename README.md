# 🖥️ Escape Room Tecnológico

Plataforma web de retos de ciberseguridad y redes al estilo Hack The Box / TryHackMe. Cada reto se ejecuta en un contenedor Docker aislado con credenciales SSH únicas por sesión. Los usuarios compiten por el mejor tiempo en un ranking global.

---

## Características

- 5 retos de dificultad progresiva (reconocimiento, SQLi, privesc, sniffing, ataque grupal)
- Entornos completamente aislados mediante Docker
- Credenciales SSH únicas generadas por sesión
- Temporizador automático con expiración de contenedor
- Validación de flags por hash MD5 único por usuario
- Ranking global por reto y progreso personal
- Suscripción simulada de acceso (5 €/mes)
- Orquestador FastAPI que gestiona el ciclo de vida de los contenedores

---

## Árbol del proyecto

```
/opt/docker/escape-room
├── challenges/
│   ├── reto1/          # Reconocimiento de red
│   ├── reto2/          # Explotación web (SQLi)
│   ├── reto3/          # Escalada de privilegios (SUID)
│   ├── reto4/          # Sniffing de tráfico (tcpdump)
│   └── reto5/          # Ataque coordinado grupal
├── orquestador/        # API FastAPI — gestión de contenedores
└── web/                # Frontend PHP
    ├── publico/        # Landing page
    └── aplicacion/     # Autenticación, laboratorio, ranking, pagos
```

---

## Retos

| # | Nombre | Técnica | Dificultad | Tiempo |
|---|--------|---------|------------|--------|
| 1 | Reconocimiento de red | nmap, netcat, enumeración de puertos | Básico | 20 min |
| 2 | Explotación web | SQL Injection sobre Flask + SQLite | Intermedio | 30 min |
| 3 | Escalada de privilegios | Binario SUID (`bash_suid -p`) | Intermedio | 25 min |
| 4 | Sniffing de tráfico | tcpdump, captura de credenciales FTP en claro | Intermedio | 20 min |
| 5 | Ataque coordinado | FTP anónimo → SSH → sudo misconfiguration (grupal) | Avanzado | 45 min |

Cada flag tiene el formato `FLAG{xxxxxxxxxxxx}` y es única por usuario (MD5 de `ssh_user + flag_seed`).

---

## Stack tecnológico

| Capa | Tecnología |
|------|-----------|
| Frontend | PHP 8, HTML/CSS (JetBrains Mono + Syne) |
| Base de datos web | MySQL — base `escape_db` |
| Orquestador | Python 3.12, FastAPI, SQLAlchemy, PyMySQL |
| Contenedores | Docker, Docker Compose |
| Retos | Ubuntu 22.04, OpenSSH, Python 3, Flask, tcpdump, vsftpd |

---

## Requisitos

- Docker y Docker Compose instalados en el host
- PHP 8+ con extensiones `pdo_mysql` y `curl`
- Python 3.12+ con los paquetes del orquestador
- MySQL con la base de datos `escape_db` inicializada
- Puerto 8000 disponible para el orquestador (FastAPI)
- Puertos 2201–2207 disponibles para SSH de los retos

---

## Instalación

### 1. Base de datos

```sql
CREATE DATABASE escape_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'escape_user'@'localhost' IDENTIFIED BY 'escape_pass';
GRANT ALL PRIVILEGES ON escape_db.* TO 'escape_user'@'localhost';
FLUSH PRIVILEGES;
```

```bash
mysql -u escape_user -p escape_db < web/squema.sql
```

### 2. Configurar credenciales

Edita `web/aplicacion/base_datos.php` con los datos reales de tu entorno:

```php
$host      = "localhost";
$bd        = "escape_db";
$usuario   = "escape_user";
$contrasena = "escape_pass";
```

Edita `orquestador/database.py` con los mismos datos:

```python
DB_HOST     = "localhost"
DB_NAME     = "escape_db"
DB_USER     = "escape_user"
DB_PASSWORD = "escape_pass"
```

### 3. Construir imágenes Docker

```bash
cd challenges/reto1 && docker compose build
cd ../reto2 && docker compose build
cd ../reto3 && docker compose build
cd ../reto4 && docker compose build
cd ../reto5 && docker compose build
```

### 4. Instalar dependencias del orquestador

```bash
cd orquestador
pip install fastapi uvicorn sqlalchemy pymysql docker
```

### 5. Arrancar el orquestador

```bash
cd orquestador
uvicorn main:app --host 127.0.0.1 --port 8000
```

Para producción se recomienda ejecutarlo como servicio systemd:

```ini
[Unit]
Description=Escape Room Orquestador
After=network.target

[Service]
WorkingDirectory=/opt/docker/escape-room/orquestador
ExecStart=/usr/bin/uvicorn main:app --host 127.0.0.1 --port 8000
Restart=always

[Install]
WantedBy=multi-user.target
```

### 6. Servidor web

Configura Apache o Nginx para servir `web/` bajo `/escape-room/` y asegúrate de que PHP tiene acceso a `curl` y `pdo_mysql`.

---

## Flujo de uso

```
Usuario se registra
       ↓
Página de suscripción (5 €) — activa campo suscrito en BD
       ↓
Laboratorio — selecciona reto
       ↓
iniciar_reto.php → POST /challenge/start (orquestador)
       ↓
Orquestador lanza contenedor Docker con credenciales únicas
       ↓
Usuario se conecta por SSH y resuelve el reto
       ↓
validar_flag.php → POST /challenge/validate (orquestador)
       ↓
Orquestador verifica hash, detiene contenedor, registra tiempo
       ↓
Resultado visible en ranking global
```

---

## API del orquestador

El orquestador FastAPI expone los siguientes endpoints en `localhost:8000`:

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/challenge/start` | Lanza contenedor para un usuario y reto |
| POST | `/challenge/abort` | Detiene y elimina el contenedor |
| POST | `/challenge/validate` | Valida la flag y cierra la sesión |
| GET | `/challenge/status/{user_id}/{challenge_id}` | Estado de la sesión activa |
| POST | `/challenge/reto5/start` | Lanza el entorno grupal (3 jugadores) |
| POST | `/challenge/reto5/abort` | Detiene el entorno grupal |
| POST | `/challenge/reto5/validate` | Valida flag grupal (parcial o final) |
| GET | `/ranking` | Listado de sesiones completadas |
| GET | `/admin/sessions` | Listado completo de sesiones |

Documentación interactiva disponible en `http://localhost:8000/docs` (Swagger UI).

---

## Estructura de la base de datos

```
usuarios        — cuentas de usuario (suscrito TINYINT)
sesiones_reto   — sesiones activas e historial de retos
salas           — salas disponibles (por defecto: Laboratorio Principal)
partidas        — partidas asociadas a usuarios y salas
ranking         — tiempos registrados por partida completada
```

---

## Seguridad

- Las flags son únicas por usuario y se calculan como `MD5(ssh_user + flag_seed)` — no son reutilizables entre sesiones.
- El tráfico saliente de cada contenedor está restringido con `iptables` (solo redes Docker internas).
- Las contraseñas de usuario se almacenan con `password_hash()` de PHP (bcrypt).
- El orquestador solo escucha en `localhost` y no está expuesto al exterior.
- Los contenedores tienen límites de memoria (256–512 MB) y CPU (0.3–0.5 cores).

---

## Licencia

Proyecto de uso educativo. No distribuir en entornos de producción sin revisar la configuración de seguridad del host Docker.
