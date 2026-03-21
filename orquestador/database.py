from sqlalchemy import create_engine, Column, Integer, String, DateTime
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker
from datetime import datetime

# ── Configuración MySQL ───────────────────────────────────────
# Mismos datos que base_datos.php
DB_HOST     = "localhost"
DB_PORT     = 3306
DB_NAME     = "escape_db"
DB_USER     = "escape_user"
DB_PASSWORD = "password1234"

DATABASE_URL = (
    f"mysql+pymysql://{DB_USER}:{DB_PASSWORD}"
    f"@{DB_HOST}:{DB_PORT}/{DB_NAME}?charset=utf8mb4"
)

engine = create_engine(
    DATABASE_URL,
    pool_pre_ping=True,   # reconecta si la conexión se cae
    pool_recycle=3600,    # recicla conexiones cada hora
)

SessionLocal = sessionmaker(bind=engine)
Base = declarative_base()

# ── Modelo — espeja la tabla sesiones_reto del schema.sql ─────
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

def init_db():
    # No crea tablas — ya existen por schema.sql
    # Solo verifica que la conexión funciona
    with engine.connect() as conn:
        pass

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()