#!/bin/bash

# Crear usuario SSH
useradd -m -s /bin/bash $SSH_USER
echo "$SSH_USER:$SSH_PASS" | chpasswd

# Servicio HTTP simple en puerto 8080 con una pista
mkdir -p /var/www/pista
echo "Interesante... pero la flag no está aquí. Prueba el puerto 3000." \
> /var/www/pista/index.html
cd /var/www/pista && python3 -m http.server 8080 &

# Servicio netcat en puerto 3000 con otra pista
while true; do
echo "Casi... sigue buscando. Mira en /opt/.secreto/" | nc -l -p 3000 -q 1
done &

# La flag real está en un archivo escondido
mkdir -p /opt/.secreto
FLAG_UNICA="FLAG{$(echo $SSH_USER$FLAG | md5sum | cut -c1-12)}"
echo "$FLAG_UNICA" > /opt/.secreto/flag.txt
chmod 644 /opt/.secreto/flag.txt

# Bloquear tráfico saliente a internet, permitir solo la red local
iptables -P FORWARD DROP
iptables -A OUTPUT -d 127.0.0.0/8 -j ACCEPT    # redes privadas Docker
iptables -A OUTPUT -d 172.100.0.0/16 -j ACCEPT       # loopback
iptables -A OUTPUT -m state --state ESTABLISHED,RELATED -j ACCEPT  # conexiones ya establecidas (SSH)
iptables -A OUTPUT -j DROP                          # bloquear todo lo demás

# Arrancar SSH
service ssh start

# Mantener contenedor vivo
tail -f /dev/null
