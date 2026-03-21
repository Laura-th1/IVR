FROM php:8.2-apache

# Instalar dependencias necesarias
RUN apt-get update && apt-get install -y libpq-dev

# Instalar extensión de PostgreSQL
RUN docker-php-ext-install pgsql pdo_pgsql

# Copiar proyecto
COPY . /var/www/html/

# Activar rewrite
RUN a2enmod rewrite

# Ajustar puerto para Render
RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

CMD ["apache2-foreground"]