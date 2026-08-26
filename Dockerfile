FROM php:8.1-apache
RUN docker-php-ext-install mysqli
RUN echo "output_buffering = 4096" >> /usr/local/etc/php/conf.d/docker-php-output-buffering.ini
