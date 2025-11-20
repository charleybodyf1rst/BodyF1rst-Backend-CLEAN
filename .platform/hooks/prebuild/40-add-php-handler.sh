#!/bin/bash
set -euxo pipefail
STAGE="/var/proxy/staging/nginx/nginx.conf"
BACKUP="${STAGE}.bak.$(date +%s)"

if [ -f "$STAGE" ]; then
  cp "$STAGE" "$BACKUP"
fi

# If no fastcgi block, inject one inside the default server
if ! grep -q "fastcgi_pass .*php-fpm" "$STAGE"; then
  awk '
    /server *{/ {print; inserver=1; next}
    inserver && /index index\.html;/ && !done {
      print "        index index.php index.html;"
      print "        location ~ \\.php$ {"
      print "            include       fastcgi_params;"
      print "            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;"
      print "            fastcgi_param DOCUMENT_ROOT   $realpath_root;"
      print "            fastcgi_index index.php;"
      print "            fastcgi_pass  unix:/run/php-fpm/www.sock;"
      print "        }"
      done=1; next
    }
    {print}
  ' "$STAGE" > "${STAGE}.tmp" && mv "${STAGE}.tmp" "$STAGE"
fi

echo "[OK] Injected PHP handler into $STAGE"
