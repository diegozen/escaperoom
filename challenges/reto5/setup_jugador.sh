#!/bin/bash

useradd -m -s /bin/bash $SSH_USER
echo "$SSH_USER:$SSH_PASS" | chpasswd

cat > /home/$SSH_USER/pista.txt << 'PISTA'
Hay una máquina objetivo en vuestra red: 172.100.9.10
Tiene varios servicios corriendo.
Paso 1: Escaneadla y analizad el tráfico de red.
Paso 2: Conseguid acceso inicial.
Paso 3: Escalad privilegios para obtener la flag final.
Trabajad en equipo.
PISTA

# tcpdump usable sin root
setcap cap_net_raw+eip /usr/bin/tcpdump

# Bloquear internet
iptables -A OUTPUT -d 127.0.0.0/8 -j ACCEPT
iptables -A OUTPUT -d 172.100.9.0/24 -j ACCEPT
iptables -A OUTPUT -d 172.100.10.0/24 -j ACCEPT
iptables -A OUTPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
iptables -A OUTPUT -j DROP

service ssh start
tail -f /dev/null