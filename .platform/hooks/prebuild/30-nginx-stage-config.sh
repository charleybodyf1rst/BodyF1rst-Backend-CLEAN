#!/bin/bash
set -euo pipefail

STAGE_DIR="/var/proxy/staging/nginx/conf.d"
sudo mkdir -p "$STAGE_DIR"

APP_CONF="$STAGE_DIR/00_app.conf"

DEPLOY_ENV=${DEPLOY_ENV:-production}
INCLUDE_LOCALHOST=${INCLUDE_LOCALHOST:-}

if [[ -z "${INCLUDE_LOCALHOST}" ]]; then
    case "${DEPLOY_ENV,,}" in
        dev|development|local|staging)
            INCLUDE_LOCALHOST=true
            ;;
        *)
            INCLUDE_LOCALHOST=false
            ;;
    esac
fi

LOCALHOST_ORIGINS=""
if [[ "${INCLUDE_LOCALHOST,,}" == "true" ]]; then
    LOCALHOST_ORIGINS=$'    "~^http://localhost:4200$" \$http_origin;
    "~^http://localhost:8100$" \$http_origin;'
fi

sudo bash -c "cat > '$APP_CONF'" <<NGX
# CORS origin validation maps
map \$http_origin \$cors_origin {
    default "";
    "~^https://bodyf1rst\.net$" \$http_origin;
    "~^https://www\.bodyf1rst\.net$" \$http_origin;
    "~^https://app\.bodyf1rst\.net$" \$http_origin;
${LOCALHOST_ORIGINS}
}

map \$cors_origin \$cors_creds {
    default "";
    "" "";
    "~^https?://" "true";
}

map \$request_method \$cors_try_target {
    default "/index.php?\$query_string";
    OPTIONS @cors_preflight;
}

server {
    listen 80 default_server;
    server_name _;

    root /var/app/current/public;
    index index.php index.html;

    location = /health {
        add_header Content-Type text/plain;
        return 200 'OK';
    }

    location @cors_preflight {
        add_header Access-Control-Allow-Origin \$cors_origin always;
        add_header Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS" always;
        add_header Access-Control-Allow-Headers "Authorization, Content-Type, X-Requested-With, X-CSRF-TOKEN" always;
        add_header Access-Control-Allow-Credentials \$cors_creds always;
        add_header Vary "Origin" always;
        return 204;
    }

    location / {
        add_header Access-Control-Allow-Origin \$cors_origin always;
        add_header Vary "Origin" always;
        add_header Access-Control-Allow-Credentials \$cors_creds always;
        try_files \$uri \$uri/ \$cors_try_target;
    }

    location ~ \.php$ {
        include       fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT   \$realpath_root;
        fastcgi_index index.php;
        fastcgi_pass  unix:/run/php-fpm/www.sock;
    }

    client_max_body_size 16m;
}
NGX

echo "=== PREBUILD PATCHED /var/proxy/staging/nginx/conf.d/00_app.conf ==="
ls -al "$APP_CONF"
