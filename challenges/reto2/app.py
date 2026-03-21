from flask import Flask, request
import sqlite3
import os

app = Flask(__name__)
DB = "/tmp/usuarios.db"

def init_db():
    conn = sqlite3.connect(DB)
    c = conn.cursor()
    c.execute("CREATE TABLE IF NOT EXISTS usuarios (id INTEGER PRIMARY KEY, nombre TEXT, password TEXT)")
    c.execute("CREATE TABLE IF NOT EXISTS secretos (id INTEGER PRIMARY KEY, flag TEXT)")
    c.execute("DELETE FROM usuarios")
    c.execute("DELETE FROM secretos")
    c.execute("INSERT INTO usuarios VALUES (1, 'admin', 'superpassword')")
    c.execute("INSERT INTO usuarios VALUES (2, 'jugador', 'password123')")
    flag = os.environ.get("FLAG_UNICA", "FLAG{error}")
    c.execute("INSERT INTO secretos VALUES (1, ?)", (flag,))
    conn.commit()
    conn.close()

@app.route("/")
def index():
    return """
    <h2>Panel de Login</h2>
    <form method='POST' action='/login'>
        Usuario: <input name='usuario'><br>
        Password: <input name='password' type='password'><br>
        <input type='submit' value='Entrar'>
    </form>
    <p><small>Hint: el sistema guarda datos sensibles en la tabla 'secretos'</small></p>
    """

@app.route("/login", methods=["POST"])
def login():
    usuario = request.form.get("usuario", "")
    password = request.form.get("password", "")
    conn = sqlite3.connect(DB)
    c = conn.cursor()
    # VULNERABLE: concatenación directa sin sanitizar
    query = f"SELECT * FROM usuarios WHERE nombre='{usuario}' AND password='{password}'"
    try:
        result = c.execute(query).fetchall()
    except Exception as e:
        return f"<p>Error SQL: {e}</p><p>Query: {query}</p>"
    conn.close()
    if result:
        return f"<p>Login correcto. Bienvenido {result[0][1]}.</p>"
    return "<p>Login incorrecto.</p>"

if __name__ == "__main__":
    init_db()
    app.run(host="0.0.0.0", port=8080)
