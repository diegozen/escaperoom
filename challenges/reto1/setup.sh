#!/bin/bash
set -e

# ── Usuarios ──────────────────────────────────────────────────
id "$SSH_USER" &>/dev/null || useradd -m -s /bin/bash "$SSH_USER"
echo "$SSH_USER:$SSH_PASS" | chpasswd

# ── Servicios de pista ────────────────────────────────────────
mkdir -p /var/www/pista
echo "Interesante... pero la flag no está aquí. Prueba el puerto 3000." \
    > /var/www/pista/index.html
cd /var/www/pista && python3 -m http.server 8080 &

while true; do
    echo "Casi... sigue buscando. Mira en /opt/.secreto/" | nc -l -p 3000 -q 1 2>/dev/null
done &

# ── Flag ──────────────────────────────────────────────────────
mkdir -p /opt/.secreto
FLAG_UNICA="FLAG{$(printf '%s%s' "$SSH_USER" "$FLAG" | md5sum | cut -c1-12)}"
echo "$FLAG_UNICA" > /opt/.secreto/flag.txt
chmod 644 /opt/.secreto/flag.txt

# ── iptables: aislamiento estricto ───────────────────────────
iptables -F
iptables -X
ip6tables -F 2>/dev/null || true

iptables -P INPUT   DROP
iptables -P FORWARD DROP
iptables -P OUTPUT  DROP

iptables -A INPUT  -i lo                                          -j ACCEPT
iptables -A INPUT  -p tcp --dport 22  -m state --state NEW,ESTABLISHED -j ACCEPT
iptables -A INPUT  -m state --state ESTABLISHED,RELATED           -j ACCEPT

iptables -A OUTPUT -o lo                                          -j ACCEPT
iptables -A OUTPUT -p tcp --sport 22  -m state --state ESTABLISHED -j ACCEPT
iptables -A OUTPUT -d 172.100.0.0/16                              -j ACCEPT

echo "[OK] iptables aplicadas"

# ── SSH ───────────────────────────────────────────────────────
mkdir -p /var/run/sshd
service ssh start
tail -f /dev/null