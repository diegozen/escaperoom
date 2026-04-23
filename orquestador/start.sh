#!/bin/bash
# Arranca el orquestador FastAPI cargando variables desde .env
# Uso: ./start.sh [--reload]  (--reload solo en desarrollo)

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env"

if [ ! -f "$ENV_FILE" ]; then
    echo "[ERROR] No se encuentra $ENV_FILE"
    echo "        Copia .env.example a .env y rellena las credenciales."
    exit 1
fi

# Cargar variables del .env (ignora líneas comentadas y vacías)
set -a
# shellcheck disable=SC1090
source "$ENV_FILE"
set +a

cd "$SCRIPT_DIR"
exec uvicorn main:app --host 127.0.0.1 --port 8000 "$@"