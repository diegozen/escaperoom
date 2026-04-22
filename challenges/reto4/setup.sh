#!/bin/bash
set -e

# ── Usuarios ──────────────────────────────────────────────────
useradd -m -s /bin/bash "$SSH_USER"
echo "$SSH_USER:$SSH_PASS" | chpasswd

# Contraseña FTP: saneada para evitar rotura en variables/heredoc
# Se recibe de Docker como variable de entorno; si viene vacía, falla
if [ -z "$FTP_PASS" ]; then
    echo "[ERROR] FTP_PASS no está definida" >&2
    exit 1
fi

useradd -m -s /bin/bash admin_ftp
echo "admin_ftp:${FTP_PASS}" | chpasswd

# ── Flag ──────────────────────────────────────────────────────
FLAG_UNICA="FLAG{$(printf '%s%s' "$SSH_USER" "$FLAG" | md5sum | cut -c1-12)}"

mkdir -p /opt/ftp_data
echo "$FLAG_UNICA" > /opt/ftp_data/flag.txt
chmod 600 /opt/ftp_data/flag.txt
chown admin_ftp:admin_ftp /opt/ftp_data/flag.txt

# ── Pista ─────────────────────────────────────────────────────
cat > "/home/$SSH_USER/pista.txt" << 'PISTA'
Hay tráfico de red interno en este servidor.
Alguien está enviando credenciales sin cifrar por la interfaz loopback.
Herramienta sugerida: tcpdump
Captura el tráfico, extrae las credenciales y úsalas para acceder como admin_ftp.
PISTA
chown "$SSH_USER":"$SSH_USER" "/home/$SSH_USER/pista.txt"

# ── tcpdump: solo capabilities, sin SUID (son incompatibles en Ubuntu 22.04) ─
# El SUID se ignora cuando hay capabilities extendidas; setcap es suficiente.
setcap cap_net_raw,cap_net_admin+eip /usr/bin/tcpdump

# ── Servidor FTP simulado ─────────────────────────────────────
/ftp_server.sh &

# ── Tráfico simulado: exportar FTP_PASS antes de lanzar trafico.sh ───────────
export FTP_PASS
/trafico.sh &

# ── iptables: aislamiento estricto ───────────────────────────
# Limpiar reglas previas
iptables -F
iptables -X
ip6tables -F 2>/dev/null || true
ip6tables -X 2>/dev/null || true

# Políticas por defecto: DROP todo
iptables -P INPUT   DROP
iptables -P FORWARD DROP
iptables -P OUTPUT  DROP

# INPUT: permitir loopback y SSH entrante
iptables -A INPUT -i lo -j ACCEPT
iptables -A INPUT -p tcp --dport 22 -m state --state NEW,ESTABLISHED -j ACCEPT
iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT

# OUTPUT: permitir loopback (tráfico FTP interno) y respuestas SSH
iptables -A OUTPUT -o lo -j ACCEPT
iptables -A OUTPUT -p tcp --sport 22 -m state --state ESTABLISHED -j ACCEPT
# Permitir la red Docker interna (para healthcheck y comunicación con orquestador)
iptables -A OUTPUT -d 172.100.0.0/16 -j ACCEPT

echo "[OK] iptables aplicadas — contenedor aislado"

# ── SSH ───────────────────────────────────────────────────────
service ssh start

tail -f /dev/null