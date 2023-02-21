FROM ubuntu:latest AS development

# Install all the dependencies
RUN apt-get update -yqq \
	&& apt-get upgrade -yqq \
	&& DEBIAN_FRONTEND=noninteractive apt-get install -yqq --no-install-recommends \
	nginx openssl \
	mariadb-server mariadb-client \
	php-fpm php-cli php-mysql php-gd php-zip php-bcmath php-curl php-imagick php-xml php-mbstring php-xml php-intl php-xdebug \
    php-sqlite3 php-ldap php-redis php-predis php-memcached memcached \
	supervisor \
    git curl ca-certificates less nano unzip \
    phpmyadmin \
	npm node-grunt-cli gettext

# If you need to replace something on the database
#RUN git clone "https://github.com/interconnectit/Search-Replace-DB.git" "${WORK_DIR}/Search-Replace-DB"

# Install PHP Composer
RUN curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
RUN php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer

# Set the environment variables
ARG PROJECT_NAME=${PROJECT_NAME:-simplesamlphp-module-uab}
ARG PROJECT_TITLE=${PROJECT_TITLE:-${PROJECT_NAME}}
ARG PROJECT_ADMIN_USER=${PROJECT_ADMIN_USER:-root}
ARG PROJECT_ADMIN_PWD=${PROJECT_ADMIN_PWD:-toor}
ARG PROJECT_ADMIN_EMAIL=${PROJECT_ADMIN_EMAIL:-cesperanc@gmail.com}
ARG PROJECT_URL=${PROJECT_URL:-localhost}

ARG WORK_DIR=${WORK_DIR:-/app/data}
ARG WWW_DIR=${WWW_DIR:-${WORK_DIR}/www}
ARG MIRROR_DIR=${MIRROR_DIR:-${WORK_DIR}/mirror}

ARG MYSQL_HOST=${MYSQL_HOST:-localhost}
ARG MYSQL_DATABASE=${MYSQL_DATABASE:-app_db}
ARG MYSQL_USER=${MYSQL_USER:-app_db_user}
ARG MYSQL_PASSWORD=${MYSQL_PASSWORD:-app_db_pwd}
ARG SIMPLESAMLPHP_VERSION=${SIMPLESAMLPHP_VERSION:-v2.0.0-rc3}
ARG SIMPLESAMLPHP_BRANCH=${SIMPLESAMLPHP_BRANCH:-simplesamlphp-2.0}

ARG PHP_VERSION=${PHP_VERSION:-8.1}
ARG DATABASE_BACKUP_DIR=${DATABASE_BACKUP_DIR:-/app/backup/database_files}
ENV WORK_DIR=${WORK_DIR} \
    MYSQL_HOST=${MYSQL_HOST} \
    MYSQL_DATABASE=${MYSQL_DATABASE} \
    DEBIAN_FRONTEND=${DEBIAN_FRONTEND:-noninteractive} \
    SIMPLESAMLPHP_VERSION=${SIMPLESAMLPHP_VERSION} \
    SIMPLESAMLPHP_BRANCH=${SIMPLESAMLPHP_BRANCH} \
    ENVS="WORK_DIR WWW_DIR MIRROR_DIR MYSQL_HOST MYSQL_DATABASE MYSQL_USER MYSQL_PASSWORD SIMPLESAMLPHP_BRANCH SIMPLESAMLPHP_VERSION DEBIAN_FRONTEND"


# Prepare the working directory
RUN mkdir -p "${WORK_DIR}" \
    && mkdir -p "${WWW_DIR}" \
    && chown -R www-data "${WWW_DIR}"

ENV PROJECT_FOLDER=${PROJECT_FOLDER:-${PROJECT_NAME}}
ENV PROJECT_FOLDER_ABSOLUTE=${WWW_DIR}/${PROJECT_FOLDER}
ARG PROJECT_FOLDER_ABSOLUTE=${PROJECT_FOLDER_ABSOLUTE}
RUN mkdir -p "${PROJECT_FOLDER_ABSOLUTE}" \
	&& chown -R www-data "${WWW_DIR}"

