#!/bin/bash
set -euo pipefail

echo "=== GENERATING ENVIRONMENT-SPECIFIC NGINX CONFIG ==="

# Determine environment (default to production)
ENVIRONMENT=${ENVIRONMENT:-production}
echo "Environment: $ENVIRONMENT"

# Source template
TEMPLATE_FILE="/var/app/staging/.platform/nginx/nginx.conf.template"
OUTPUT_FILE="/var/app/staging/.platform/nginx/nginx.conf"

if [ ! -f "$TEMPLATE_FILE" ]; then
    echo "ERROR: Template file not found: $TEMPLATE_FILE"
    exit 1
fi

# Copy template to output
cp "$TEMPLATE_FILE" "$OUTPUT_FILE"

# Replace placeholder based on environment
if [ "$ENVIRONMENT" = "development" ] || [ "$ENVIRONMENT" = "dev" ] || [ "$ENVIRONMENT" = "local" ]; then
    echo "Adding localhost origins for development environment"
    # Add localhost origins for development
    sed -i 's/# DEVELOPMENT_ORIGINS_PLACEHOLDER/        "~^http:\/\/localhost:4200$" $http_origin;\
        "~^http:\/\/localhost:8100$" $http_origin;\
        "~^http:\/\/127\.0\.0\.1:4200$" $http_origin;\
        "~^http:\/\/127\.0\.0\.1:8100$" $http_origin;/' "$OUTPUT_FILE"
else
    echo "Production environment - no localhost origins"
    # Remove the placeholder line for production
    sed -i '/# DEVELOPMENT_ORIGINS_PLACEHOLDER/d' "$OUTPUT_FILE"
fi

echo "Generated nginx.conf for environment: $ENVIRONMENT"
echo "Output file: $OUTPUT_FILE"

# Validate nginx config syntax
if command -v nginx >/dev/null 2>&1; then
    if nginx -t -c "$OUTPUT_FILE"; then
        echo "Nginx config validation: PASSED"
    else
        echo "Nginx config validation: FAILED"
        exit 1
    fi
fi

echo "=== NGINX CONFIG GENERATION COMPLETE ==="
