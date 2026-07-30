# WooCommerce Payment Gateway Boilerplate

**Skeleton** to build WooCommerce payment gateways / **orchestrators** — not a branded Stripe (or other) product.

The **repository root is the plugin**. Docker bind-mounts this folder into `wp-content/plugins/woocommerce-payment-gateway-boilerplate`.

## What you get

- WooCommerce gateway (classic + Blocks)
- HPOS + cart/checkout blocks compatibility
- Decoupled `ProviderInterface` with **`StubProvider` by default**
- Optional **reference** adapter under `includes/Provider/Example/` (Stripe Checkout — deletable)
- **Headless REST** API (`wc-gateway-boilerplate/v1`) on top of `PaymentService`
- `PaymentService` + status mapping
- Signed webhooks (`/?wc-api=wc_gateway_boilerplate`)
- Docker local environment + WP-CLI setup scripts
- PHPUnit + PHPCS + GitHub Actions CI
- i18n-ready (text domain + `.pot`)

**Main path:** clone → rename → implement your Provider.  
Guide: [documentation/create-new-gateway.md](./documentation/create-new-gateway.md).  
Headless: [documentation/headless.md](./documentation/headless.md).

## Documentation

| Doc | Content |
|---|---|
| [README.md](./README.md) | This file — quick start |
| [DOCKER.md](./DOCKER.md) | Local Docker, E2E checklist, webhooks curl |
| [documentation/](./documentation/README.md) | Architecture, new gateway, headless, optional Stripe reference, webhooks, Blocks, i18n |

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
2. Implement `ProviderInterface` (start from `StubProvider`; optionally study `Provider/Example/StripeReferenceProvider`).
3. Wire **your** provider as the default (filter or `Plugin::boot_services`).
4. Adjust settings / JS if the provider needs hosted fields, SDK widget, or redirect.
5. Delete `includes/Provider/Example/` before a client delivery if you do not need it.
6. Test: success, failure, webhook sign/fail/duplicate, refund, classic + Blocks.
7. Run `composer test` and `composer phpcs`.

Full guide: [documentation/create-new-gateway.md](./documentation/create-new-gateway.md).

## Gateway settings

| Setting | Use |
|---|---|
| Enable/Disable | Show method at checkout |
| Title / Description | Customer-facing copy |
| Active provider | **Stub (default)** or optional Stripe **reference** example |
| Sandbox | Prefer test keys for whatever provider you wire |
| API key / Secret key | For real/reference providers (unused by stub) |
| Webhook secret | Stub HMAC or your provider’s signing secret |
| Logging | WooCommerce → Status → Logs |
| Simulate failure | Stub-only forced failure |

Optional Stripe sandbox walkthrough: [documentation/reference-stripe-adapter.md](./documentation/reference-stripe-adapter.md).

Successful **stub** payments set the order to **Processing** (`stub_pay_*`).  
The Stripe **reference** sets **on-hold** until webhook → **Processing**.

Webhook URL:

```text
http://localhost:8080/?wc-api=wc_gateway_boilerplate
```

(Use your Docker port if different, e.g. `9080`.)

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
├── includes/               # Plugin, Gateway, Provider (+ Example/), Service, …
├── documentation/          # Architecture, create-new-gateway, optional Stripe reference
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
| **v1.0** | Skeleton + Stub — ready to build real providers |
| **v1.1** | Optional Stripe **reference** adapter under `Provider/Example/` |
| **v1.2** (this branch) | Headless REST payment API (`wc-gateway-boilerplate/v1`) |

## Out of scope

Headless **GraphQL** (WPGraphQL soft-dep later), Playwright E2E, subscriptions, advanced multi-currency, WPML-specific integration — see fase 2 plan locally. This repo is not a hosted Stripe plugin.

## License

[MIT](./LICENSE)