# Create the required PHP-FPM directories
RUN mkdir -p /run/php \
    && mkdir -p /var/log/php-fpm

# Create the required PHP-FPM directories
RUN mkdir -p /etc/nginx/sites-overrides

# Create SSL certificate
RUN export PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;") && echo '[dn]\n\
CN='${PROJECT_URL}'\n\
C = PT\n\
O = C3\n\
\n\
[req]\n\
default_bits = 2048\n\
distinguished_name = dn\n\
req_extensions = req_ext\n\
x509_extensions = x509_ext\n\
string_mask = utf8only\n\
\n\
# Section x509_ext is used when generating a self-signed certificate. I.e., openssl req -x509 ...\n\
[ x509_ext ]\n\
subjectKeyIdentifier = hash\n\
authorityKeyIdentifier = keyid,issuer\n\
basicConstraints = CA:FALSE\n\
keyUsage = digitalSignature\n\
extendedKeyUsage=serverAuth\n\
subjectAltName = @alternate_names\n\
nsComment = "'${PROJECT_URL}' Certificate"\n\
\n\
# Section req_ext is used when generating a certificate signing request. I.e., openssl req ...\n\
[ req_ext ]\n\
subjectKeyIdentifier = hash\n\
basicConstraints = CA:FALSE\n\
keyUsage = digitalSignature\n\
extendedKeyUsage=serverAuth\n\
subjectAltName = @alternate_names\n\
nsComment = "'${PROJECT_URL}' Certificate"\n\
\n\
[ alternate_names ]\n\
DNS.1 = '${PROJECT_URL}'\n\
DNS.2 = www.'${PROJECT_URL}'\n\
DNS.3 = localhost\n\
DNS.4 = localhost.localdomain\n\
DNS.5 = 127.0.0.1\n\
\n\
' | openssl req -x509 -days 365 -out /etc/ssl/private/"${PROJECT_URL}".crt -keyout /etc/ssl/private/"${PROJECT_URL}".key \
  -newkey rsa:2048 -nodes -sha256 \
  -subj "/CN=${PROJECT_URL}" -config /dev/stdin 2> /dev/null


# Configure nginx

## Configure PHPmyAdmin
RUN export PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;") && echo '\
location ~ ^/phpmyadmin { \n\
    root /usr/share/; \n\
    index index.php index.html index.htm; \n\
    location ~ ^/phpmyadmin/(.+\.php)$ { \n\
        try_files $uri =404; \n\
        root /usr/share/; \n\
        fastcgi_pass unix:/var/run/php-fpm.sock; \n\
        fastcgi_index index.php; \n\
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \n\
        fastcgi_param PATH_INFO $fastcgi_path_info; \n\
		fastcgi_read_timeout 3600; \n\
        include fastcgi_params; \n\
        fastcgi_intercept_errors off; \n\
        fastcgi_buffers 16 16k; \n\
        fastcgi_buffer_size 32k; \n\
    } \n\
    \n\
    location ~* ^/phpmyadmin/(.+\.(jpg|jpeg|gif|css|png|js|ico|html|xml|txt))$ { \n\
        root /usr/share/; \n\
    } \n\
    \n\
}' \
> /etc/nginx/snippets/phpmyadmin.conf

