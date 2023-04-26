FROM wordpress:latest

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update -qq && \
  apt-get install -y \
  build-essential \
  sudo \
  less \
  nano

RUN mkdir -p /var/www/html/wp-content/plugins
RUN mkdir -p /var/www/html/wp-content/uploads
RUN mkdir -p /var/www/html/wp-content/upgrade
RUN chown -R www-data:www-data /var/www
RUN find /var/www/ -type d -exec chmod 0755 {} \;
RUN find /var/www/ -type f -exec chmod 644 {} \;

RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
  chmod +x wp-cli.phar && \
  mv wp-cli.phar /usr/local/bin/wpcli

RUN echo 'alias wp="wpcli --path=/var/www/html --allow-root"' >> ~/.bashrc

# OMG: WordPress docker-entrypoint.sh depends on something called apache2*
# to execute anything!!!
COPY docker/apache2-hello.sh /usr/local/bin/apache2-hello.sh
RUN chmod +x /usr/local/bin/apache2-hello.sh

COPY docker/install-wordpress.sh /usr/local/bin/docker-install-wordpress.sh
RUN chmod +x /usr/local/bin/docker-install-wordpress.sh

CMD ["apache2-hello.sh", "docker-install-wordpress.sh", "apache2-foreground"]
