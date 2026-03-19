FROM php:8.2-apache

# Copiar contenido correctamente
COPY . /var/www/html/

# Forzar directorio de trabajo
WORKDIR /var/www/html

RUN a2enmod rewrite

EXPOSE 80