#!/bin/bash
set -e

# ── Idempotencia: evitar fallos en reinicios del contenedor ───
if ! id "$SSH_USER" &>/dev/null; then
    useradd -m -s /bin/bash "$SSH_USER"
fi
echo "$SSH_USER:$SSH_PASS" | chpasswd

# ── Pista ─────────────────────────────────────────────────────
cat > "/home/$SSH_USER/pista.txt" << 'PISTA'
Hay una máquina objetivo en vuestra red: 172.100.9.10
Tiene varios servicios corriendo.
Paso 1: Escaneadla y analizad sus servicios.
Paso 2: Conseguid acceso inicial.
Paso 3: Escalad privilegios para obtener la flag final.
Trabajad en equipo.
PISTA
chown "$SSH_USER":"$SSH_USER" "/home/$SSH_USER/pista.txt"

# ── tcpdump sin root (requiere libcap2-bin en Dockerfile.jugador) ─
setcap cap_net_raw,cap_net_admin+eip /usr/bin/tcpdump

# ── iptables: aislamiento estricto ───────────────────────────
iptables -F
iptables -X
ip6tables -F 2>/dev/null || true

iptables -P INPUT   DROP
iptables -P FORWARD DROP
iptables -P OUTPUT  DROP

iptables -A INPUT  -i lo                                               -j ACCEPT
iptables -A INPUT  -p tcp --dport 22 -m state --state NEW,ESTABLISHED  -j ACCEPT
iptables -A INPUT  -m state --state ESTABLISHED,RELATED                -j ACCEPT

iptables -A OUTPUT -o lo                                               -j ACCEPT
iptables -A OUTPUT -p tcp --sport 22 -m state --state ESTABLISHED      -j ACCEPT
iptables -A OUTPUT -d 172.100.9.0/24                                   -j ACCEPT
iptables -A OUTPUT -d 172.100.10.0/24                                  -j ACCEPT

echo "[OK] iptables jugador aplicadas"

# ── SSH ───────────────────────────────────────────────────────
mkdir -p /var/run/sshd
service ssh start

tail -f /dev/null