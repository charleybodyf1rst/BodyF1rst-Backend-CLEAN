#!/bin/bash
set -euo pipefail

# Ensure the host Nginx server (00_elastic_beanstalk_proxy.conf) also serves
# a local /health independent of the container, and attaches CORS headers for
# browser-visible errors. This file is included by the default server via:
#   include conf.d/elasticbeanstalk/*.conf;

TARGET_DIR="/etc/nginx/conf.d/elasticbeanstalk"
TARGET_CONF="$TARGET_DIR/00_health_override.conf"

sudo mkdir -p "$TARGET_DIR"
sudo bash -c "cat > $TARGET_CONF" <<'NGINX'
# Lightweight health + global ACAO headers for host-level responses

# Add CORS headers for host-generated responses (incl. 4xx/5xx)
set $validated_origin "";
if ($http_origin ~* "^https://bodyf1rst\.net$") { set $validated_origin $http_origin; }
if ($http_origin ~* "^https://www\.bodyf1rst\.net$") { set $validated_origin $http_origin; }
if ($http_origin ~* "^https://app\.bodyf1rst\.net$") { set $validated_origin $http_origin; }
if ($http_origin ~* "^https://admin-bodyf1rst\.com$") { set $validated_origin $http_origin; }
if ($http_origin ~* "^https://www\.admin-bodyf1rst\.com$") { set $validated_origin $http_origin; }
if ($http_origin ~* "^https://[a-z0-9-]+\.amplifyapp\.com$") { set $validated_origin $http_origin; }

set $allow_creds "";
if ($validated_origin != "") { set $allow_creds "true"; }

add_header Access-Control-Allow-Origin $validated_origin always;
add_header Access-Control-Allow-Credentials $allow_creds always;
add_header Vary "Origin" always;

location = /health {
    add_header Content-Type text/plain;
    return 200 'OK';
}

location /__host-cors-check {
    return 204;
}
NGINX

sudo chown root:root "$TARGET_CONF"
sudo chmod 0644 "$TARGET_CONF"

sudo nginx -t && sudo systemctl reload nginx || sudo service nginx reload

