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

# Opcional: dependencias de desarrollo / autoload Composer
composer install

# Opcional: copiar variables de entorno
cp .env.example .env

docker compose up -d
```

Abrir [http://localhost:8080](http://localhost:8080).

Primera vez en WordPress:

1. Completar instalación (idioma, admin, etc.).
2. Instalar y activar **WooCommerce**.
3. Activar **WooCommerce Payment Gateway Boilerplate** en Plugins.
4. Ir a **WooCommerce → Ajustes → Pagos**.
5. Activar **Payment Gateway Boilerplate** / **Boilerplate Payment**.
6. Dejar **Simulate failure** desmarcado para un pago de prueba exitoso.
7. Crear un producto, ir al checkout (classic) y pagar con el método.

Detalle del entorno: [DOCKER.md](./DOCKER.md).

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
├── documentation/                           (v1.0+)
├── tests/                                   (v0.7+)
└── woocommerce-payment-gateway-boilerplate.php
```

No hay subcarpeta `plugins/` dentro del repo. El bind mount de Docker coloca este directorio en la ruta de plugins de WordPress.

### Checkout Blocks (v0.6)

El método se registra con WooCommerce Blocks (`BlocksSupport` + `assets/js/blocks.js`).

1. Activa el gateway en **WooCommerce → Ajustes → Pagos**.
2. Usa una página de Checkout con el bloque de WooCommerce (el default moderno).
3. El método **Boilerplate Payment** debe aparecer y completar el pedido vía StubProvider (mismo `process_payment` del gateway).

El checkout classic de v0.4 sigue funcionando.

### Smoke tests

```bash
php bin/smoke-stub.php
php bin/smoke-service.php
```

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
| v0.7+ | Pendiente |

## Licencia

[MIT](./LICENSE)
