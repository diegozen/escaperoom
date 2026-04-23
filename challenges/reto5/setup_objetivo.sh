#!/bin/bash
set -e

# ── Validación ────────────────────────────────────────────────
if [ -z "$FLAG" ]; then
    echo "[ERROR] Variable FLAG no definida" >&2
    exit 1
fi

# ── Flag final (solo root) ────────────────────────────────────
# Calculada igual que validate_reto5 en main.py:
#   hashlib.md5(f"{flag_seed}\n".encode()).hexdigest()[:12]
# printf evita el \n extra que añade echo en algunas shells
FLAG_UNICA="FLAG{$(printf '%s\n' "$FLAG" | md5sum | cut -c1-12)}"
echo "$FLAG_UNICA" > /root/flag.txt
chmod 600 /root/flag.txt

# ── Usuario vulnerable ────────────────────────────────────────
useradd -m -s /bin/bash serviceftp
echo "serviceftp:ftp1234" | chpasswd

# Flag parcial: acceso inicial conseguido
echo "FLAG_PARCIAL{acceso_inicial_conseguido}" > /home/serviceftp/flag_parcial.txt
chmod 644 /home/serviceftp/flag_parcial.txt

echo "Recuerda: tienes permisos especiales para leer archivos del sistema." \
    > /home/serviceftp/nota.txt
chown serviceftp:serviceftp /home/serviceftp/nota.txt

# Sudo mal configurado (vector de privesc)
echo "serviceftp ALL=(ALL) NOPASSWD: /bin/cat" >> /etc/sudoers

# ── vsftpd: ya instalado en Dockerfile.objetivo ──────────────
# NO hacer apt-get aquí (lento, requiere red, ya está en la imagen)

# Configurar FTP anónimo
cat > /etc/vsftpd.conf << 'VSFTPD'
listen=YES
listen_ipv6=NO
anonymous_enable=YES
local_enable=YES
write_enable=NO
anon_root=/var/ftp/pub
no_anon_password=YES
pasv_enable=YES
pasv_min_port=40000
pasv_max_port=40010
VSFTPD

# Credenciales accesibles por FTP anónimo
mkdir -p /var/ftp/pub
echo "Credenciales de acceso SSH: serviceftp:ftp1234" > /var/ftp/pub/credentials.txt
chmod 644 /var/ftp/pub/credentials.txt
chown -R ftp:ftp /var/ftp 2>/dev/null || chown -R nobody:nogroup /var/ftp

# ── Servicio web con pista (puerto 80) ────────────────────────
mkdir -p /var/www/html
cat > /var/www/html/index.html << 'HTML'
<html>
<head><title>Sistema interno v1.0</title></head>
<body>
<h2>Sistema interno v1.0</h2>
<p>Servicio de administración activo.</p>
<!-- TODO: deshabilitar acceso FTP anonimo en puerto 21 -->
</body>
</html>
HTML

cd /var/www/html && python3 -m http.server 80 &

# ── iptables: aislamiento estricto ───────────────────────────
# El objetivo solo es accesible desde la red grupal (172.100.9.0/24)
# No tiene acceso a internet en ningún caso
iptables -F
iptables -X
ip6tables -F 2>/dev/null || true

iptables -P INPUT   DROP
iptables -P FORWARD DROP
iptables -P OUTPUT  DROP

# INPUT: loopback + servicios desde la red grupal únicamente
iptables -A INPUT -i lo                                                    -j ACCEPT
iptables -A INPUT -s 172.100.9.0/24 -p tcp --dport 22  -m state --state NEW,ESTABLISHED -j ACCEPT
iptables -A INPUT -s 172.100.9.0/24 -p tcp --dport 21  -m state --state NEW,ESTABLISHED -j ACCEPT
iptables -A INPUT -s 172.100.9.0/24 -p tcp --dport 80  -m state --state NEW,ESTABLISHED -j ACCEPT
# Puertos pasivos FTP
iptables -A INPUT -s 172.100.9.0/24 -p tcp --dport 40000:40010 -m state --state NEW,ESTABLISHED -j ACCEPT
iptables -A INPUT -m state --state ESTABLISHED,RELATED             -j ACCEPT

# OUTPUT: loopback + respuestas hacia la red grupal
iptables -A OUTPUT -o lo                                                   -j ACCEPT
iptables -A OUTPUT -d 172.100.9.0/24 -m state --state ESTABLISHED,RELATED -j ACCEPT

echo "[OK] iptables objetivo aplicadas"

# ── Arrancar servicios ────────────────────────────────────────
service vsftpd start
service ssh start

tail -f /dev/null