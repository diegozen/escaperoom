import os
import sys
from sqlalchemy import create_engine, Column, Integer, String, DateTime, text
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker
from datetime import datetime

# ── Credenciales desde variables de entorno ───────────────────
# Nunca hardcodear credenciales en el código fuente.
# El proceso debe fallar al arrancar si faltan variables críticas.

def _require_env(key: str) -> str:
    value = os.environ.get(key)
    if not value:
        print(f"[ERROR] Variable de entorno '{key}' no definida", file=sys.stderr)
        sys.exit(1)
    return value

DB_HOST     = os.environ.get("DB_HOST",     "localhost")
DB_PORT     = os.environ.get("DB_PORT",     "3306")
DB_NAME     = _require_env("DB_NAME")
DB_USER     = _require_env("DB_USER")
DB_PASSWORD = _require_env("DB_PASSWORD")

DATABASE_URL = (
    f"mysql+pymysql://{DB_USER}:{DB_PASSWORD}"
    f"@{DB_HOST}:{DB_PORT}/{DB_NAME}?charset=utf8mb4"
)

engine = create_engine(
    DATABASE_URL,
    pool_pre_ping  = True,    # reconecta automáticamente si la conexión se cae
    pool_recycle   = 3600,    # recicla conexiones cada hora
    pool_size      = 5,       # conexiones permanentes en el pool
    max_overflow   = 10,      # conexiones extra permitidas bajo carga
    echo           = False,   # True solo para debug SQL
)

SessionLocal = sessionmaker(bind=engine, autocommit=False, autoflush=False)
Base         = declarative_base()

# ── Modelo — espeja sesiones_reto del schema.sql ──────────────
class Session(Base):
    __tablename__ = "sesiones_reto"

    id_sesion    = Column(Integer,     primary_key=True, autoincrement=True)
    id_usuario   = Column(Integer,     nullable=False)
    id_partida   = Column(Integer,     nullable=True)
    challenge_id = Column(String(50),  nullable=False)
    container_id = Column(String(100), nullable=True)
    ssh_host     = Column(String(100), nullable=True,  default="localhost")
    ssh_port     = Column(Integer,     nullable=True)
    ssh_user     = Column(String(100), nullable=True)
    ssh_pass     = Column(String(100), nullable=True)
    status       = Column(String(20),  nullable=False, default="running")
    started_at   = Column(DateTime,    nullable=False, default=datetime.utcnow)
    finished_at  = Column(DateTime,    nullable=True)
    elapsed_secs = Column(Integer,     nullable=True)
    flag_used    = Column(String(255), nullable=True)

class SessionReto5(Base):
    __tablename__ = "sesiones_reto5"

    id_sesion    = Column(Integer,     primary_key=True, autoincrement=True)
    jugador      = Column(String(20),  nullable=False)
    ssh_port     = Column(Integer,     nullable=False)
    ssh_user     = Column(String(100), nullable=False)
    ssh_pass     = Column(String(100), nullable=False)
    status       = Column(String(20),  nullable=False, default="running")
    started_at   = Column(DateTime,    nullable=False, default=datetime.utcnow)
    finished_at  = Column(DateTime,    nullable=True)
    elapsed_secs = Column(Integer,     nullable=True)
    flag_used    = Column(String(255), nullable=True)

# ── Inicialización ────────────────────────────────────────────
def init_db():
    """
    Verifica que la conexión con MySQL funciona al arrancar.
    Si falla, termina el proceso para que el supervisor lo reinicie
    en lugar de dejar FastAPI corriendo sin BD.
    """
    try:
        with engine.connect() as conn:
            conn.execute(text("SELECT 1"))
        print("[DB] Conexión MySQL OK")
    except Exception as e:
        print(f"[ERROR] No se pudo conectar a MySQL: {e}", file=sys.stderr)
        sys.exit(1)


def get_db():
    """
    Generador de sesiones para inyección de dependencias FastAPI.
    Garantiza que la sesión se cierra siempre, incluso si hay excepción.
    """
    db = SessionLocal()
    try:
        yield db
    except Exception:
        db.rollback()
        raise
    finally:
        db.close()