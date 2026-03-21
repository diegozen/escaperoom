FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

# Instalar herramientas necesarias
RUN apt-get update && apt-get install -y \
    openssh-server \
    nmap \
    netcat-openbsd \
    curl \
    python3 \
    iptables \
    && rm -rf /var/lib/apt/lists/*
    
# Configurar SSH 
RUN mkdir /var/run/sshd
RUN echo 'PermitRootLogin no' >> /etc/ssh/sshd_config
RUN echo 'PasswordAuthentication yes' >> /etc/ssh/sshd_config

COPY setup.sh /setup.sh

EXPOSE 22

CMD ["/setup.sh"]
