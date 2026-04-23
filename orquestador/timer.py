import threading
from datetime import datetime
from manager import stop_container

# ── Lock para acceso seguro al dict desde múltiples hilos ────
_lock         = threading.Lock()
active_timers: dict[int, threading.Timer] = {}


def _on_timeout(session_id: int, container_id: str, db_factory):
    """
    Callback ejecutado cuando expira el tiempo de una sesión.
    Detiene el contenedor y marca la sesión como 'timeout' en BD.
    Cualquier excepción se loguea pero no se propaga (hilo daemon).
    """
    print(f"[TIMEOUT] Sesión {session_id} — deteniendo contenedor {container_id[:12]}")

    # Eliminar el timer del dict antes de cualquier I/O
    with _lock:
        active_timers.pop(session_id, None)

    # Detener contenedor
    try:
        stop_container(container_id)
    except Exception as e:
        print(f"[TIMEOUT] Error deteniendo contenedor {container_id[:12]}: {e}")

    # Actualizar BD — usamos el factory directamente, no next(generador)
    db = None
    try:
        db = db_factory()
        from database import Session
        session = db.query(Session).filter(Session.id_sesion == session_id).first()
        if session and session.status == "running":
            session.status       = "timeout"
            session.finished_at  = datetime.utcnow()
            session.elapsed_secs = int(
                (session.finished_at - session.started_at).total_seconds()
            )
            db.commit()
            print(f"[TIMEOUT] Sesión {session_id} marcada como timeout")
        else:
            print(f"[TIMEOUT] Sesión {session_id} ya no estaba en running, ignorando")
    except Exception as e:
        print(f"[TIMEOUT] Error actualizando BD para sesión {session_id}: {e}")
        if db:
            try:
                db.rollback()
            except Exception:
                pass
    finally:
        if db:
            try:
                db.close()
            except Exception:
                pass


def start_timer(
    session_id:   int,
    container_id: str,
    timeout_secs: int,
    db_factory,          # callable que devuelve una SQLAlchemy Session ya abierta
):
    """
    Inicia el temporizador para una sesión.
    db_factory debe ser un callable sin argumentos que devuelva
    una Session de SQLAlchemy (no un generador).
    """
    timer = threading.Timer(
        timeout_secs,
        _on_timeout,
        args=[session_id, container_id, db_factory],
    )
    timer.daemon = True
    timer.start()

    with _lock:
        active_timers[session_id] = timer

    print(f"[TIMER] Sesión {session_id} — {timeout_secs}s ({timeout_secs // 60}m)")


def cancel_timer(session_id: int) -> bool:
    """
    Cancela el temporizador de una sesión si existe.
    Devuelve True si había un timer activo, False si no existía.
    """
    with _lock:
        timer = active_timers.pop(session_id, None)

    if timer:
        timer.cancel()
        print(f"[TIMER] Cancelado — sesión {session_id}")
        return True

    return False


def active_timer_count() -> int:
    """Devuelve el número de timers activos. Útil para monitoreo."""
    with _lock:
        return len(active_timers)