# WooCommerce Payment Gateway Boilerplate

**v1.0** — Modular boilerplate to build WooCommerce payment gateways / orchestrators.

The **repository root is the plugin**. Docker bind-mounts this folder into `wp-content/plugins/woocommerce-payment-gateway-boilerplate`.

## What you get

- WooCommerce gateway (classic + Blocks)
- HPOS + cart/checkout blocks compatibility
- Decoupled `ProviderInterface` (StubProvider included)
- `PaymentService` + status mapping
- Signed webhooks (`/?wc-api=wc_gateway_boilerplate`)
- Docker local environment + WP-CLI setup scripts
- PHPUnit + PHPCS + GitHub Actions CI
- i18n-ready (text domain + `.pot`)

For a **real** gateway you mainly implement a Provider (+ settings/assets). See [documentation/create-new-gateway.md](./documentation/create-new-gateway.md).

## Documentation

| Doc | Content |
|---|---|
| [README.md](./README.md) | This file — quick start |
| [DOCKER.md](./DOCKER.md) | Local Docker, E2E checklist, webhooks curl |
| [documentation/](./documentation/README.md) | Architecture, new gateway, webhooks, Blocks, i18n |

## Requirements

- WordPress 6.0+
- WooCommerce (active)
- PHP 7.4+
- Docker (recommended for local)
- Composer (optional; fallback autoload included; required for tests/CI)

## Quick start (Docker)

```bash
git clone https://github.com/Engelb64/woocommerce-payment-gateway-boilerplate.git
cd woocommerce-payment-gateway-boilerplate

cp .env.example .env
docker compose down -v
docker compose up -d
```

**Auto setup** (WordPress + WooCommerce + plugin + sample product):

```powershell
# Windows
powershell -ExecutionPolicy Bypass -File bin\setup-wp.ps1
```

```bash
# Git Bash / WSL / Linux / macOS
sh bin/setup-wp.sh
```

Default admin: `admin` / `admin` → http://localhost:8080/wp-admin

Pay at checkout with **Boilerplate Payment** (leave **Simulate failure** off).

## Create a real gateway (summary)

1. Clone / fork and rename slug, namespace, text domain, gateway id.
2. Implement `ProviderInterface` (see `StubProvider` as reference).
3. Wire it with `wc_gateway_boilerplate_provider` (and config filter).
4. Adjust settings / JS if the provider needs hosted fields or redirect.
5. Test: success, failure, webhook sign/fail/duplicate, refund, classic + Blocks.
6. Run `composer test` and `composer phpcs`.

Full guide: [documentation/create-new-gateway.md](./documentation/create-new-gateway.md).

## Gateway settings

| Setting | Use |
|---|---|
| Enable/Disable | Show method at checkout |
| Title / Description | Customer-facing copy |
| Sandbox | Test mode flag for real providers |
| API key | Provider credentials (unused by stub) |
| Webhook secret | Signature verification (`stub_secret` by default) |
| Logging | WooCommerce → Status → Logs |
| Simulate failure | Stub-only forced failure |

Successful stub payments set the order to **Processing** and store `_wc_gateway_boilerplate_payment_id` (e.g. `stub_pay_123`).

Webhook URL:

```text
http://localhost:8080/?wc-api=wc_gateway_boilerplate
```

## Quality

```bash
composer install
composer test
composer phpcs
```

CI (`.github/workflows/ci.yml`) runs PHPUnit + PHPCS on PHP 7.4 / 8.1 / 8.2 for PRs to `main`.

Smoke (no full WP required for stub/service):

```bash
php bin/smoke-stub.php
php bin/smoke-service.php
```

## Repository layout

```text
woocommerce-payment-gateway-boilerplate/
├── docker-compose.yml
├── .env.example
├── README.md
├── DOCKER.md
├── documentation/
├── composer.json
├── bin/                    # smoke + setup-wp
├── languages/              # .pot template
├── includes/               # Plugin, Gateway, Provider, Service, …
├── assets/js|css
├── tests/Unit
├── phpunit.xml.dist
├── phpcs.xml.dist
├── .github/workflows/ci.yml
└── woocommerce-payment-gateway-boilerplate.php
```

## Version status

| Version | Status |
|---|---|
| v0.1–v0.9 | Incremental roadmap (scaffold → CI) |
| **v1.0** | **MVP boilerplate — ready for real providers** |

## Out of scope (v1.0)

Headless/GraphQL, Playwright E2E, subscriptions, advanced multi-currency, WPML-specific integration. Planned as post-1.0 when needed.

## License

[MIT](./LICENSE)
