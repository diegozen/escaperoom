#!/bin/bash

# Servidor FTP simple que acepta conexiones y responde
while true; do
    echo -e "220 FTP Server Ready\n331 Password required\n230 Login OK\n221 Bye" | \
    nc -l -p 2121 -q 2 2>/dev/null
done