import docker
import secrets
import socket
import subprocess as sp
import os

client = docker.from_env()

CHALLENGES = {
    "reto1": {
        "image": "reto1-reto1",
        "flag_seed": "r3c0n_secret",
        "timeout_secs": 1200,
    },
    "reto2": {
        "image": "reto2-reto2",
        "flag_seed": "sqli_secret",
        "timeout_secs": 1800,
    },
    "reto3": {
        "image": "reto3-reto3",
        "flag_seed": "privesc_secret",
        "timeout_secs": 1500,
    },
    "reto4": {
        "image": "reto4-reto4",
        "flag_seed": "sniff_secret",
        "timeout_secs": 1200,
    },
    "reto5": {
        "image": None,
        "flag_seed": "grupal_secret",
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

def spawn_container(user_id: str, challenge_id: str):
    """Lanza un contenedor para un usuario y reto específico."""
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

    return {
        "container_id": container.id,
        "ssh_user": ssh_user,
        "ssh_pass": ssh_pass,
        "ssh_port": port,
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
    """Levanta el entorno grupal con credenciales aleatorias."""
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

    return creds

def stop_reto5():
    """Para el entorno grupal completo."""
    compose_dir = CHALLENGES["reto5"]["compose_dir"]
    sp.run(["docker", "compose", "down"], cwd=compose_dir, check=True)