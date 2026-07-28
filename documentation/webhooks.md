# Webhooks

## Endpoint

```text
https://your-site.example/?wc-api=wc_gateway_boilerplate
```

Gateway id is `AbstractGateway::GATEWAY_ID` (`wc_gateway_boilerplate` in the boilerplate).  
Rename it when creating a real gateway.

The URL is also shown under **WooCommerce → Settings → Payments → [Gateway]**.

## Handler flow

1. Read raw body + headers  
2. `Provider::verify_webhook_signature()` — fail → **401**  
3. `Provider::parse_webhook()` — fail → **400**  
4. Resolve order by `order_id` (or extend lookup later) — missing → **404**  
5. `PaymentService::reconcile()` — duplicate `event_id` → **200** with `handled: false`  
6. Success → **200** JSON

## StubProvider signature (dev only)

- Header: `X-Stub-Signature`
- Value: `HMAC-SHA256(raw_body, webhook_secret)`
- Default secret: `stub_secret` (gateway setting **Webhook secret**)

Example payload:

```json
{
  "event_id": "evt_1",
  "type": "payment.paid",
  "provider_payment_id": "stub_pay_123",
  "status": "paid",
  "order_id": 123
}
```

Full curl examples: [`DOCKER.md`](../DOCKER.md).

## Real providers

Implement signature verification exactly as the provider documents (HMAC header name, timestamp tolerance, etc.).  
Map their event types into `WebhookEvent` with a normalized `status` for `StatusMapper`.

## Idempotency

Processed event ids are stored on the order (`OrderHelper::META_PROCESSED_EVENTS`).  
Replays must not corrupt status transitions.

## Security

- Never skip signature checks in production.
- Do not log full webhook bodies if they contain PII/secrets.
- Prefer HTTPS in production; use a tunnel (ngrok, etc.) only for local provider callbacks.
