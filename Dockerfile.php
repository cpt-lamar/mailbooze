FROM container4armhf/armhf-alpine

WORKDIR /var/www/html

RUN set -x \
  && apk update \
  && apk add --no-cache unzip curl php5 php5-fpm php5-curl php5-iconv php5-json php5-xml php5-dom php5-openssl php5-zlib php5-opcache php5-gd php5-pdo_pgsql msmtp gettext su-exec \
  && addgroup -g 82 -S www-data \
  && adduser -u 82 -D -S -G www-data www-data \
  && curl http://www.rainloop.net/repository/webmail/rainloop-community-latest.zip -o rainloop.zip \
  && unzip rainloop.zip \
  && rm rainloop.zip \
  && rm -r ./rainloop/v/*/static/ \
  && find . -type d -exec chmod 755 {} \; \
  && find . -type f -exec chmod 644 {} \; \
  && find . -path */MailSo/Base/Utils.php -exec \
      sed -i 's/\$sFirst = \$sLine{1};/\$sFirst = \\substr(\$sLine,0,1);/' {} \; \
  && chown -R www-data:www-data .

ENV PHP_PROCS\
  DOMAIN\
  MDA_HOST\
  MDA_PORT\
  MSA_HOST\
  MSA_PORT\
  MSA_AUTH\
  MSA_TLS\
  MSA_STARTTLS\
  MSA_USER\
  MSA_PASSWORD\
  DB_HOST\
  DB_TYPE\
  DB_NAME\
  DB_PORT\
  DB_USER\
  DB_PASSWORD

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
listen = [::]:9000\n" > /etc/php5/php-fpm.conf.temp

RUN echo -e "sendmail_path = msmtp --read-envelope-from -t" >> /etc/php5/php.ini

#msmtp (sendmail) configuration
RUN echo -e "\
account default\n\
host \$MSA_HOST\n\
port \$MSA_PORT\n\
auth \$MSA_AUTH\n\
tls \$MSA_TLS\n\
tls_starttls \$MSA_STARTTLS\n\
tls_certcheck off\n\
#from \$MSA_USER\n\
user \$MSA_USER\n\
password \$MSA_PASSWORD \n\
" > /etc/msmtprc.temp

VOLUME /var/www/html/data

CMD  envsubst < /etc/php5/php-fpm.conf.temp > /etc/php5/php-fpm.conf \
  && envsubst < /etc/msmtprc.temp > /etc/msmtprc \
  && chown www-data:www-data data \
  && su-exec www-data php index.php \
  && su-exec www-data echo -e "imap_host = \"$MDA_HOST\"\nimap_port = $MDA_PORT\nsmtp_php_mail = On" > data/_data_/_default_/domains/$DOMAIN.ini \
  && su-exec www-data sed -i -e "/^\[contacts\]/,/^\[.*\]/ s|^enable.*$|enable = On|" \
            -e "/^\[debug\]/,/^\[.*\]/ s|^enable *=.*$|enable = Off|" \
            -e "s/^mail_func_clear_headers.*/mail_func_clear_headers = On/" \
            -e "s/^type.*$/type = $DB_TYPE/" \
            -e "s/^pdo_dsn.*$/pdo_dsn = \"$DB_TYPE:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME\"/" \
            -e "s/^pdo_user.*$/pdo_user = $DB_USER/" \
            -e "s/^pdo_password.*$/pdo_password = $DB_PASSWORD/" \
            data/_data_/_default_/configs/application.ini \
  && php-fpm

EXPOSE 9000
