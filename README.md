# WooCommerce Payment Gateway Boilerplate

Boilerplate modular para crear plugins de pasarelas de pago y orquestadores en WooCommerce.

La **raíz de este repositorio es el plugin**. Docker monta este directorio en `wp-content/plugins/woocommerce-payment-gateway-boilerplate`, así que los cambios en código se reflejan al instante en WordPress.

## Documentación

| Archivo | Contenido |
|---|---|
| [README.md](./README.md) | Este archivo — inicio rápido y estado del proyecto |
| [DOCKER.md](./DOCKER.md) | Entorno local con Docker: setup, URLs, troubleshooting |

Más adelante (v1.0+), la documentación detallada vivirá en `documentation/` (arquitectura, nuevo provider, webhooks, blocks, etc.). El README seguirá siendo el punto de entrada.

## Requisitos

- WordPress 6.0+
- WooCommerce (activo)
- PHP 7.4+
- Docker Desktop (o Docker + Docker Compose v2) — para desarrollo local
- Git
- Composer (opcional; hay autoload de respaldo si no corres `composer install`)

## Inicio rápido

```bash
git clone https://github.com/Engelb64/woocommerce-payment-gateway-boilerplate.git
cd woocommerce-payment-gateway-boilerplate

cp .env.example .env
docker compose down -v
docker compose up -d
```

**Setup automático** (WP + WooCommerce + plugin + producto de prueba):

```powershell
# Windows
powershell -ExecutionPolicy Bypass -File bin\setup-wp.ps1
```

```bash
# Git Bash / WSL / Linux / macOS
sh bin/setup-wp.sh
```

Admin por defecto: `admin` / `admin` en http://localhost:8080/wp-admin

Luego paga en checkout con **Boilerplate Payment** (Simulate failure = no).

Guía completa y checklist E2E: [DOCKER.md](./DOCKER.md).

### Configuración del gateway (v0.4)

| Setting | Uso |
|---|---|
| Enable/Disable | Activa el método en checkout |
| Title / Description | Textos visibles al cliente |
| Sandbox | Modo test (flag para providers reales) |
| API key | Reservado para providers reales (Stub no lo usa) |
| Webhook secret | Firma de webhooks (default `stub_secret`) |
| Logging | Logs en WooCommerce → Estado → Registros |
| Simulate failure | Fuerza fallo del StubProvider (solo pruebas) |

Tras un pago exitoso con el stub, el pedido debería quedar en **Processing** y guardar el meta `_wc_gateway_boilerplate_payment_id` (ej. `stub_pay_123`).

Los reembolsos desde el pedido en admin pasan por `PaymentService::refund()` → StubProvider.

### Webhooks (v0.5)

URL local:

```text
http://localhost:8080/?wc-api=wc_gateway_boilerplate
```

Firma stub: header `X-Stub-Signature` = HMAC-SHA256 del body con el **Webhook secret** (default `stub_secret`).

Ver ejemplos `curl` en [DOCKER.md](./DOCKER.md).

## Estructura del repositorio

```text
woocommerce-payment-gateway-boilerplate/     ← raíz = plugin + docker
├── docker-compose.yml
├── .env.example
├── README.md
├── DOCKER.md
├── composer.json
├── bin/smoke-stub.php
├── bin/smoke-service.php
├── bin/setup-wp.sh
├── bin/setup-wp.ps1
├── languages/
├── includes/
│   ├── Plugin.php
│   ├── Gateway/AbstractGateway.php
│   ├── Gateway/BlocksSupport.php
│   ├── Webhook/WebhookHandler.php
│   ├── Dto/
│   ├── Http/
│   ├── Provider/
│   ├── Service/
│   └── Support/
├── assets/
│   ├── js/blocks.js
│   └── css/gateway.css
├── tests/
│   ├── bootstrap.php
│   └── Unit/
├── phpunit.xml.dist
├── phpcs.xml.dist
├── .github/workflows/ci.yml
├── documentation/                           (v1.0+)
└── woocommerce-payment-gateway-boilerplate.php
```

No hay subcarpeta `plugins/` dentro del repo. El bind mount de Docker coloca este directorio en la ruta de plugins de WordPress.

### Checkout Blocks (v0.6)

El método se registra con WooCommerce Blocks (`BlocksSupport` + `assets/js/blocks.js`).

1. Activa el gateway en **WooCommerce → Ajustes → Pagos**.
2. Usa una página de Checkout con el bloque de WooCommerce (el default moderno).
3. El método **Boilerplate Payment** debe aparecer y completar el pedido vía StubProvider (mismo `process_payment` del gateway).

El checkout classic de v0.4 sigue funcionando.

### Smoke tests / PHPUnit / PHPCS (v0.7–v0.9)

```bash
composer install
composer test
composer phpcs
```

CI en GitHub Actions (`.github/workflows/ci.yml`) corre lo mismo en PHP 7.4 / 8.1 / 8.2 en cada PR a `main`.

También:

```bash
php bin/smoke-stub.php
php bin/smoke-service.php
```

Los unit tests no requieren WordPress ni Docker (usan Brain Monkey para el HTTP client).

## Estado del proyecto

| Versión | Estado |
|---|---|
| v0.0 | README + Docker scaffold |
| v0.1 | Scaffold del plugin (bootstrap, HPOS, i18n) |
| v0.2 | Contratos HTTP + Provider + StubProvider |
| v0.3 | PaymentService + StatusMapper + Logger |
| v0.4 | Gateway WooCommerce classic (stub checkout) |
| v0.5 | Webhooks firmados (`/?wc-api=wc_gateway_boilerplate`) |
| v0.6 | Checkout Blocks |
| v0.7 | Tests unitarios (PHPUnit) |
| v0.8 | Validación Docker + setup WP-CLI |
| v0.9 | CI GitHub Actions (PHPUnit + PHPCS) |
| v1.0 | Pendiente |

## Licencia

[MIT](./LICENSE)
