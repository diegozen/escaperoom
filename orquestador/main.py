from fastapi import FastAPI, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy.orm import Session as DBSession
from datetime import datetime
import hashlib

from database import get_db, init_db, Session
from manager import spawn_container, stop_container, CHALLENGES, spawn_reto5, stop_reto5
from timer import start_timer, cancel_timer

app = FastAPI(title="Escape Room API")

@app.on_event("startup")
def startup():
    init_db()

# ── Modelos de request ────────────────────────────────────────────────────────

class StartRequest(BaseModel):
    user_id: str
    challenge_id: str

class AbortRequest(BaseModel):
    user_id: str
    challenge_id: str

class ValidateRequest(BaseModel):
    user_id: str
    challenge_id: str
    flag: str

# ── Endpoints retos individuales ──────────────────────────────────────────────

@app.post("/challenge/start")
def start_challenge(req: StartRequest, db: DBSession = Depends(get_db)):
    existing = db.query(Session).filter(
        Session.id_usuario   == req.user_id,
        Session.challenge_id == req.challenge_id,
        Session.status       == "running"
    ).first()
    if existing:
        raise HTTPException(400, "Ya tienes una sesión activa para este reto")

    result = spawn_container(req.user_id, req.challenge_id)

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
    start_timer(session.id_sesion, result["container_id"], timeout, lambda: next(get_db()))

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
        Session.status       == "running"
    ).first()
    if not session:
        raise HTTPException(404, "No hay sesión activa para este reto")

    cancel_timer(session.id_sesion)
    stop_container(session.container_id)

    session.status       = "aborted"
    session.finished_at  = datetime.utcnow()
    session.elapsed_secs = (session.finished_at - session.started_at).seconds
    db.commit()

    return {"message": "Reto abortado"}

@app.post("/challenge/validate")
def validate_flag(req: ValidateRequest, db: DBSession = Depends(get_db)):
    session = db.query(Session).filter(
        Session.id_usuario   == req.user_id,
        Session.challenge_id == req.challenge_id,
        Session.status       == "running"
    ).first()
    if not session:
        raise HTTPException(404, "No hay sesión activa para este reto")

    flag_seed  = CHALLENGES[req.challenge_id]["flag_seed"]
    flag_input = f"{session.ssh_user}{flag_seed}\n"
    expected   = f"FLAG{{{hashlib.md5(flag_input.encode()).hexdigest()[:12]}}}"

    if req.flag != expected:
        return {"success": False, "message": "Flag incorrecta. Sigue intentándolo."}

    cancel_timer(session.id_sesion)
    stop_container(session.container_id)

    session.status       = "completed"
    session.finished_at  = datetime.utcnow()
    session.elapsed_secs = (session.finished_at - session.started_at).seconds
    session.flag_used    = req.flag
    db.commit()

    mins = session.elapsed_secs // 60
    secs = session.elapsed_secs % 60
    return {
        "success":          True,
        "message":          "¡Reto completado!",
        "elapsed_seconds":  session.elapsed_secs,
        "elapsed_human":    f"{mins}m {secs:02d}s"
    }

@app.get("/challenge/status/{user_id}/{challenge_id}")
def get_status(user_id: str, challenge_id: str, db: DBSession = Depends(get_db)):
    session = db.query(Session).filter(
        Session.id_usuario   == user_id,
        Session.challenge_id == challenge_id,
    ).order_by(Session.started_at.desc()).first()
    if not session:
        raise HTTPException(404, "Sesión no encontrada")

    elapsed = None
    if session.started_at and session.status == "running":
        elapsed = (datetime.utcnow() - session.started_at).seconds

    return {
        "status":    session.status,
        "elapsed_secs": elapsed or session.elapsed_secs,
        "ssh_port":  session.ssh_port,
        "ssh_user":  session.ssh_user,
    }

# ── Endpoints reto grupal (reto5) ─────────────────────────────────────────────

@app.post("/challenge/reto5/start")
def start_reto5(db: DBSession = Depends(get_db)):
    existing = db.query(Session).filter(
        Session.challenge_id == "reto5",
        Session.status       == "running"
    ).first()
    if existing:
        raise HTTPException(400, "El reto grupal ya está en curso")

    result = spawn_reto5()

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
        "jugadores":    result
    }

@app.post("/challenge/reto5/abort")
def abort_reto5(db: DBSession = Depends(get_db)):
    sessions = db.query(Session).filter(
        Session.challenge_id == "reto5",
        Session.status       == "running"
    ).all()
    if not sessions:
        raise HTTPException(404, "No hay sesión grupal activa")

    stop_reto5()

    for session in sessions:
        session.status       = "aborted"
        session.finished_at  = datetime.utcnow()
        session.elapsed_secs = (session.finished_at - session.started_at).seconds

    db.commit()
    return {"message": "Reto grupal abortado"}

@app.post("/challenge/reto5/validate")
def validate_reto5(req: ValidateRequest, db: DBSession = Depends(get_db)):
    sessions = db.query(Session).filter(
        Session.challenge_id == "reto5",
        Session.status       == "running"
    ).all()
    if not sessions:
        raise HTTPException(404, "No hay sesión grupal activa")

    flag_input     = "grupal_secret\n"
    expected_final  = f"FLAG{{{hashlib.md5(flag_input.encode()).hexdigest()[:12]}}}"
    expected_parcial = "FLAG_PARCIAL{acceso_inicial_conseguido}"

    if req.flag == expected_parcial:
        return {"success": True, "message": "Flag parcial correcta. ¡Seguid escalando!"}

    if req.flag != expected_final:
        return {"success": False, "message": "Flag incorrecta. Sigue intentándolo."}

    stop_reto5()

    for s in sessions:
        s.status       = "completed"
        s.finished_at  = datetime.utcnow()
        s.elapsed_secs = (s.finished_at - s.started_at).seconds
        s.flag_used    = req.flag
    db.commit()

    mins = sessions[0].elapsed_secs // 60
    secs = sessions[0].elapsed_secs % 60
    return {
        "success":         True,
        "message":         "¡Reto grupal completado!",
        "elapsed_seconds": sessions[0].elapsed_secs,
        "elapsed_human":   f"{mins}m {secs:02d}s"
    }

# ── Endpoints generales ───────────────────────────────────────────────────────

@app.get("/ranking")
def get_ranking(db: DBSession = Depends(get_db)):
    sessions = db.query(Session).filter(
        Session.status == "completed"
    ).order_by(Session.elapsed_secs).all()

    return [
        {
            "user_id":      s.id_usuario,
            "challenge_id": s.challenge_id,
            "elapsed_secs": s.elapsed_secs,
            "elapsed_human": f"{s.elapsed_secs // 60}m {s.elapsed_secs % 60:02d}s",
            "finished_at":  s.finished_at,
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