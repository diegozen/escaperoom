import docker
import secrets
import socket
import subprocess as sp
import os
import time

client = docker.from_env()

CHALLENGES = {
    "reto1": {
        "image":        "reto1-reto1",
        "flag_seed":    "r3c0n_secret",
        "timeout_secs": 1200,
    },
    "reto2": {
        "image":        "reto2-reto2",
        "flag_seed":    "sql1_secret",
        "timeout_secs": 1800,
    },
    "reto3": {
        "image":        "reto3-reto3",
        "flag_seed":    "pr1v3sc_secret",
        "timeout_secs": 1500,
    },
    "reto4": {
        "image":        "reto4-reto4",
        "flag_seed":    "sn1ff_secret",
        "timeout_secs": 1200,
        # El usuario que el jugador necesita suplantar para leer la flag
        "target_user":  "admin_ftp",
    },
    "reto5": {
        "image":        None,
        "flag_seed":    "grup4l_secret",
        "timeout_secs": 2700,
        "tipo":         "grupal",
        "compose_dir":  "/opt/docker/escape-room/challenges/reto5",
        "puertos": {
            "jugador1": 2205,
            "jugador2": 2206,
            "jugador3": 2207,
        },
    },
}

# Capabilities necesarias para los contenedores de retos
# NET_ADMIN: iptables dentro del contenedor
# NET_RAW:   tcpdump / captura de paquetes (reto4)
CAP_ADD = ["NET_ADMIN", "NET_RAW"]

HEALTHY_TIMEOUT = 60
HEALTHY_POLL    = 2


def get_free_port(start: int = 2210, end: int = 2299) -> int:
    """Devuelve el primer puerto TCP libre en el rango dado."""
    for port in range(start, end):
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            if s.connect_ex(("localhost", port)) != 0:
                return port
    raise RuntimeError("No hay puertos libres en el rango 2210-2299")


def generate_credentials() -> tuple[str, str]:
    """Genera usuario y contraseña únicos y seguros."""
    ssh_user = f"player_{secrets.token_hex(3)}"
    # token_hex en lugar de token_urlsafe para evitar caracteres especiales
    # que rompan scripts bash dentro del contenedor (ej: $, !, `)
    ssh_pass = secrets.token_hex(8)   # 16 chars hex, siempre alfanumérico
    return ssh_user, ssh_pass


def generate_ftp_pass() -> str:
    """Genera contraseña FTP segura y sin caracteres especiales."""
    return secrets.token_hex(8)


def wait_healthy(container_id: str, timeout: int = HEALTHY_TIMEOUT) -> bool:
    """
    Espera a que el contenedor reporte 'healthy'.
    Devuelve True si lo consigue, False si expira o queda 'unhealthy'.
    Si el contenedor no tiene healthcheck definido devuelve True inmediatamente.
    """
    deadline = time.time() + timeout
    while time.time() < deadline:
        try:
            container = client.containers.get(container_id)
            health    = container.attrs.get("State", {}).get("Health")

            if health is None:
                return True  # sin healthcheck definido

            status = health.get("Status", "")
            if status == "healthy":
                return True
            if status == "unhealthy":
                print(f"[HEALTH] {container_id[:12]} → unhealthy")
                return False

        except Exception as e:
            print(f"[HEALTH] Error consultando {container_id[:12]}: {e}")
            return False

        time.sleep(HEALTHY_POLL)

    print(f"[HEALTH] Timeout esperando healthy en {container_id[:12]}")
    return False


