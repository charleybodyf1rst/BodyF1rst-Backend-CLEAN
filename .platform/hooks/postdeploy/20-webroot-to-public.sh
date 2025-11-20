#!/bin/bash
set -euxo pipefail
LOG=/var/log/atlas-webroot.log
{
  echo "=== POSTDEPLOY WEBROOT FIX BEGIN === $(date)"
  echo "[BEFORE]"; ls -al /var/www/html || true
  rm -rf /var/www/html
  ln -s /var/app/current/public /var/www/html
  chown -h root:root /var/www/html
  echo "[AFTER ]"; ls -al /var/www/html || true
  nginx -t && systemctl reload nginx || service nginx reload
  echo "=== POSTDEPLOY WEBROOT FIX END === $(date)"
} | tee -a "$LOG"
