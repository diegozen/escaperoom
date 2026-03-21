import threading
from datetime import datetime, timedelta
from manager import stop_container

# Diccionario de timers activos: {session_id: threading.Timer}
active_timers = {}

def _on_timeout(session_id: int, container_id: str, db_session_factory):
    """Callback que se ejecuta cuando expira el tiempo."""
    print(f"[TIMEOUT] Sesión {session_id}")
    stop_container(container_id)

    db = db_session_factory()
    try:
        from database import Session
        session = db.query(Session).filter(Session.id == session_id).first()
        if session and session.status == "running":
            session.status = "timeout"
            session.finished_at = datetime.utcnow()
            elapsed = (session.finished_at - session.started_at).seconds
            session.elapsed_secs = elapsed
            db.commit()
    finally:
        db.close()

    active_timers.pop(session_id, None)

def start_timer(session_id: int, container_id: str, timeout_secs: int, db_session_factory):
    """Inicia el temporizador para una sesión."""
    timer = threading.Timer(
        timeout_secs,
        _on_timeout,
        args=[session_id, container_id, db_session_factory]
    )
    timer.daemon = True
    timer.start()
    active_timers[session_id] = timer
    print(f"[TIMER] Sesión {session_id} — {timeout_secs}s")

def cancel_timer(session_id: int):
    """Cancela el temporizador de una sesión."""
    timer = active_timers.pop(session_id, None)
    if timer:
        timer.cancel()
        print(f"[TIMER] Cancelado sesión {session_id}")