RUN rm /etc/nginx/sites-enabled/default && export PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;") && echo '\
# Include custom overrides from host \n\
include /etc/nginx/sites-overrides/*;\n\
\n\
# Expires map \n\
map_hash_max_size 128; \n\
map_hash_bucket_size 128; \n\
map $sent_http_content_type $expires { \n\
    default                    off; \n\
    text/html                  epoch; \n\
    text/css                   1y; \n\
    application/javascript     1y; \n\
    ~image/                    1y; \n\
} \n\
 \n\
server { \n\
    listen 80; \n\
    listen [::]:80; \n\
    listen 443 ssl http2; \n\
    listen [::]:443 ssl http2; \n\
    \n\
    server_name '${PROJECT_URL}'; \n\
    server_tokens off; \n\
    # Max body size (for uploads) \n\
    client_max_body_size 2000M; \n\
    # SSL settings \n\
    ssl_certificate /etc/ssl/private/'${PROJECT_URL}'.crt; \n\
    ssl_certificate_key /etc/ssl/private/'${PROJECT_URL}'.key; \n\
    add_header Strict-Transport-Security "max-age=31536000"; \n\
    ssl_protocols TLSv1 TLSv1.1 TLSv1.2; \n\
    ssl_ciphers !aNULL:!eNULL:FIPS@STRENGTH; \n\
    ssl_prefer_server_ciphers on; \n\
    \n\
    # Enable compression \n\
    gzip on; \n\
    gzip_vary on; \n\
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript; \n\
    # File system structure \n\
    set $CUSTOM_ROOT '${PROJECT_FOLDER_ABSOLUTE}'/public/; \n\
    root $CUSTOM_ROOT; \n\
    index index.html index.php; \n\
    # Logs \n\
    access_log /var/log/nginx/localhost-access.log; \n\
    error_log /var/log/nginx/localhost-error.log; \n\
    \n\
    ## Restrictions ## \n\
    location ~ /\. { \n\
        deny all; \n\
    } \n\
	\n\
    # Deny access of PHP files in the uploads and files directory \n\
    location ~* /(?:uploads|files)/.*\.php$ { \n\
        deny all; \n\
    } \n\
	# Cache management \n\
	expires $expires; \n\
	\n\
    # Strip the site subpath \n\
    if (!-e $request_filename) { \n\
        rewrite ^(/phpmyadmin/?.*)$ $1 break; \n\
    } \n\
\n\
    include snippets/phpmyadmin.conf;\n\
\n\
    location ~ ^/ {\n\
        alias $CUSTOM_ROOT;\n\
\n\
        location ~ ^(?<prefix>/)(?<phpfile>.+?\.php)(?<pathinfo>.*)?$ {\n\
            alias $CUSTOM_ROOT;\n\
            try_files $phpfile =404;\n\
            #root /usr/share/;\n\
            fastcgi_pass unix:/var/run/php-fpm.sock;\n\
            fastcgi_index index.php;\n\
            fastcgi_param SCRIPT_FILENAME $CUSTOM_ROOT$phpfile;\n\
            #fastcgi_param PATH_INFO $fastcgi_path_info;\n\
            fastcgi_param SCRIPT_NAME $prefix$phpfile;\n\
            fastcgi_param PATH_INFO $pathinfo if_not_empty;\n\
            fastcgi_read_timeout 3600;\n\
            include fastcgi_params;\n\
            fastcgi_intercept_errors on;\n\
            fastcgi_buffers 16 16k;\n\
            fastcgi_buffer_size 32k;\n\
        }\n\
\n\
        location ~ ^(?<prefix>/)(?<file>.+?)$ {\n\
            alias $CUSTOM_ROOT;\n\
            try_files $file =404;\n\
        }\n\
    }\n\
\n\
}' \
> /etc/nginx/sites-enabled/localhost.conf

RUN export PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;") && echo '\n\
catch_workers_output = yes \n\
listen = /var/run/php-fpm.sock \n\
' \
>> "/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"

RUN phpdismod xdebug

# Mirror WWW_DIR
RUN echo '#!/usr/bin/env bash\n\
\n\
function unmount {\n\
  umount "'${MIRROR_DIR}'/www/'${PROJECT_FOLDER}'/modules/uab"\n\
  umount "'${MIRROR_DIR}'/www"\n\
  exit 0\n\
} \n\
\n\
trap unmount SIGTERM\n\
\n\
mkdir -p "'${MIRROR_DIR}'/www"\n\
mkdir -p "'${WORK_DIR}'/null"\n\
mount --bind "'${WWW_DIR}'" "'${MIRROR_DIR}'/www"\n\
mount --make-shared "'${MIRROR_DIR}'/www"\n\
mount --bind "'${WORK_DIR}'/null" "'${MIRROR_DIR}'/www/'${PROJECT_FOLDER}'/modules/uab"\n\
touch "'${MIRROR_DIR}'/www/.gitkeep"\n\
\n\
while true; do\n\
  sleep 1\n\
done\n\
' \
> "${WORK_DIR}"/bind_mount.bash && chmod +x "${WORK_DIR}"/bind_mount.bash

