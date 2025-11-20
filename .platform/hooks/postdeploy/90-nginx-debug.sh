#!/bin/bash
set -euo pipefail
echo "=== EB DEBUG BEGIN ==="
date
echo "-- nginx -t --"
sudo nginx -t || true
echo "-- nginx -T (first 200) --"
sudo nginx -T 2>&1 | head -n 200
echo "-- ls includes --"
/bin/ls -al /etc/nginx/conf.d /etc/nginx/conf.d/elasticbeanstalk || true
echo "-- webroot link --"
/bin/ls -al /var/www/html || true
echo "-- php-fpm status --"
systemctl is-active php-fpm || systemctl status php-fpm --no-pager || true
echo "=== EB DEBUG END ==="
