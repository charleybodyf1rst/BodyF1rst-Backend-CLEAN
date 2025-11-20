#!/bin/bash
set -euo pipefail

# Skip installing host-level PHP nginx when environment is Docker-based.
# EB Docker AL2023 already proxies to the container; this override would break it.
if command -v docker >/dev/null 2>&1; then
  if docker info >/dev/null 2>&1; then
    echo "[25-nginx-00-app-conf] Docker platform detected; skipping host PHP nginx override."
    exit 0
  fi
fi

CONF='/etc/nginx/conf.d/00_app.conf'
CORS_DIR='/etc/nginx/snippets'
CORS_SNIPPET="${CORS_DIR}/bodyf1rst_cors_headers.conf"

sudo mkdir -p "$CORS_DIR"
sudo bash -c "cat > $CORS_SNIPPET" <<'CORS'
# Shared BodyF1rst CORS header logic
set $allow_creds "";
if ($validated_origin != "") {
    set $allow_creds "true";
}
add_header Access-Control-Allow-Origin $validated_origin always;
add_header Vary "Origin" always;
add_header Access-Control-Allow-Credentials $allow_creds always;
CORS

sudo bash -c "cat > $CONF" <<'NGX'
# Authoritative app include for EB AL2023
root /var/app/current/public;
index index.php index.html;

# CORS origin validation (map-based)
map $http_origin $validated_origin {
    default "";
    "~^https://bodyf1rst\.net$" $http_origin;
    "~^https://www\.bodyf1rst\.net$" $http_origin;
    "~^https://app\.bodyf1rst\.net$" $http_origin;
    "~^https://admin-bodyf1rst\.com$" $http_origin;
    "~^https://www\.admin-bodyf1rst\.com$" $http_origin;
}

# Fast CORS preflight (avoid hitting PHP)
if ($request_method = OPTIONS) {
    add_header Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS" always;
    add_header Access-Control-Allow-Headers "Authorization, Content-Type, X-Requested-With, X-CSRF-TOKEN" always;
    include /etc/nginx/snippets/bodyf1rst_cors_headers.conf;

    return 204;
}

# /health for LB checks
location = /health {
    add_header Content-Type text/plain;
    return 200 'OK';
}

# Static files
location ~* \.(?:css|js|jpg|jpeg|gif|png|svg|ico|woff2?|ttf|otf|eot)$ {
    try_files $uri =404;
    expires 7d;
    access_log off;
    add_header Cache-Control "public";
}

# Laravel front controller
location / {
    include /etc/nginx/snippets/bodyf1rst_cors_headers.conf;
    try_files $uri $uri/ /index.php?$query_string;
}

# PHP -> php-fpm (EB AL2023)
location ~ \.php$ {
    include       fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_param DOCUMENT_ROOT   $realpath_root;
    fastcgi_index index.php;
    fastcgi_pass  unix:/run/php-fpm/www.sock;
    include /etc/nginx/snippets/bodyf1rst_cors_headers.conf;
}
client_max_body_size 16m;
NGX

sudo chown root:root "$CONF"
sudo chmod 0644 "$CONF"

# Validate & reload
if ! sudo nginx -t; then
    echo "nginx -t validation failed" >&2
    exit 1
fi

if ! sudo systemctl reload nginx; then
    echo "systemctl reload nginx failed, attempting fallback reload" >&2
    if ! sudo service nginx reload; then
        echo "Fallback service nginx reload also failed" >&2
        exit 1
    fi
fi
