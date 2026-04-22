from fastapi import FastAPI, Depends, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, field_validator
from sqlalchemy.orm import Session as DBSession
from datetime import datetime
import hashlib
import re

from database import get_db, init_db, Session
from manager  import spawn_container, stop_container, CHALLENGES, spawn_reto5, stop_reto5
from timer    import start_timer, cancel_timer

app = FastAPI(title="Escape Room API", docs_url=None, redoc_url=None)

# CORS: solo el origen de la web PHP
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost", "http://127.0.0.1"],
    allow_methods=["POST", "GET"],
    allow_headers=["Content-Type"],
)

# ── Validación de challenge_id ────────────────────────────────
VALID_CHALLENGES = {"reto1", "reto2", "reto3", "reto4", "reto5"}
CHALLENGE_RE     = re.compile(r"^reto[1-5]$")

# ── Modelos de request ────────────────────────────────────────

class StartRequest(BaseModel):
    user_id:      str
    challenge_id: str

    @field_validator("challenge_id")
    @classmethod
    def validate_challenge(cls, v: str) -> str:
        if not CHALLENGE_RE.match(v):
            raise ValueError("challenge_id inválido")
        return v

    @field_validator("user_id")
    @classmethod
    def validate_user_id(cls, v: str) -> str:
        if not v.isdigit() or len(v) > 10:
            raise ValueError("user_id inválido")
        return v


class AbortRequest(BaseModel):
    user_id:      str
    challenge_id: str

    @field_validator("challenge_id")
    @classmethod
    def validate_challenge(cls, v: str) -> str:
        if not CHALLENGE_RE.match(v):
            raise ValueError("challenge_id inválido")
        return v

    @field_validator("user_id")
    @classmethod
    def validate_user_id(cls, v: str) -> str:
        if not v.isdigit() or len(v) > 10:
            raise ValueError("user_id inválido")
        return v


class ValidateRequest(BaseModel):
    user_id:      str
    challenge_id: str
    flag:         str

    @field_validator("flag")
    @classmethod
    def validate_flag_format(cls, v: str) -> str:
        v = v.strip()
        if len(v) > 200:
            raise ValueError("flag demasiado larga")
        return v

    @field_validator("challenge_id")
    @classmethod
    def validate_challenge(cls, v: str) -> str:
        if not CHALLENGE_RE.match(v):
            raise ValueError("challenge_id inválido")
        return v

    @field_validator("user_id")
    @classmethod
    def validate_user_id(cls, v: str) -> str:
        if not v.isdigit() or len(v) > 10:
            raise ValueError("user_id inválido")
        return v


# ── Startup ───────────────────────────────────────────────────

@app.on_event("startup")
def startup():
    init_db()


# ── Helpers ───────────────────────────────────────────────────

def _compute_flag(ssh_user: str, flag_seed: str) -> str:
    """Calcula la flag esperada igual que el setup.sh de cada reto."""
    flag_input = f"{ssh_user}{flag_seed}\n"
    return f"FLAG{{{hashlib.md5(flag_input.encode()).hexdigest()[:12]}}}"


def _finish_session(session: Session, status: str, db: DBSession):
    session.status       = status
    session.finished_at  = datetime.utcnow()
    session.elapsed_secs = int((session.finished_at - session.started_at).total_seconds())
    db.commit()


# ── Retos individuales ────────────────────────────────────────

@app.post("/challenge/start")
def start_challenge(req: StartRequest, db: DBSession = Depends(get_db)):
    if req.challenge_id == "reto5":
        raise HTTPException(400, "El reto5 es grupal, usa /challenge/reto5/start")

    existing = db.query(Session).filter(
        Session.id_usuario   == req.user_id,
        Session.challenge_id == req.challenge_id,
        Session.status       == "running",
    ).first()
    if existing:
        raise HTTPException(400, "Ya tienes una sesión activa para este reto")

    try:
        result = spawn_container(req.user_id, req.challenge_id)
    except Exception as e:
        raise HTTPException(500, f"Error al lanzar el contenedor: {e}")

    session = Session(
        id_usuario   = req.user_id,
        challenge_id = req.challenge_id,
        container_id = result["container_id"],
        ssh_port     = result["ssh_port"],
        ssh_user     = result["ssh_user"],
        ssh_pass     = result["ssh_pass"],
        status       = "running",
        started_at   = datetime.utcnow(),
    )
    db.add(session)
    db.commit()
    db.refresh(session)

    timeout = CHALLENGES[req.challenge_id]["timeout_secs"]

    def db_factory():
        return next(get_db())

    start_timer(session.id_sesion, result["container_id"], timeout, db_factory)

    return {
        "message":      "Contenedor lanzado",
        "host":         "localhost",
        "port":         result["ssh_port"],
        "user":         result["ssh_user"],
        "password":     result["ssh_pass"],
        "timeout_secs": timeout,
    }


