#!/usr/bin/env bash
echo Starting server

set -u
set -e

cat > /app/app/config/parameters.yml <<EOF
parameters:
  mailer_transport: ${SYMFONY__MM_DASHBOARD__MAILER__TRANSPORT}
  mailer_host: ${SYMFONY__MM_DASHBOARD__MAILER__HOST}
  mailer_user: ${SYMFONY__MM_DASHBOARD__MAILER__USER}
  mailer_password: ${SYMFONY__MM_DASHBOARD__MAILER__PASSWORD}
  mailer_port: ${SYMFONY__MM_DASHBOARD__MAILER__PORT}
  mailer_encryption: ${SYMFONY__MM_DASHBOARD__MAILER__ENCRYPTION}
  mail_from: ${SYMFONY__MM_DASHBOARD__MAIL__FROM}
  mail_cc: ${SYMFONY__MM_DASHBOARD__MAIL__CC}
  secret: ${SYMFONY__MM_DASHBOARD__SECRET}
  markt_api.url: ${SYMFONY__MM_DASHBOARD__API_URL}
  markt_api.guzzle.verify: true
EOF

php composer.phar install

cd /app/app
php console cache:clear --env=prod
php console cache:warmup --env=prod
chown -R www-data:www-data /app/app/cache && find /app/app/cache -type d -exec chmod -R 0770 {} \; && find /app/app/cache -type f -exec chmod -R 0660 {} \;
php console assetic:dump --env=prod

service php7.0-fpm start
nginx -g "daemon off;"