# Configure supervisord
RUN export PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;") && echo '\
[unix_http_server] \n\
file=/var/run/supervisor.sock \n\
chmod=0700 \n\
chown= nobody:nogroup \n\
username = docker_services \n\
password = docker_services_pwd \n\
\n\
[supervisord] \n\
logfile=/var/log/supervisor/supervisord.log \n\
pidfile=/var/run/supervisord.pid \n\
childlogdir=/var/log/supervisor \n\
user=root \n\
#nodaemon=true \n\
\n\
[rpcinterface:supervisor] \n\
supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface \n\
\n\
[supervisorctl] \n\
serverurl=unix:///var/run/supervisor.sock \n\
username = docker_services \n\
password = docker_services_pwd \n\
\n\
[program:mariadb]\n\
command=/usr/bin/mysqld_safe \n\
user=root \n\
autostart=true \n\
autorestart=true \n\
redirect_stderr=true \n\
priority=20 \n\
\n\
[program:php-fpm]\n\
command=/usr/sbin/php-fpm'${PHP_VERSION}' -R -F -O -d catch_workers_output=yes -d access.log=/proc/self/fd/2 -d error_log=/proc/self/fd/2 -d display_errors=yes\n\
user=root \n\
autostart=true \n\
autorestart=true \n\
redirect_stderr=true \n\
priority=30 \n\
stdout_logfile=/dev/stdout\n\
stdout_logfile_maxbytes=0\n\
\n\
[program:nginx]\n\
command=/usr/sbin/nginx -c /etc/nginx/nginx.conf -g "daemon off;" \n\
directory='${WWW_DIR}' \n\
user=root \n\
autostart=true \n\
autorestart=true \n\
redirect_stderr=true \n\
priority=40 \n\
\n\
[program:memcached]\n\
command=memcached -m 128 -p 11211 -u memcache -l 127.0.0.1\n\
autostart=true\n\
autorestart=true\n\
startsecs=10\n\
startretries=3\n\
user=root\n\
redirect_stderr=true\n\
stdout_logfile=/dev/stdout\n\
stdout_logfile_maxbytes=0\n\
\n\
[program:bindmount]\n\
command="'${WORK_DIR}'/bind_mount.bash" \n\
user=root \n\
stdout_logfile=/proc/self/fd/1 \n\
stdout_logfile_maxbytes=0 \n\
redirect_stderr=true \n\
\n\
[group:web] \n\
programs=bindmount,memcached,mariadb,php-fpm,nginx \n\
priority=999 \n\
\n\
' \
> "${WORK_DIR}/supervisord.conf" && ln -sf "${WORK_DIR}/supervisord.conf" /etc/supervisor/supervisord.conf \
&& echo '#!/usr/bin/env sh \n\
\n\
# Start supervisor in daemon mode \n\
/usr/bin/supervisord -c "'${WORK_DIR}'/supervisord.conf" \n\
' \
> "${WORK_DIR}/start-daemons.sh" && chmod +x "${WORK_DIR}/start-daemons.sh" \
&& echo '#!/usr/bin/env bash \n\
\n\
# Stop supervisord servives \n\
/usr/bin/supervisorctl -c "'${WORK_DIR}'/supervisord.conf" stop all \
&& kill -s SIGTERM $(supervisorctl -c "'${WORK_DIR}'/supervisord.conf" pid) \n\
' \
> "${WORK_DIR}/stop-daemons.sh" && chmod +x "${WORK_DIR}/stop-daemons.sh" \
&& echo '#!/usr/bin/env bash \n\
\n\
exec /usr/bin/supervisord -n -c "${WORK_DIR}/supervisord.conf" \n\
' \
> "/usr/bin/start_container_services.bash" && chmod +x "/usr/bin/start_container_services.bash"

