FROM php:8.1-apache

# Extensoes para falar com o MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

WORKDIR /var/www/html
COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# OBS (simplificacao): o Twitter-Clone original baixava e instalava o Datadog PHP
# Tracer aqui (curl + datadog-setup.php). Isso foi removido de proposito: a
# telemetria agora sai via OTLP/HTTP (curl nativo em otel_helper.php) direto para
# o OTel Collector, que ja encaminha para Datadog, Cloud Trace e Zabbix/Prometheus.
# Um unico caminho de instrumentacao = menos coisa pra quebrar no build.

EXPOSE 80
