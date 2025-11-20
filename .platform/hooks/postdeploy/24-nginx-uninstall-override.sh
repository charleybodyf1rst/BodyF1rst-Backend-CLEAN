#!/bin/bash
set -euo pipefail
CONF="/etc/nginx/conf.d/00_app.conf"
if [ -f "$CONF" ]; then
  sudo rm -f "$CONF"
fi
sudo nginx -t && sudo systemctl reload nginx || sudo service nginx reload