# Prepare the database
RUN echo '#!/usr/bin/env bash \n\
/usr/bin/mysqld_safe > /dev/null & \n\
while ! mysqladmin ping -h"'${MYSQL_HOST}'" --silent -s; do \n\
    sleep 1 \n\
done \n\
\n\
echo "CREATE DATABASE IF NOT EXISTS \\`'${MYSQL_DATABASE}'\\` ;" | /usr/bin/mysql \n\
echo "CREATE USER /*M!100103 IF NOT EXISTS */ \\"'${MYSQL_USER}'\\"@\\"'${MYSQL_HOST}'\\" IDENTIFIED BY \\"'${MYSQL_PASSWORD}'\\" ; \
	  GRANT ALL ON \\`'${MYSQL_DATABASE}'\\`.* TO \\"'${MYSQL_USER}'\\"@\\"'${MYSQL_HOST}'\\" WITH GRANT OPTION ; \
	  FLUSH PRIVILEGES;" | /usr/bin/mysql \n\
\n\
echo " \n\
    USE \\`'${MYSQL_DATABASE}'\\`;\n\
    CREATE TABLE IF NOT EXISTS \\`credentials\\` ( \n\
        \\`creation_date\\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, \n\
        \\`user_id\\` VARCHAR(80) NOT NULL, \n\
        \\`credentialId\\` VARCHAR(500) NOT NULL, \n\
        \\`credential\\` MEDIUMBLOB NOT NULL, \n\
        \\`signCounter\\` INT NOT NULL, \n\
        \\`friendlyName\\` VARCHAR(100) DEFAULT \\"Unnamed Token\\", \n\
        UNIQUE (\\`user_id\\`,\\`credentialId\\`) \n\
    ); \n\
" | /usr/bin/mysql \n\
\n\
echo " \n\
    USE \\`'${MYSQL_DATABASE}'\\`;\n\
    ALTER TABLE \\`credentials\\` ADD COLUMN \\`algo\\` INT DEFAULT NULL AFTER \\`credential\\`; \n\
    ALTER TABLE \\`credentials\\` ADD COLUMN \\`presenceLevel\\` INT DEFAULT NULL AFTER \\`algo\\`; \n\
" | /usr/bin/mysql \n\
\n\
# GRANT SELECT,INSERT,UPDATE,DELETE ON  \\`'${MYSQL_DATABASE}'\\`.\\`credentials\\` TO \\"...dbuser\\"@\\"1.2.3.4\\" IDENTIFIED BY \\"...dbpass\\";\n\
\n\
echo " \n\
    USE \\`'${MYSQL_DATABASE}'\\`;\n\
    CREATE TABLE IF NOT EXISTS \\`userstatus\\` ( \n\
        \\`user_id\\` VARCHAR(80) NOT NULL, \n\
        \\`fido2Status\\` ENUM(\\"FIDO2Disabled\\",\\"FIDO2Enabled\\") NOT NULL DEFAULT \\"FIDO2Disabled\\", \n\
        UNIQUE (\\`user_id\\`) \n\
    ); \n\
" | /usr/bin/mysql \n\
# GRANT SELECT ON  \\`'${MYSQL_DATABASE}'\\`.\\`userstatus\\` TO \\"...dbuser\\"@\\"1.2.3.4\\" IDENTIFIED BY \\"...dbpass\\";\n\
\n\
echo " \n\
    USE \\`'${MYSQL_DATABASE}'\\`;\n\
    \n\
    CREATE TABLE IF NOT EXISTS \\`uab_user_attributes_matching__tbl\\` ( \n\
        \\`identity_ID\\` bigint(20) DEFAULT NULL COMMENT \\"Refers to a common identity ID (if aplicable)\\", \n\
        \\`auth_source_primary\\` varchar(100) DEFAULT \\"ldap\\" COMMENT \\"1st attribute source\\", \n\
        \\`auth_source_primary_match_field\\` varchar(250) DEFAULT \\"sAMAccountName\\" COMMENT \\"1st attribute to match\\", \n\
        \\`auth_source_primary_match_value\\` varchar(250) NOT NULL COMMENT \\"1st attribute value to match\\", \n\
        \\`auth_source_secondary\\` varchar(100) NOT NULL DEFAULT \\"autenticacao_gov\\" COMMENT \\"2nd attribute source\\", \n\
        \\`auth_source_secondary_match_field\\` varchar(250) NOT NULL DEFAULT \\"NIF\\" COMMENT \\"2nd attribute to match\\", \n\
        \\`auth_source_secondary_match_value\\` varchar(250) NOT NULL COMMENT \\"2nd attribute to match\\", \n\
        \\`_inserted\\` datetime NOT NULL DEFAULT current_timestamp(), \n\
        \\`_last_update\\` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), \n\
        UNIQUE KEY \\`unique_id_map\\` (\n\
            \\`identity_ID\\`,\n\
            \\`auth_source_primary\\`,\n\
            \\`auth_source_primary_match_field\\`,\n\
            \\`auth_source_primary_match_value\\`,\n\
            \\`auth_source_secondary\\`,\n\
            \\`auth_source_secondary_match_field\\`,\n\
            \\`auth_source_secondary_match_value\\`\n\
        ) \n\
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT=\\"Associate attributes of multiple IDP identities\\"; \n\
 \n\
" | /usr/bin/mysql \n\
\n\
mysqladmin shutdown \n\
' \
> "${WORK_DIR}"/prepare-db.bash && chmod +x "${WORK_DIR}"/prepare-db.bash && "${WORK_DIR}"/prepare-db.bash && rm -f "${WORK_DIR}"/prepare-db.bash

