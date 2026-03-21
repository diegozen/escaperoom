FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

# Instalar herramientas necesarias
RUN apt-get update && apt-get install -y \
    openssh-server \
    python3 \
    python3-pip \
    curl \
    nmap \
    iptables \
    && pip3 install flask \
    && rm -rf /var/lib/apt/lists/*

# Configurar SSH
RUN mkdir /var/run/sshd
RUN echo 'PermitRootLogin no' >> /etc/ssh/sshd_config
RUN echo 'PasswordAuthentication yes' >> /etc/ssh/sshd_config

COPY app.py /app/app.py
COPY setup.sh /setup.sh

EXPOSE 22

CMD ["/setup.sh"]