@app.post("/challenge/abort")
def abort_challenge(req: AbortRequest, db: DBSession = Depends(get_db)):
    session = db.query(Session).filter(
        Session.id_usuario   == req.user_id,
        Session.challenge_id == req.challenge_id,
        Session.status       == "running",
    ).first()
    if not session:
        raise HTTPException(404, "No hay sesión activa para este reto")

    cancel_timer(session.id_sesion)
    stop_container(session.container_id)
    _finish_session(session, "aborted", db)

    return {"message": "Reto abortado"}


@app.post("/challenge/validate")
def validate_flag(req: ValidateRequest, db: DBSession = Depends(get_db)):
    if req.challenge_id == "reto5":
        raise HTTPException(400, "Usa /challenge/reto5/validate para el reto grupal")

    session = db.query(Session).filter(
        Session.id_usuario   == req.user_id,
        Session.challenge_id == req.challenge_id,
        Session.status       == "running",
    ).first()
    if not session:
        raise HTTPException(404, "No hay sesión activa para este reto")

    flag_seed = CHALLENGES[req.challenge_id]["flag_seed"]
    expected  = _compute_flag(session.ssh_user, flag_seed)

    if req.flag != expected:
        return {"success": False, "message": "Flag incorrecta. Sigue intentándolo."}

    cancel_timer(session.id_sesion)
    stop_container(session.container_id)
    session.flag_used = req.flag
    _finish_session(session, "completed", db)

    mins = session.elapsed_secs // 60
    secs = session.elapsed_secs % 60
    return {
        "success":         True,
        "message":         "¡Reto completado!",
        "elapsed_seconds": session.elapsed_secs,
        "elapsed_human":   f"{mins}m {secs:02d}s",
    }


@app.get("/challenge/status/{user_id}/{challenge_id}")
def get_status(user_id: str, challenge_id: str, db: DBSession = Depends(get_db)):
    # Validación básica de parámetros de ruta
    if not user_id.isdigit() or not CHALLENGE_RE.match(challenge_id):
        raise HTTPException(400, "Parámetros inválidos")

    session = db.query(Session).filter(
        Session.id_usuario   == user_id,
        Session.challenge_id == challenge_id,
    ).order_by(Session.started_at.desc()).first()

    if not session:
        raise HTTPException(404, "Sesión no encontrada")

    elapsed = None
    if session.status == "running" and session.started_at:
        elapsed = int((datetime.utcnow() - session.started_at).total_seconds())

    # ── Fix reto4: el usuario SSH del jugador ≠ usuario objetivo de la flag ──
    # ssh_user es el del jugador (player_xxxxxx).
    # Para reto4 informamos también del usuario FTP objetivo.
    response = {
        "status":       session.status,
        "elapsed_secs": elapsed if elapsed is not None else session.elapsed_secs,
        "ssh_port":     session.ssh_port,
        "ssh_user":     session.ssh_user,   # usuario con el que se conecta por SSH
    }
    if challenge_id == "reto4":
        # El jugador conecta por SSH como ssh_user, pero debe pivotar a admin_ftp
        response["target_user"] = CHALLENGES["reto4"].get("target_user", "admin_ftp")

    return response


# ── Reto grupal (reto5) ───────────────────────────────────────

