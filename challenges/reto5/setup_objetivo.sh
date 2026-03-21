#!/bin/bash

# Flag única
FLAG_UNICA="FLAG{$(echo $FLAG | md5sum | cut -c1-12)}"
echo "$FLAG_UNICA" > /root/flag.txt
chmod 600 /root/flag.txt

# Usuario vulnerable
useradd -m -s /bin/bash serviceftp
echo "serviceftp:ftp1234" | chpasswd
echo "FLAG_PARCIAL{acceso_inicial_conseguido}" > /home/serviceftp/flag_parcial.txt
chmod 644 /home/serviceftp/flag_parcial.txt
echo "Recuerda: tienes permisos especiales para leer archivos." \
    > /home/serviceftp/nota.txt

# Sudo mal configurado
echo "serviceftp ALL=(ALL) NOPASSWD: /bin/cat" >> /etc/sudoers

# Instalar vsftpd para FTP anónimo real
apt-get install -y vsftpd 2>/dev/null

# Configurar FTP anónimo
cat > /etc/vsftpd.conf << 'VSFTPD'
listen=YES
anonymous_enable=YES
local_enable=YES
write_enable=NO
anon_root=/var/ftp/pub
no_anon_password=YES
VSFTPD

# Archivo con credenciales accesible por anonymous
mkdir -p /var/ftp/pub
echo "Credenciales de acceso SSH: serviceftp:ftp1234" > /var/ftp/pub/credentials.txt
chmod 644 /var/ftp/pub/credentials.txt

# Servicio web con pista
mkdir -p /var/www/html
cat > /var/www/html/index.html << 'HTML'
<h2>Sistema interno v1.0</h2>
<p>Servicio de administración activo.</p>
<!-- TODO: deshabilitar acceso FTP anonimo en puerto 21 -->
HTML
cd /var/www/html && python3 -m http.server 80 &

# Arrancar FTP y SSH
service vsftpd start
service ssh start

tail -f /dev/null