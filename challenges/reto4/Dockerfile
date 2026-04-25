FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    openssh-server \
    tcpdump \
    netcat-openbsd \
    curl \
    iptables \
    libcap2-bin \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir /var/run/sshd
RUN echo 'PermitRootLogin no' >> /etc/ssh/sshd_config
RUN echo 'PasswordAuthentication yes' >> /etc/ssh/sshd_config

COPY setup.sh /setup.sh
COPY trafico.sh /trafico.sh
COPY ftp_server.sh /ftp_server.sh
RUN chmod +x /setup.sh /trafico.sh /ftp_server.sh

EXPOSE 22

CMD ["/setup.sh"]