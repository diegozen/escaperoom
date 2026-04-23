#!/bin/bash
set -e

# ── Usuarios ──────────────────────────────────────────────────
id "$SSH_USER" &>/dev/null || useradd -m -s /bin/bash "$SSH_USER"
echo "$SSH_USER:$SSH_PASS" | chpasswd

# ── Flag (solo root puede leerla) ─────────────────────────────
FLAG_UNICA="FLAG{$(printf '%s%s' "$SSH_USER" "$FLAG" | md5sum | cut -c1-12)}"
mkdir -p /root/secreto
echo "$FLAG_UNICA" > /root/secreto/flag.txt
chmod 700 /root/secreto
chmod 600 /root/secreto/flag.txt

# ── Pista ─────────────────────────────────────────────────────
echo "Algo en este sistema tiene más permisos de los que debería. Busca binarios con bits especiales." \
    > "/home/$SSH_USER/pista.txt"
chown "$SSH_USER":"$SSH_USER" "/home/$SSH_USER/pista.txt"

# ── Vector de escalada: bash con SUID ────────────────────────
cp /bin/bash /usr/local/bin/bash_suid
chmod u+s /usr/local/bin/bash_suid

# ── iptables: aislamiento estricto ───────────────────────────
iptables -F
iptables -X
ip6tables -F 2>/dev/null || true

iptables -P INPUT   DROP
iptables -P FORWARD DROP
iptables -P OUTPUT  DROP

iptables -A INPUT  -i lo                                               -j ACCEPT
iptables -A INPUT  -p tcp --dport 22   -m state --state NEW,ESTABLISHED -j ACCEPT
iptables -A INPUT  -m state --state ESTABLISHED,RELATED                -j ACCEPT

iptables -A OUTPUT -o lo                                               -j ACCEPT
iptables -A OUTPUT -p tcp --sport 22   -m state --state ESTABLISHED    -j ACCEPT
iptables -A OUTPUT -d 172.100.0.0/16                                   -j ACCEPT

echo "[OK] iptables aplicadas"

# ── SSH ───────────────────────────────────────────────────────
mkdir -p /var/run/sshd
service ssh start
tail -f /dev/null