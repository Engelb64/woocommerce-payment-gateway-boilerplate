# Install / configure WordPress + WooCommerce + this plugin via WP-CLI (Docker).
# Run from the repo root after: docker compose up -d
#
# Usage:
#   powershell -ExecutionPolicy Bypass -File bin\setup-wp.ps1

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

$WP_URL = if ($env:WP_URL) { $env:WP_URL } else { "http://localhost:8080" }
$WP_TITLE = if ($env:WP_TITLE) { $env:WP_TITLE } else { "WC Gateway Boilerplate" }
$WP_ADMIN_USER = if ($env:WP_ADMIN_USER) { $env:WP_ADMIN_USER } else { "admin" }
$WP_ADMIN_PASSWORD = if ($env:WP_ADMIN_PASSWORD) { $env:WP_ADMIN_PASSWORD } else { "admin" }
$WP_ADMIN_EMAIL = if ($env:WP_ADMIN_EMAIL) { $env:WP_ADMIN_EMAIL } else { "admin@example.com" }
$PluginSlug = "woocommerce-payment-gateway-boilerplate"

function Invoke-Wp {
  param([Parameter(Mandatory = $true)][string[]]$WpArgs)
  & docker compose run --rm wpcli @WpArgs
  if ($LASTEXITCODE -ne 0) {
    throw "wp-cli failed: wp $($WpArgs -join ' ')"
  }
}

Write-Host "==> Waiting for WordPress..."
$ready = $false
for ($i = 0; $i -lt 60; $i++) {
  & docker compose exec -T wordpress sh -c "php -r `"exit(@file_get_contents('http://wordpress/') ? 0 : 1);`"" 2>$null | Out-Null
  if ($LASTEXITCODE -eq 0) {
    $ready = $true
    break
  }
  Start-Sleep -Seconds 2
}
if (-not $ready) {
  throw "WordPress did not become ready in time."
}

& docker compose run --rm wpcli core is-installed 2>$null | Out-Null
if ($LASTEXITCODE -ne 0) {
  Write-Host "==> Installing WordPress..."
  Invoke-Wp -WpArgs @(
    "core", "install",
    "--url=$WP_URL",
    "--title=$WP_TITLE",
    "--admin_user=$WP_ADMIN_USER",
    "--admin_password=$WP_ADMIN_PASSWORD",
    "--admin_email=$WP_ADMIN_EMAIL",
    "--skip-email"
  )
} else {
  Write-Host "==> WordPress already installed."
}

Write-Host "==> Installing WooCommerce..."
Invoke-Wp -WpArgs @("plugin", "install", "woocommerce", "--activate")

Write-Host "==> Activating $PluginSlug..."
Invoke-Wp -WpArgs @("plugin", "activate", $PluginSlug)

Write-Host "==> Creating WooCommerce pages (if needed)..."
& docker compose run --rm wpcli wc tool run install_pages --user=1 2>$null | Out-Null

Write-Host "==> Enabling payment gateway (stub)..."
$settingsJson = '{"enabled":"yes","title":"Boilerplate Payment","description":"Pay using the boilerplate stub provider (for development).","sandbox":"yes","api_key":"","webhook_secret":"stub_secret","logging":"yes","simulate_failure":"no"}'
Invoke-Wp -WpArgs @("option", "update", "woocommerce_wc_gateway_boilerplate_settings", $settingsJson, "--format=json")

Write-Host "==> Creating a simple test product (if missing)..."
$existing = (& docker compose run --rm wpcli post list --post_type=product --name=stub-test-product --field=ID 2>$null | Out-String).Trim()
if (-not $existing) {
  $productId = (& docker compose run --rm wpcli wc product create --user=1 `
    --name="Stub Test Product" `
    --slug="stub-test-product" `
    --type=simple `
    --regular_price=10 `
    --porcelain | Out-String).Trim()
  Write-Host "    Product ID: $productId"
} else {
  Write-Host "    Product already exists."
}

Write-Host ""
Write-Host "Setup complete."
Write-Host "  Site:    $WP_URL"
Write-Host "  Admin:   $WP_URL/wp-admin   ($WP_ADMIN_USER / $WP_ADMIN_PASSWORD)"
Write-Host "  Gateway: WooCommerce → Settings → Payments → Payment Gateway Boilerplate"
Write-Host "  Webhook: $WP_URL/?wc-api=wc_gateway_boilerplate"
