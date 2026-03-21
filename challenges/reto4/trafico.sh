#!/bin/bash

# Simula tráfico FTP interno cada 10 segundos
while true; do
    echo -e "USER admin_ftp\nPASS $FTP_PASS\nSTOR secret.txt\nQUIT" | \
    nc -q 1 127.0.0.1 2121 2>/dev/null
    sleep 10
done