#!/bin/bash
set -euo pipefail
CONF_SRC="/var/app/staging/.platform/nginx/conf.d/elasticbeanstalk/health.conf"
CONF_DST_DIR="/etc/nginx/conf.d/elasticbeanstalk"
CONF_DST="$CONF_DST_DIR/10_health.conf"

sudo mkdir -p "$CONF_DST_DIR"
if [ -f "$CONF_SRC" ]; then
  sudo cp "$CONF_SRC" "$CONF_DST"
  sudo chown root:root "$CONF_DST"
  sudo chmod 0644 "$CONF_DST"
  # Test and reload nginx gracefully
  sudo nginx -t && sudo systemctl reload nginx || sudo service nginx reload
fi
