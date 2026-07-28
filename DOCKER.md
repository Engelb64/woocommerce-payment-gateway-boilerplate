# Entorno local con Docker

Este documento describe cómo probar el plugin en WordPress usando Docker desde la **raíz del repositorio**.

## Cómo funciona

```text
Tu repo (esta carpeta)
        │
        │  docker compose up
        ▼
┌───────────────────────────────────────────────┐
│  Contenedor WordPress                         │
│  /var/www/html/wp-content/plugins/            │
│    woocommerce-payment-gateway-boilerplate/   │  ← bind mount de ./
└───────────────────────────────────────────────┘
```

- Editas archivos en tu máquina.
- WordPress los lee en la ruta de plugins sin copiar manualmente.
- `vendor/`, `includes/`, `assets/`, etc. vivirán aquí cuando empiece el código (v0.1+).

## Servicios

| Servicio | Imagen | Puerto local | Uso |
|---|---|---|---|
| `wordpress` | `wordpress:6.7-php8.2` | `8080` | WordPress + montaje del plugin |
| `db` | `mysql:8.0` | interno | Base de datos |
| `adminer` | `adminer` | `8081` | UI para MySQL (opcional) |

## Requisitos

- Docker Desktop en Windows, o Docker Engine + Compose v2.
- Puertos `8080` y `8081` libres (o cambiar en `.env`).

## Setup

### 1. Variables de entorno

```bash
cp .env.example .env
```

Valores por defecto en `.env.example` son para desarrollo local. No uses esas credenciales en producción.

### 2. Levantar contenedores

```bash
docker compose up -d
```

Ver logs:

```bash
docker compose logs -f wordpress
```

### 3. Instalar WordPress (solo la primera vez)

1. Abre [http://localhost:8080](http://localhost:8080).
2. Idioma, título del sitio, usuario admin y contraseña.
3. Entra al escritorio.

### 4. Instalar WooCommerce

1. **Plugins → Añadir nuevo** → buscar "WooCommerce".
2. Instalar y activar.
3. Completar el asistente de Woo (país, moneda, etc.) o saltarlo.

### 5. Activar este plugin

1. **Plugins** → activar **WooCommerce Payment Gateway Boilerplate**.
2. **WooCommerce → Ajustes → Pagos** → activar **Payment Gateway Boilerplate**.
3. Guardar. Dejar **Simulate failure** en no para un checkout de prueba OK.
4. Crear un producto simple, añadir al carrito y pagar en checkout classic **o** Checkout Blocks.

Si el método no aparece: confirma que WooCommerce está activo y recarga la página de Pagos. En Blocks, asegúrate de usar la página Checkout con el bloque de WooCommerce.

## URLs útiles

| Recurso | URL |
|---|---|
| WordPress | http://localhost:8080 |
| Admin | http://localhost:8080/wp-admin |
| Adminer (DB) | http://localhost:8081 |
| Checkout (tras Woo) | http://localhost:8080/checkout |

### Adminer — credenciales por defecto

| Campo | Valor |
|---|---|
| Sistema | MySQL |
| Servidor | `db` |
| Usuario | `wordpress` |
| Contraseña | `wordpress` |
| Base de datos | `wordpress` |

## Comandos habituales

```bash
# Levantar
docker compose up -d

# Parar (conserva datos)
docker compose down

# Parar y borrar volúmenes (reset total WP + DB)
docker compose down -v

# Reiniciar solo WordPress
docker compose restart wordpress

# Ver estado
docker compose ps
```

## Composer dentro del contenedor (v0.1+)

Cuando exista `composer.json`:

```bash
docker compose exec wordpress bash -c "cd wp-content/plugins/woocommerce-payment-gateway-boilerplate && composer install"
```

O en tu máquina, si tienes PHP/Composer local:

```bash
composer install
```

El bind mount hace que `vendor/` quede disponible para WordPress.

## Webhooks (v0.5)

Endpoint del stub:

```text
http://localhost:8080/?wc-api=wc_gateway_boilerplate
```

También se muestra en **WooCommerce → Ajustes → Pagos → Payment Gateway Boilerplate**.

### Firma (StubProvider)

Header: `X-Stub-Signature`  
Valor: `HMAC-SHA256(raw_body, webhook_secret)`  
Secret por defecto: `stub_secret` (el de la setting **Webhook secret**).

### Payload de ejemplo

```json
{
  "event_id": "evt_test_1",
  "type": "payment.paid",
  "provider_payment_id": "stub_pay_123",
  "status": "paid",
  "order_id": 123
}
```

Sustituye `123` por un **order id real** de WooCommerce.

### curl — firma válida (PowerShell)

```powershell
$body = '{"event_id":"evt_test_1","type":"payment.paid","provider_payment_id":"stub_pay_123","status":"paid","order_id":123}'
$secret = "stub_secret"
$hmac = [System.BitConverter]::ToString(
  [System.Security.Cryptography.HMACSHA256]::new(
    [Text.Encoding]::UTF8.GetBytes($secret)
  ).ComputeHash([Text.Encoding]::UTF8.GetBytes($body))
).Replace("-","").ToLower()

curl.exe -s -X POST "http://localhost:8080/?wc-api=wc_gateway_boilerplate" `
  -H "Content-Type: application/json" `
  -H "X-Stub-Signature: $hmac" `
  -d $body
```

Respuesta esperada: HTTP 200 con `"ok":true` y `"woo_status":"processing"`.

### curl — firma inválida

```powershell
curl.exe -s -o - -w "\nHTTP %{http_code}\n" -X POST "http://localhost:8080/?wc-api=wc_gateway_boilerplate" `
  -H "Content-Type: application/json" `
  -H "X-Stub-Signature: invalid" `
  -d "{\"event_id\":\"evt_bad\",\"type\":\"payment.paid\",\"provider_payment_id\":\"stub_pay_1\",\"status\":\"paid\",\"order_id\":1}"
```

Respuesta esperada: HTTP **401** (`invalid_signature`) — la orden no debe cambiar.

### Idempotencia

Reenvía el mismo `event_id` firmado: HTTP 200 con `"handled":false` y `"reason":"duplicate_event"`.

### Túnel externo

Para que un provider real llame a tu máquina: ngrok / Cloudflare Tunnel apuntando a `localhost:8080`.

## Troubleshooting

### Puerto 8080 ocupado

Edita `.env`:

```env
WP_PORT=9080
```

Luego `docker compose up -d` y usa http://localhost:9080.

### El plugin no aparece en Plugins

- Confirma que `docker-compose.yml` está en la raíz del repo.
- Confirma que ejecutaste `docker compose` desde esa raíz.
- Verifica el montaje:

```bash
docker compose exec wordpress ls -la wp-content/plugins/woocommerce-payment-gateway-boilerplate
```

Deberías ver `README.md`, `docker-compose.yml`, etc.

### Cambios en PHP no se reflejan

- WordPress con bind mount no requiere rebuild.
- Si usas opcache agresivo en otro setup, reinicia: `docker compose restart wordpress`.

### Reset limpio

```bash
docker compose down -v
docker compose up -d
```

Vuelve a instalar WordPress y WooCommerce desde el navegador.

## Qué no incluye este Docker

- WooCommerce preinstalado (se instala desde el admin; automatización opcional en fases posteriores).
- TLS / HTTPS local (no necesario para desarrollo básico).
- Producción: este compose es solo para desarrollo local.
