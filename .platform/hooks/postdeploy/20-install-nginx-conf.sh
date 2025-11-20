#!/bin/bash
set -euo pipefail

# Copy EB nginx snippets to the EB-managed include folder which is loaded
# inside the main server block on AL2023 Docker environments.
SRC_DIR="/var/app/staging/.platform/nginx/conf.d/elasticbeanstalk"
DST_DIR="/etc/nginx/conf.d/elasticbeanstalk"
sudo mkdir -p "$DST_DIR"

copy_conf() {
  local src="$1"; shift
  local dest_name="$1"; shift
  if [ -f "$src" ]; then
    local dst="$DST_DIR/$dest_name"
    sudo cp "$src" "$dst"
    sudo chown root:root "$dst"
    sudo chmod 0644 "$dst"
  fi
}

# Install as ordered conf files
copy_conf "$SRC_DIR/health.conf" "10_health.conf"
copy_conf "$SRC_DIR/preflight.conf" "11_cors_preflight.conf"

# Validate and reload nginx
sudo nginx -t && sudo systemctl reload nginx || sudo service nginx reload
