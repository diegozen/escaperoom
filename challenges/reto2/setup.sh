#!/bin/bash

# Crear usuario SSH
useradd -m -s /bin/bash $SSH_USER
echo "$SSH_USER:$SSH_PASS" | chpasswd

# Calcular flag única por usuario
FLAG_UNICA="FLAG{$(echo $SSH_USER$FLAG | md5sum | cut -c1-12)}"

# Arrancar la app web vulnerable
export FLAG_UNICA
cd /app && python3 app.py &

# Dejar pista en el home del usuario
echo "Hay un servicio web corriendo en este servidor. Búscalo y explótalo." \
    > /home/$SSH_USER/pista.txt

# Bloquear tráfico saliente a internet, permitir solo la red local
iptables -P FORWARD DROP
iptables -A OUTPUT -d 127.0.0.0/8 -j ACCEPT    # redes privadas Docker
iptables -A OUTPUT -d 172.100.0.0/16 -j ACCEPT       # loopback
iptables -A OUTPUT -m state --state ESTABLISHED,RELATED -j ACCEPT  # conexiones ya establecidas (SSH)
iptables -A OUTPUT -j DROP                          # bloquear todo lo demás

# Arrancar SSH
service ssh start

tail -f /dev/null
