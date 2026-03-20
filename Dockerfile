FROM php:8.2-apache

# Copiar archivos
COPY . /var/www/html/

# Activar rewrite
RUN a2enmod rewrite

# Ajustar Apache al puerto de Render
RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 10000