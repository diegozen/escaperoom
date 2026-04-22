#!/bin/bash

# FTP_PASS viene exportada desde setup.sh
# Simulamos tráfico FTP en claro por loopback cada 10 segundos
# El jugador debe capturarlo con tcpdump para extraer las credenciales

if [ -z "$FTP_PASS" ]; then
    echo "[ERROR] trafico.sh: FTP_PASS no definida" >&2
    exit 1
fi

while true; do
    {
        printf "USER admin_ftp\r\n"
        sleep 0.3
        printf "PASS %s\r\n" "$FTP_PASS"
        sleep 0.3
        printf "STOR secret.txt\r\n"
        sleep 0.3
        printf "QUIT\r\n"
    } | nc -q 2 127.0.0.1 2121 2>/dev/null || true
    sleep 10
done