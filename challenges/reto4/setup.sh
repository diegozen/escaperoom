#!/bin/bash

# Crear usuario SSH sin privilegios
useradd -m -s /bin/bash $SSH_USER
echo "$SSH_USER:$SSH_PASS" | chpasswd

# Flag única por usuario
FLAG_UNICA="FLAG{$(echo $SSH_USER$FLAG | md5sum | cut -c1-12)}"

# La flag está protegida, solo accesible con las credenciales FTP capturadas
mkdir -p /opt/ftp_data
echo "$FLAG_UNICA" > /opt/ftp_data/flag.txt
chmod 600 /opt/ftp_data/flag.txt

# Crear usuario ftp con la flag accesible solo para él
useradd -m -s /bin/bash admin_ftp
echo "admin_ftp:$FTP_PASS" | chpasswd
chown admin_ftp:admin_ftp /opt/ftp_data/flag.txt

# Pista en el home del jugador
cat > /home/$SSH_USER/pista.txt << 'PISTA'
Hay tráfico de red interno en este servidor.
Alguien está enviando credenciales sin cifrar.
Captura el tráfico y úsalas.
PISTA

# Arrancar servidor FTP falso
/ftp_server.sh &

# Arrancar tráfico simulado
FTP_PASS=$FTP_PASS /trafico.sh &

# Permisos root en el binario tcpdump
chmod +s /usr/bin/tcpdump
setcap cap_net_raw+eip /usr/bin/tcpdump

# Bloquear tráfico saliente
iptables -A OUTPUT -d 127.0.0.0/8 -j ACCEPT
iptables -A OUTPUT -d 172.100.0.0/16 -j ACCEPT
iptables -A OUTPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
iptables -A OUTPUT -j DROP

echo "Reglas iptables aplicadas"

# Arrancar SSH
service ssh start

tail -f /dev/null