FROM alpine:3.19

WORKDIR /var/www/html

RUN set -x \
  && apk update \
  && apk add --no-cache unzip curl php83 php83-fpm php83-curl php83-iconv php83-json php83-xml php83-dom php83-openssl php83-zlib php83-opcache php83-gd php83-pdo_sqlite php83-simplexml php83-mbstring php83-sodium php83-ctype php83-fileinfo gettext su-exec \
  && curl -L https://github.com/the-djmaze/snappymail/releases/download/v2.33.0/snappymail-2.33.0.zip -o snappymail.zip \
  && unzip -o snappymail.zip \
  && rm snappymail.zip \
#  && rm -r ./snappymail/v/*/static/ \
  && find . -type d -exec chmod 755 {} \; \
  && find . -type f -exec chmod 644 {} \; \
  && adduser -u 82 -D -S -G www-data www-data \
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
listen = [::]:9000\n\
" > /etc/php83/php-fpm.conf.temp

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

RUN  echo -e "\
[contacts]\n\
enable = On\n\
allow_sync = On\n\
type = \"sqlite\"\n\
[login]\n\
determine_user_domain = On\n\
hide_submit_button = Off\n\
login_lowercase = On\n\
sign_me_auto = \"DefaultOn\"\n\
[defaults]\n\
view_editor_type = \"Plain\"\n\
mail_reply_same_folder = On\n\
[labs]\n\
smtp_show_server_errors = On\n\
mail_func_clear_headers = On\
" > /etc/application.ini.temp

VOLUME /var/www/html/data

CMD  envsubst < /etc/php83/php-fpm.conf.temp > /etc/php83/php-fpm.conf \
  && chown -R www-data:www-data data \
  && mkdir -p data/_data_/_default_/configs/ data/_data_/_default_/domains/ \
  && { if ! ls  data/_data_/_default_/configs/application.ini 2>/dev/null; then envsubst < /etc/application.ini.temp > data/_data_/_default_/configs/application.ini; fi; } \
#  && su-exec www-data php8 index.php \
  && envsubst < /etc/domain.ini.temp > data/_data_/_default_/domains/$DOMAIN.ini \
  && sed -i -e "s/^upload_max_filesize.*/upload_max_filesize = 25M/" \
            -e "s/^post_max_size.*/post_max_size = 25M/" \
            /etc/php83/php.ini \
  && echo "extension = sodium" >> /etc/php83/php.ini \ 
  && exec php-fpm83

EXPOSE 9000
