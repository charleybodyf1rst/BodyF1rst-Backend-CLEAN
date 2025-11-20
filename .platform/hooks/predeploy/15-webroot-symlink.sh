#!/bin/bash
set -euo pipefail
echo "=== PREDEPLOY SYMLINK BEGIN ==="
date

# Make /var/www/html point at the deployed app's public/ folder.
echo "-- Removing existing /var/www/html --"
if [ -L /var/www/html ] || [ -d /var/www/html ]; then
  sudo rm -rf /var/www/html
fi

echo "-- Creating symlink /var/www/html -> /var/app/current/public --"
sudo ln -s /var/app/current/public /var/www/html
sudo chown -h root:root /var/www/html

echo "-- Verifying symlink --"
/bin/ls -al /var/www/html || true

# Secure permissions (targeted approach)
echo "-- Setting secure permissions --"

# Change ownership to web user
sudo chown -R webapp:webapp /var/app/current

# Set directory permissions
sudo find /var/app/current -type d -exec chmod 755 {} \;

# Set writable directories for Laravel
sudo chmod -R 775 /var/app/current/storage
sudo chmod -R 775 /var/app/current/bootstrap/cache

# Preserve execute permissions for scripts
sudo chmod 755 /var/app/current/artisan
if [ -d "/var/app/current/bin" ]; then
    sudo chmod -R 755 /var/app/current/bin
fi

# Set secure permissions for sensitive files
if [ -f "/var/app/current/.env" ]; then
    sudo chmod 600 /var/app/current/.env
fi

# Set regular file permissions (excluding executables)
sudo find /var/app/current -type f ! -path "*/storage/*" ! -path "*/bootstrap/cache/*" ! -name "artisan" ! -path "*/bin/*" ! -name "*.sh" ! -perm /111 -exec chmod 644 {} \;

echo "=== PREDEPLOY SYMLINK END ==="
