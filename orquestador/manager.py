import docker
import secrets
import socket
import subprocess as sp
import os
import time

client = docker.from_env()

CHALLENGES = {
    "reto1": {
        "image": "reto1-reto1",
        "flag_seed": "r3c0n_secret",
        "timeout_secs": 1200,
    },
    "reto2": {
        "image": "reto2-reto2",
        "flag_seed": "sql1_secret",
        "timeout_secs": 1800,
    },
    "reto3": {
        "image": "reto3-reto3",
        "flag_seed": "pr1v3sc_secret",
        "timeout_secs": 1500,
    },
    "reto4": {
        "image": "reto4-reto4",
        "flag_seed": "sn1ff_secret",
        "timeout_secs": 1200,
    },
    "reto5": {
        "image": None,
        "flag_seed": "grup4l_secret",
        "timeout_secs": 2700,
        "tipo": "grupal",
        "compose_dir": "/opt/docker/escape-room/challenges/reto5",
        "puertos": {
            "jugador1": 2205,
            "jugador2": 2206,
            "jugador3": 2207,
        }
    },
}

# Tiempo máximo en segundos esperando a que un contenedor sea healthy
HEALTHY_TIMEOUT = 60
HEALTHY_POLL    = 2


def get_free_port(start=2210, end=2299):
    """Busca un puerto libre en el rango dado."""
    for port in range(start, end):
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            if s.connect_ex(("localhost", port)) != 0:
                return port
    raise Exception("No hay puertos disponibles")


def generate_credentials():
    """Genera usuario y contraseña únicos."""
    ssh_user = f"player_{secrets.token_hex(3)}"
    ssh_pass = secrets.token_urlsafe(10)
    return ssh_user, ssh_pass


def wait_healthy(container_id: str, timeout: int = HEALTHY_TIMEOUT) -> bool:
    """
    Espera hasta que el contenedor reporte status 'healthy'.
    Devuelve True si lo consigue antes del timeout, False si expira.
    Si el contenedor no tiene healthcheck definido devuelve True inmediatamente.
    """
    deadline = time.time() + timeout
    while time.time() < deadline:
        try:
            container = client.containers.get(container_id)
            health = container.attrs.get("State", {}).get("Health", None)

            # Sin healthcheck definido — no hay nada que esperar
            if health is None:
                return True

            status = health.get("Status", "")
            if status == "healthy":
                return True
            if status == "unhealthy":
                print(f"[HEALTH] Contenedor {container_id[:12]} marcado unhealthy")
                return False

        except Exception as e:
            print(f"[HEALTH] Error consultando estado: {e}")
            return False

        time.sleep(HEALTHY_POLL)

    print(f"[HEALTH] Timeout esperando healthy en {container_id[:12]}")
    return False


def spawn_container(user_id: str, challenge_id: str):
    """Lanza un contenedor y espera a que esté healthy antes de devolver credenciales."""
    if challenge_id not in CHALLENGES:
        raise Exception(f"Reto {challenge_id} no existe")

    if CHALLENGES[challenge_id].get("tipo") == "grupal":
        raise Exception("El reto5 es grupal, usa /challenge/reto5/start")

    challenge = CHALLENGES[challenge_id]
    ssh_user, ssh_pass = generate_credentials()
    port = get_free_port()

    container = client.containers.run(
        image=challenge["image"],
        detach=True,
        ports={"22/tcp": port},
        environment={
            "SSH_USER": ssh_user,
            "SSH_PASS": ssh_pass,
            "FLAG": challenge["flag_seed"],
        },
        name=f"session_{user_id}_{challenge_id}",
        mem_limit="512m",
        nano_cpus=500_000_000,
    )

    print(f"[SPAWN] {container.id[:12]} lanzado — esperando healthy...")
    ok = wait_healthy(container.id)

    if not ok:
        # Limpiar el contenedor si no llega a healthy
        try:
            container.stop(timeout=3)
            container.remove()
        except Exception:
            pass
        raise Exception(f"El contenedor del {challenge_id} no arrancó correctamente")

    print(f"[SPAWN] {container.id[:12]} healthy — devolviendo credenciales")

    return {
        "container_id": container.id,
        "ssh_user":     ssh_user,
        "ssh_pass":     ssh_pass,
        "ssh_port":     port,
    }


def stop_container(container_id: str):
    """Detiene y elimina un contenedor."""
    try:
        container = client.containers.get(container_id)
        container.stop(timeout=5)
        container.remove()
        return True
    except Exception as e:
        print(f"Error deteniendo contenedor {container_id}: {e}")
        return False


def spawn_reto5():
    """Levanta el entorno grupal y espera a que todos los jugadores estén healthy."""
    compose_dir = CHALLENGES["reto5"]["compose_dir"]

    creds = {
        f"jugador{i}": {
            "ssh_port": 2204 + i,
            "ssh_user": f"jugador{i}",
            "ssh_pass": secrets.token_urlsafe(10)
        }
        for i in range(1, 4)
    }

    env = {
        "PASS_J1": creds["jugador1"]["ssh_pass"],
        "PASS_J2": creds["jugador2"]["ssh_pass"],
        "PASS_J3": creds["jugador3"]["ssh_pass"],
    }

    sp.run(
        ["docker", "compose", "up", "-d"],
        cwd=compose_dir,
        env={**os.environ, **env},
        check=True
    )

    # Esperar a que los tres jugadores estén healthy
    jugadores = ["escape_reto5_jugador1", "escape_reto5_jugador2", "escape_reto5_jugador3"]
    for nombre in jugadores:
        print(f"[SPAWN] Esperando healthy en {nombre}...")
        try:
            container = client.containers.get(nombre)
            ok = wait_healthy(container.id)
            if not ok:
                raise Exception(f"{nombre} no llegó a estado healthy")
            print(f"[SPAWN] {nombre} healthy")
        except docker.errors.NotFound:
            raise Exception(f"Contenedor {nombre} no encontrado tras docker compose up")

    return creds


def stop_reto5():
    """Para el entorno grupal completo."""
    compose_dir = CHALLENGES["reto5"]["compose_dir"]
    sp.run(["docker", "compose", "down"], cwd=compose_dir, check=True)