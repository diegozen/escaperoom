#!/bin/bash

# Crear usuario SSH
useradd -m -s /bin/bash $SSH_USER
echo "$SSH_USER:$SSH_PASS" | chpasswd

# Flag única por usuario
FLAG_UNICA="FLAG{$(echo $SSH_USER$FLAG | md5sum | cut -c1-12)}"

# La flag solo la puede leer root
mkdir -p /root/secreto
echo "$FLAG_UNICA" > /root/secreto/flag.txt
chmod 700 /root/secreto
chmod 600 /root/secreto/flag.txt

# Pista en el home del jugador
echo "Algo en este sistema tiene más permisos de los que debería..." \
    > /home/$SSH_USER/pista.txt

# Vector de escalada: binario bash con bit SUID
cp /bin/bash /usr/local/bin/bash_suid
chmod u+s /usr/local/bin/bash_suid

# Bloquear tráfico saliente a internet, permitir solo la red local
iptables -P FORWARD DROP
iptables -A OUTPUT -d 127.0.0.0/8 -j ACCEPT    # redes privadas Docker
iptables -A OUTPUT -d 172.100.0.0/16 -j ACCEPT       # loopback
iptables -A OUTPUT -m state --state ESTABLISHED,RELATED -j ACCEPT  # conexiones ya establecidas (SSH)
iptables -A OUTPUT -j DROP                          # bloquear todo lo demás

# Arrancar SSH
service ssh start

tail -f /dev/null