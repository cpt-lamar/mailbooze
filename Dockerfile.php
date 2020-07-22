FROM alpine:latest

WORKDIR /var/www/html

RUN set -x \
  && apk update \
  && apk add --no-cache unzip curl php7 php7-fpm php7-curl php7-iconv php7-json php7-xml php7-dom php7-openssl php7-zlib php7-opcache php7-gd php7-pdo_sqlite php7-simplexml gettext su-exec \
  && curl http://www.rainloop.net/repository/webmail/rainloop-community-latest.zip -o rainloop.zip \
  && unzip -o rainloop.zip \
  && rm rainloop.zip \
  && rm -r ./rainloop/v/*/static/ \
  && find . -type d -exec chmod 755 {} \; \
  && find . -type f -exec chmod 644 {} \; \
  && chown -R www-data:www-data .

ENV PHP_PROCS\
  DOMAIN\
  MDA_HOST\
  MDA_PORT\
  MDA_SIEVE_PORT\
  MDA_SECURE\
  MSA_HOST\
  MSA_PORT\
  MSA_AUTH\
  MSA_SECURE

#php-fpm configuration
RUN echo -e "\
[global]\n\
daemonize = no\n\
error_log = /proc/self/fd/2\n\
log_level = notice\n\
\n\
[www]\n\
pm = static \n\
pm.max_children = \$PHP_PROCS \n\
user = www-data\n\
access.log = /proc/self/fd/2\n\
clear_env = no\n\
catch_workers_output = yes\n\
listen = [::]:9000\n" > /etc/php7/php-fpm.conf.temp

RUN echo -e "\
imap_host = \"\$MDA_HOST\"\n\
imap_port = \$MDA_PORT\n\
imap_secure = \"\$MDA_SECURE\"\n\
imap_short_login = On\n\
sieve_use = On\n\
sieve_allow_raw = On\n\
sieve_host =\"\$MDA_HOST\"\n\
sieve_port = \"\$MDA_SIEVE_PORT\"\n\
sieve_secure = \"None\"\n\
smtp_host = \"\$MSA_HOST\"\n\
smtp_port = \$MSA_PORT\n\
smtp_secure = \"\$MSA_SECURE\"\n\
smtp_short_login = On\n\
smtp_auth = \$MSA_AUTH\n\
smtp_php_mail = Off" > /etc/domain.ini.temp

VOLUME /var/www/html/data

CMD  envsubst < /etc/php7/php-fpm.conf.temp > /etc/php7/php-fpm.conf \
  && chown www-data:www-data data \
  && su-exec www-data php7 index.php \
  && envsubst < /etc/domain.ini.temp > data/_data_/_default_/domains/$DOMAIN.ini \
#  && su-exec www-data sed -i -e "/^\[contacts\]/,/^\[.*\]/ s|^enable.*$|enable = On|" \
#            -e "/^\[debug\]/,/^\[.*\]/ s|^enable *=.*$|enable = Off|" \
#            -e "s/^mail_func_clear_headers.*/mail_func_clear_headers = On/" \
#            -e "s/^smtp_show_server_errors.*/smtp_show_server_errors = On/" \
#            -e "s/^type.*$/type = sqlite/" \
#            data/_data_/_default_/configs/application.ini \
  && sed -i -e "s/^upload_max_filesize.*/upload_max_filesize = 25M/" \
            -e "s/^post_max_size.*/post_max_size = 25M/" \
            /etc/php7/php.ini \
  && exec php-fpm7

EXPOSE 9000
