# Entorno local con Docker

Guía validada (v0.8) para probar el plugin de punta a punta desde la **raíz del repositorio**.

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

Editas en tu máquina; WordPress ve los cambios al instante.

## Servicios

| Servicio | Imagen | Puerto | Uso |
|---|---|---|---|
| `wordpress` | `wordpress:6.7-php8.2` | `8080` | WordPress + plugin montado |
| `db` | `mysql:8.0` | interno | Base de datos |
| `adminer` | `adminer` | `8081` | UI MySQL |
| `wpcli` | `wordpress:cli-php8.2` | — | WP-CLI one-off (`bin/setup-wp.*`) |

## Instalación limpia (recomendada)

### 1. Reset + levantar

```bash
# Desde la raíz del repo
cp .env.example .env   # solo la primera vez

docker compose down -v
docker compose up -d
docker compose ps
```

Espera a que `wordpress` esté healthy/up (unos segundos). Abre http://localhost:8080 si quieres verificar a mano.

### 2. Setup automático (WP + Woo + plugin + producto)

**Windows (PowerShell):**

```powershell
powershell -ExecutionPolicy Bypass -File bin\setup-wp.ps1
```

**Git Bash / WSL / macOS / Linux:**

```bash
sh bin/setup-wp.sh
```

Credenciales por defecto del script:

| Campo | Valor |
|---|---|
| URL | http://localhost:8080 |
| Usuario | `admin` |
| Contraseña | `admin` |

Override opcional: `WP_URL`, `WP_ADMIN_USER`, `WP_ADMIN_PASSWORD`, `WP_ADMIN_EMAIL`, `WP_TITLE`.

### 3. Setup manual (alternativa)

1. http://localhost:8080 → instalar WordPress.
2. Plugins → instalar/activar **WooCommerce**.
3. Activar **WooCommerce Payment Gateway Boilerplate**.
4. WooCommerce → Ajustes → Pagos → activar **Payment Gateway Boilerplate**.
5. **Simulate failure** = no.
6. Crear un producto y pagar en checkout classic o Blocks.

## Checklist de validación end-to-end (v0.8)

Marca en una instalación limpia:

- [ ] `docker compose up -d` levanta WP en http://localhost:8080
- [ ] El plugin aparece montado:

```bash
docker compose exec wordpress ls wp-content/plugins/woocommerce-payment-gateway-boilerplate
```

- [ ] Setup (`bin/setup-wp.ps1` o `.sh`) deja Woo + plugin activos
- [ ] Gateway visible y enabled en Pagos
- [ ] Checkout stub → pedido en **Processing** + meta `stub_pay_*`
- [ ] Refund desde el pedido en admin OK
- [ ] Webhook firmado OK / firma inválida 401 (sección Webhooks)
- [ ] Smoke CLI:

```bash
docker compose run --rm --no-deps --entrypoint php wordpress \
  /var/www/html/wp-content/plugins/woocommerce-payment-gateway-boilerplate/bin/smoke-service.php
```

- [ ] PHPUnit (imagen Composer; la imagen WordPress no trae Composer):

```bash
docker run --rm -v "${PWD}:/app" -w /app composer:2 install
docker run --rm -v "${PWD}:/app" -w /app composer:2 php vendor/bin/phpunit --configuration phpunit.xml.dist
```

## URLs útiles

| Recurso | URL |
|---|---|
| WordPress | http://localhost:8080 |
| Admin | http://localhost:8080/wp-admin |
| Adminer | http://localhost:8081 |
| Checkout | http://localhost:8080/checkout |
| Webhook | http://localhost:8080/?wc-api=wc_gateway_boilerplate |

### Adminer

| Campo | Valor |
|---|---|
| Sistema | MySQL |
| Servidor | `db` |
| Usuario | `wordpress` |
| Contraseña | `wordpress` |
| Base | `wordpress` |

## Comandos habituales

```bash
docker compose up -d
docker compose down          # conserva datos
docker compose down -v       # reset total WP + DB
docker compose restart wordpress
docker compose ps
docker compose logs -f wordpress

# WP-CLI ad-hoc
docker compose run --rm wpcli plugin list
docker compose run --rm wpcli option get siteurl
```

## Composer

La imagen `wordpress` **no** incluye Composer. Usa:

```bash
docker run --rm -v "${PWD}:/app" -w /app composer:2 install
docker run --rm -v "${PWD}:/app" -w /app composer:2 test
```

O Composer local si lo tienes en el PATH. El bind mount hace que `vendor/` quede visible para WordPress (útil para autoload; el plugin también tiene fallback sin vendor).

## Webhooks (v0.5+)

```text
http://localhost:8080/?wc-api=wc_gateway_boilerplate
```

Firma stub: header `X-Stub-Signature` = HMAC-SHA256(body, `webhook_secret`)  
Default secret: `stub_secret`

### Payload

```json
{
  "event_id": "evt_test_1",
  "type": "payment.paid",
  "provider_payment_id": "stub_pay_123",
  "status": "paid",
  "order_id": 123
}
```

Usa un `order_id` real.

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

Esperado: HTTP 200, `"ok":true`.

### curl — firma inválida

```powershell
curl.exe -s -o - -w "\nHTTP %{http_code}\n" -X POST "http://localhost:8080/?wc-api=wc_gateway_boilerplate" `
  -H "Content-Type: application/json" `
  -H "X-Stub-Signature: invalid" `
  -d "{\"event_id\":\"evt_bad\",\"type\":\"payment.paid\",\"provider_payment_id\":\"stub_pay_1\",\"status\":\"paid\",\"order_id\":1}"
```

Esperado: HTTP **401**.

### Idempotencia

Reenviar el mismo `event_id` → `"handled":false`, `"reason":"duplicate_event"`.

## Troubleshooting

### Puerto 8080 ocupado

En `.env`: `WP_PORT=9080` → http://localhost:9080

### El plugin no aparece

```bash
docker compose exec wordpress ls -la wp-content/plugins/woocommerce-payment-gateway-boilerplate
```

Debes ver `README.md`, `includes/`, etc. Ejecuta compose desde la raíz del repo.

### `bin/setup-wp` falla con permisos

El servicio `wpcli` corre como UID 33. Si hay errores de escritura en `wp_data`, reinicia limpio: `docker compose down -v && docker compose up -d`.

### Composer / PHPUnit

No uses `docker compose exec wordpress composer` (no está instalado). Usa la imagen `composer:2` como arriba.

## Qué no incluye este Docker

- HTTPS local
- Producción
- WooCommerce preinstalado en la imagen (se instala con el script o a mano)