@app.post("/challenge/reto5/start")
def start_reto5(db: DBSession = Depends(get_db)):
    existing = db.query(Session).filter(
        Session.challenge_id == "reto5",
        Session.status       == "running",
    ).first()
    if existing:
        raise HTTPException(400, "El reto grupal ya está en curso")

    try:
        result = spawn_reto5()
    except Exception as e:
        raise HTTPException(500, f"Error al lanzar reto5: {e}")

    for jugador, creds in result.items():
        session = Session(
            id_usuario   = jugador,
            challenge_id = "reto5",
            container_id = f"escape_reto5_{jugador}",
            ssh_port     = creds["ssh_port"],
            ssh_user     = creds["ssh_user"],
            ssh_pass     = creds["ssh_pass"],
            status       = "running",
            started_at   = datetime.utcnow(),
        )
        db.add(session)

    db.commit()

    timeout = CHALLENGES["reto5"]["timeout_secs"]
    return {
        "message":      "Reto grupal lanzado",
        "timeout_secs": timeout,
        "jugadores":    result,
    }


@app.post("/challenge/reto5/abort")
def abort_reto5(db: DBSession = Depends(get_db)):
    sessions = db.query(Session).filter(
        Session.challenge_id == "reto5",
        Session.status       == "running",
    ).all()
    if not sessions:
        raise HTTPException(404, "No hay sesión grupal activa")

    try:
        stop_reto5()
    except Exception as e:
        raise HTTPException(500, f"Error deteniendo reto5: {e}")

    for session in sessions:
        _finish_session(session, "aborted", db)

    return {"message": "Reto grupal abortado"}


@app.post("/challenge/reto5/validate")
def validate_reto5(req: ValidateRequest, db: DBSession = Depends(get_db)):
    sessions = db.query(Session).filter(
        Session.challenge_id == "reto5",
        Session.status       == "running",
    ).all()
    if not sessions:
        raise HTTPException(404, "No hay sesión grupal activa")

    # Flag parcial (acceso inicial conseguido)
    expected_parcial = "FLAG_PARCIAL{acceso_inicial_conseguido}"

    # Flag final: basada en el flag_seed del reto5, sin ssh_user prefix
    # (el objetivo no tiene usuario dinámico — es un contenedor fijo)
    flag_input     = f"{CHALLENGES['reto5']['flag_seed']}\n"
    expected_final = f"FLAG{{{hashlib.md5(flag_input.encode()).hexdigest()[:12]}}}"

    if req.flag == expected_parcial:
        return {"success": True, "partial": True, "message": "Flag parcial correcta. ¡Seguid escalando!"}

    if req.flag != expected_final:
        return {"success": False, "message": "Flag incorrecta. Sigue intentándolo."}

    try:
        stop_reto5()
    except Exception as e:
        raise HTTPException(500, f"Error deteniendo reto5: {e}")

    for s in sessions:
        s.flag_used = req.flag
        _finish_session(s, "completed", db)

    mins = sessions[0].elapsed_secs // 60
    secs = sessions[0].elapsed_secs % 60
    return {
        "success":         True,
        "message":         "¡Reto grupal completado!",
        "elapsed_seconds": sessions[0].elapsed_secs,
        "elapsed_human":   f"{mins}m {secs:02d}s",
    }


# ── Endpoints de consulta ─────────────────────────────────────

@app.get("/ranking")
def get_ranking(db: DBSession = Depends(get_db)):
    sessions = db.query(Session).filter(
        Session.status == "completed"
    ).order_by(Session.elapsed_secs).all()

    return [
        {
            "user_id":       s.id_usuario,
            "challenge_id":  s.challenge_id,
            "elapsed_secs":  s.elapsed_secs,
            "elapsed_human": f"{s.elapsed_secs // 60}m {s.elapsed_secs % 60:02d}s",
            "finished_at":   s.finished_at,
        }
        for s in sessions
    ]


@app.get("/admin/sessions")
def admin_sessions(db: DBSession = Depends(get_db)):
    sessions = db.query(Session).order_by(Session.started_at.desc()).all()
    return [
        {
            "id":           s.id_sesion,
            "user_id":      s.id_usuario,
            "challenge_id": s.challenge_id,
            "status":       s.status,
            "ssh_port":     s.ssh_port,
            "started_at":   s.started_at,
            "elapsed_secs": s.elapsed_secs,
        }
        for s in sessions
    ]