def spawn_container(user_id: str, challenge_id: str) -> dict:
    """
    Lanza un contenedor para retos individuales (1-4).
    Espera a que esté healthy antes de devolver las credenciales.
    """
    if challenge_id not in CHALLENGES:
        raise ValueError(f"Reto '{challenge_id}' no existe")

    if CHALLENGES[challenge_id].get("tipo") == "grupal":
        raise ValueError("El reto5 es grupal, usa /challenge/reto5/start")

    challenge        = CHALLENGES[challenge_id]
    ssh_user, ssh_pass = generate_credentials()
    ftp_pass         = generate_ftp_pass()
    port             = get_free_port()

    env = {
        "SSH_USER": ssh_user,
        "SSH_PASS": ssh_pass,
        "FLAG":     challenge["flag_seed"],
    }
    # reto4 necesita FTP_PASS para el servidor simulado y trafico.sh
    if challenge_id == "reto4":
        env["FTP_PASS"] = ftp_pass

    container = client.containers.run(
        image      = challenge["image"],
        detach     = True,
        ports      = {"22/tcp": port},
        environment= env,
        name       = f"session_{user_id}_{challenge_id}",
        mem_limit  = "512m",
        nano_cpus  = 500_000_000,
        cap_add    = CAP_ADD,   # NET_ADMIN + NET_RAW para iptables y tcpdump
        # Evitar que el contenedor tenga acceso a red exterior por defecto
        # (el aislamiento fino lo hace iptables dentro del contenedor)
    )

    print(f"[SPAWN] {container.id[:12]} lanzado para {challenge_id} — esperando healthy…")
    ok = wait_healthy(container.id)

    if not ok:
        try:
            container.stop(timeout=3)
            container.remove()
        except Exception:
            pass
        raise RuntimeError(f"Contenedor {challenge_id} no alcanzó estado healthy")

    print(f"[SPAWN] {container.id[:12]} healthy")

    ssh_host = os.environ.get("SSH_HOST", "localhost")
    
    result = {
        "container_id": container.id,
        "ssh_user":     ssh_user,
        "ssh_pass":     ssh_pass,
        "ssh_port":     port,
        "ssh_port":     ssh_host,
    }
    # Devolver ftp_pass solo para reto4 (se almacena en sesión para mostrar
    # al jugador si necesita soporte, pero NO se expone en la pantalla normal)
    if challenge_id == "reto4":
        result["ftp_target_user"] = "admin_ftp"

    return result


def stop_container(container_id: str) -> bool:
    """Detiene y elimina un contenedor por ID."""
    try:
        container = client.containers.get(container_id)
        container.stop(timeout=5)
        container.remove()
        return True
    except docker.errors.NotFound:
        print(f"[STOP] Contenedor {container_id[:12]} ya no existe")
        return True
    except Exception as e:
        print(f"[STOP] Error deteniendo {container_id}: {e}")
        return False


def spawn_reto5() -> dict:
    """
    Levanta el entorno grupal con docker compose.
    Espera a que los tres jugadores estén healthy.
    """
    compose_dir = CHALLENGES["reto5"]["compose_dir"]

    creds = {
        f"jugador{i}": {
            "ssh_port": 2204 + i,
            "ssh_user": f"jugador{i}",
            "ssh_pass": secrets.token_hex(8),   # hex: sin caracteres especiales
        }
        for i in range(1, 4)
    }

    env = {
        "PASS_J1": creds["jugador1"]["ssh_pass"],
        "PASS_J2": creds["jugador2"]["ssh_pass"],
        "PASS_J3": creds["jugador3"]["ssh_pass"],
    }

    sp.run(
        ["docker", "compose", "up", "-d", "--build"],
        cwd   = compose_dir,
        env   = {**os.environ, **env},
        check = True,
    )

    jugadores = [
        "escape_reto5_jugador1",
        "escape_reto5_jugador2",
        "escape_reto5_jugador3",
    ]
    for nombre in jugadores:
        print(f"[SPAWN] Esperando healthy en {nombre}…")
        try:
            container = client.containers.get(nombre)
            if not wait_healthy(container.id):
                raise RuntimeError(f"{nombre} no llegó a healthy")
            print(f"[SPAWN] {nombre} OK")
        except docker.errors.NotFound:
            raise RuntimeError(f"Contenedor {nombre} no encontrado tras compose up")

    return creds


def stop_reto5() -> None:
    """Para y elimina el entorno grupal completo."""
    compose_dir = CHALLENGES["reto5"]["compose_dir"]
    sp.run(
        ["docker", "compose", "down", "--remove-orphans"],
        cwd   = compose_dir,
        check = True,
    )