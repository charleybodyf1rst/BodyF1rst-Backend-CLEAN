#!/bin/bash
set -euxo pipefail
STAGE_DIR="/var/proxy/staging/nginx/conf.d"
sudo mkdir -p "$STAGE_DIR"

sudo bash -c "cat > '$STAGE_DIR/00_app.conf'" <<'NGX'
# CORS origin validation map
map \$http_origin \$cors_allowed {
    default "";
    "~^https://bodyf1rst\.net$" \$http_origin;
    "~^https://www\.bodyf1rst\.net$" \$http_origin;
    "~^https://app\.bodyf1rst\.net$" \$http_origin;
}

server {
    listen 80 default_server;
    server_name _;

    root /var/app/current/public;
    index index.php index.html;

    # Laravel front controller
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP → php-fpm
    location ~ \.php$ {
        include       fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT   $realpath_root;
        fastcgi_index index.php;
        fastcgi_pass  unix:/run/php-fpm/www.sock;
    }

    # Health (header before return)
    location = /health {
        add_header Content-Type text/plain;
        return 200 'OK';
    }

    # Secure CORS preflight with origin validation
    if (\$request_method = OPTIONS) {
        add_header Access-Control-Allow-Origin \$cors_allowed always;
        add_header Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS" always;
        add_header Access-Control-Allow-Headers "Authorization, Content-Type, X-Requested-With, X-CSRF-TOKEN" always;
        add_header Vary "Origin" always;
        
        # Only allow credentials for validated origins
        set \$cors_creds "";
        if (\$cors_allowed != "") {
            set \$cors_creds "true";
        }
        add_header Access-Control-Allow-Credentials \$cors_creds always;
        return 204;
    }

    client_max_body_size 16m;
}
NGX
