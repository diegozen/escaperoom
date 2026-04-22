#!/bin/bash

# Servidor FTP simulado en puerto 2121 (loopback only)
# Responde a USER/PASS/STOR/QUIT de forma mínima pero reconocible
# Solo escucha en loopback para que el jugador tenga que capturar con tcpdump

while true; do
    printf "220 FTP Server Ready\r\n331 Password required\r\n230 Login OK\r\n226 Transfer complete\r\n221 Bye\r\n" \
        | nc -l -p 2121 -s 127.0.0.1 -q 3 2>/dev/null || true
done