# Configure the project folder
RUN git config --global --add advice.detachedHead false \
    && git config --global --add safe.directory "${PROJECT_FOLDER_ABSOLUTE}" \
    && git clone -b "${SIMPLESAMLPHP_BRANCH}" --single-branch -o upstream https://github.com/simplesamlphp/simplesamlphp.git "${PROJECT_FOLDER_ABSOLUTE}" \
#    && git clone -b "${SIMPLESAMLPHP_VERSION}" https://github.com/simplesamlphp/simplesamlphp.git "${PROJECT_FOLDER_ABSOLUTE}" \
#    && cd "${PROJECT_FOLDER_ABSOLUTE}" \
#    && git remote add upstream https://github.com/simplesamlphp/simplesamlphp.git \
#    && git fetch upstream \
#    && git checkout "${SIMPLESAMLPHP_VERSION}"
    && echo "Ok"

RUN cd "${PROJECT_FOLDER_ABSOLUTE}" \
    && composer config version "${SIMPLESAMLPHP_VERSION}" \
    && composer update --no-progress \
    && composer require simplesamlphp/simplesamlphp-module-ldap"${SIMPLESAMLPHP_VERSION_LDAP}" --no-progress \
    && composer require simplesamlphp/simplesamlphp-module-webauthn"${SIMPLESAMLPHP_VERSION_WEBAUTHN}" --no-progress

RUN mkdir -p "${PROJECT_FOLDER_ABSOLUTE}/modules/uab"

COPY --chown=www-data . "${PROJECT_FOLDER_ABSOLUTE}/modules/uab"

WORKDIR "${PROJECT_FOLDER_ABSOLUTE}"

#VOLUME "${WWW_DIR}"
VOLUME "/var/lib/mysql/"

# Create the entrypoint script
RUN echo '#!/usr/bin/env sh \n\
exec "$@"\n\
' \
> /usr/bin/entrypoint.sh && chmod +x /usr/bin/entrypoint.sh

# Set the entrypoint
ENTRYPOINT ["/usr/bin/entrypoint.sh"]
# Start the daemons
CMD ["/usr/bin/start_container_services.bash"]
EXPOSE 80 443
