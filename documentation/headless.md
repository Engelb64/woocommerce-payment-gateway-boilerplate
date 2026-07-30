# Headless payment API (v1.2)

Expose **create / refund / status** to a decoupled storefront (Next.js, mobile, etc.) **without** duplicating payment logic.

```text
Headless client (fetch / Apollo / …)
        │
        ▼
REST  wc-gateway-boilerplate/v1   ← this layer (thin)
        │
        ▼
PaymentService → ProviderInterface (Stub / your adapter / Example)
```

## Why REST first (not mandatory WPGraphQL)

| Choice | Reason |
|---|---|
| Primary: **REST** | No extra plugin; works with Application Passwords, cookies, or your JWT gateway |
| GraphQL | Optional later — same operations via WPGraphQL mutations wrapping `PaymentService` |

This keeps the boilerplate a skeleton: headless consumers call HTTP; you do not fork Gateway UI.

## Endpoints

Base: `/wp-json/wc-gateway-boilerplate/v1`

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `POST` | `/payments` | Logged-in order owner **or** `manage_woocommerce` / `edit_shop_orders` | Create payment |
| `GET` | `/payments/{order_id}` | Same as create | Read provider + Woo status meta |
| `POST` | `/payments/refund` | `manage_woocommerce` or `edit_shop_orders` | Refund |

### Create

```http
POST /wp-json/wc-gateway-boilerplate/v1/payments
Content-Type: application/json

{
  "order_id": 123,
  "return_url": "https://store.example/order-received/123",
  "cancel_url": "https://store.example/checkout"
}
```

Success `200` (stub):

```json
{
  "success": true,
  "provider_payment_id": "stub_pay_123",
  "provider_status": "paid",
  "woo_status": "processing",
  "redirect_url": "https://…",
  "message": "…"
}
```

Provider failure → `422` with `success: false` and `message` (no secrets / no raw provider dump).

If the active provider returns a hosted checkout URL (e.g. Stripe reference), follow `redirect_url`.

### Status

```http
GET /wp-json/wc-gateway-boilerplate/v1/payments/123
```

### Refund

```http
POST /wp-json/wc-gateway-boilerplate/v1/payments/refund
Content-Type: application/json

{
  "order_id": 123,
  "amount": 10,
  "reason": "customer request"
}
```

## Auth in Docker / local

1. Create an Application Password for `admin` (Users → Profile), or use a logged-in cookie session.
2. Example with Application Passwords:

```bash
curl -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d "{\"order_id\":123}" \
  http://localhost:9080/wp-json/wc-gateway-boilerplate/v1/payments
```

Typical headless flow:

1. Create the Woo order via Store API / your BFF.
2. `POST /payments` with that `order_id`.
3. If `redirect_url` is set, send the shopper there; else treat as completed when `success` + `woo_status`.
4. Webhooks still update the order asynchronously (unchanged).

## GraphQL (optional follow-up)

Not bundled. If the project already runs [WPGraphQL](https://www.wpgraphql.com/):

- Register mutations `createBoilerplatePayment` / `refundBoilerplatePayment` that call `Plugin::instance()->get_payment_service()` the same way `RestController` does.
- Reuse `PaymentResponseMapper` so REST and GraphQL stay aligned.

## What not to do

- Do not call the provider HTTP API from Next.js with secret keys.
- Do not put `api_key` / webhook secrets in JSON responses.
- Do not bypass `PaymentService` in a custom route.

## Manual checklist

- [ ] `POST /payments` with stub → order Processing + `stub_pay_*`
- [ ] `GET /payments/{id}` returns meta
- [ ] `POST /payments/refund` as shop manager
- [ ] Unauthenticated → 401; wrong user → 403
- [ ] `composer test` green
