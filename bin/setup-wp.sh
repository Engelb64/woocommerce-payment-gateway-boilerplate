#!/usr/bin/env sh
# Install / configure WordPress + WooCommerce + this plugin via WP-CLI.
# Run from the repo root after: docker compose up -d
#
# Usage:
#   sh bin/setup-wp.sh
#   # or on Windows (Git Bash):
#   bash bin/setup-wp.sh
#
# Env overrides (optional):
#   WP_URL, WP_TITLE, WP_ADMIN_USER, WP_ADMIN_PASSWORD, WP_ADMIN_EMAIL

set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
cd "$ROOT_DIR"

WP_URL=${WP_URL:-http://localhost:8080}
WP_TITLE=${WP_TITLE:-WC Gateway Boilerplate}
WP_ADMIN_USER=${WP_ADMIN_USER:-admin}
WP_ADMIN_PASSWORD=${WP_ADMIN_PASSWORD:-admin}
WP_ADMIN_EMAIL=${WP_ADMIN_EMAIL:-admin@example.com}
PLUGIN_SLUG=woocommerce-payment-gateway-boilerplate

wp() {
  docker compose run --rm wpcli "$@"
}

echo "==> Waiting for WordPress HTTP on ${WP_URL} ..."
i=0
until docker compose exec -T wordpress sh -c "php -r \"exit(@file_get_contents('http://wordpress/') ? 0 : 1);\"" >/dev/null 2>&1; do
  i=$((i + 1))
  if [ "$i" -gt 60 ]; then
    echo "WordPress did not become ready in time." >&2
    exit 1
  fi
  sleep 2
done

if ! wp core is-installed >/dev/null 2>&1; then
  echo "==> Installing WordPress..."
  wp core install \
    --url="$WP_URL" \
    --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
else
  echo "==> WordPress already installed."
fi

echo "==> Installing WooCommerce..."
wp plugin install woocommerce --activate

echo "==> Activating ${PLUGIN_SLUG}..."
wp plugin activate "$PLUGIN_SLUG"

echo "==> Creating WooCommerce pages (if needed)..."
wp wc tool run install_pages --user=1 >/dev/null 2>&1 || true

echo "==> Enabling payment gateway (stub)..."
SETTINGS_JSON='{"enabled":"yes","title":"Boilerplate Payment","description":"Pay using the boilerplate stub provider (for development).","sandbox":"yes","api_key":"","webhook_secret":"stub_secret","logging":"yes","simulate_failure":"no"}'
wp option update woocommerce_wc_gateway_boilerplate_settings "$SETTINGS_JSON" --format=json

echo "==> Creating a simple test product (if missing)..."
if ! wp post list --post_type=product --name=stub-test-product --field=ID 2>/dev/null | grep -q '[0-9]'; then
  PRODUCT_ID=$(wp wc product create --user=1 \
    --name='Stub Test Product' \
    --slug='stub-test-product' \
    --type=simple \
    --regular_price=10 \
    --porcelain)
  echo "    Product ID: ${PRODUCT_ID}"
else
  echo "    Product already exists."
fi

echo ""
echo "Setup complete."
echo "  Site:    ${WP_URL}"
echo "  Admin:   ${WP_URL}/wp-admin   (${WP_ADMIN_USER} / ${WP_ADMIN_PASSWORD})"
echo "  Gateway: WooCommerce → Settings → Payments → Payment Gateway Boilerplate"
echo "  Webhook: ${WP_URL}/?wc-api=wc_gateway_boilerplate"
echo ""
echo "Next: add the product to cart and checkout, or run smoke scripts (see DOCKER.md)."
