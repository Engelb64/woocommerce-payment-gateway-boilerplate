# Architecture

## Goal

Reuse the same WooCommerce payment infrastructure for every orchestrator / gateway.  
**Swap the Provider**; keep Gateway, PaymentService, HTTP client, and webhooks.

## Layer diagram

```text
Plugin bootstrap
      │
      ▼
Gateway (WC_Payment_Gateway) ──────── BlocksSupport
      │
      ▼
PaymentService (create / refund / reconcile)
      │
      ▼
ProviderInterface  ◄── main extension point (implement YOUR provider; Stub by default)
      │                    optional: Provider/Example/* reference adapters (deletable)
      ▼
Http ClientInterface (WpHttpClient)
      ▲
WebhookHandler ──► PaymentService
```

## Responsibilities

| Layer | Class(es) | Responsibility |
|---|---|---|
| Bootstrap | `Plugin` | Textdomain, DI-ish wiring, register gateway + webhooks + blocks |
| Gateway | `Gateway\AbstractGateway` | Settings UI, `process_payment`, `process_refund` |
| Blocks | `Gateway\BlocksSupport` | Register method with Checkout Blocks |
| Service | `PaymentService`, `StatusMapper` | Orchestration + provider status → Woo status |
| Provider | `ProviderInterface`, `StubProvider`, your adapter | Talk to the payment API |
| Example | `Provider\Example\*` | Optional reference implementations (not product) |
| HTTP | `ClientInterface`, `WpHttpClient` | Transport only |
| Webhook | `WebhookHandler` | `/?wc-api={gateway_id}` |
| Support | `Logger`, `OrderHelper` | Logs (scrubbed) + order meta / idempotency |
| DTOs | `PaymentResult`, `WebhookEvent` | Woo-free results |

## Filters (extension without forking core)

| Filter | Purpose |
|---|---|
| `wc_gateway_boilerplate_provider` | Replace `StubProvider` with your adapter |
| `wc_gateway_boilerplate_provider_config` | Pass API keys / secrets / sandbox flags |
| `wc_gateway_boilerplate_status_map` | Override provider → Woo status map |
| `wc_gateway_boilerplate_init` | Action after boot |

## Payment flows

### Sync checkout

```text
Checkout → Gateway::process_payment()
        → PaymentService::create()
        → Provider::create_payment()
        → map status + save meta → redirect success/fail
```

### Async webhook

```text
Provider → POST /?wc-api=wc_gateway_boilerplate
        → verify signature
        → parse event
        → PaymentService::reconcile()
        → update order (idempotent by event_id)
```

### Refund

```text
Admin refund → Gateway::process_refund()
            → PaymentService::refund()
            → Provider::refund_payment()
```

## What you should not change for a normal integration

- `PaymentService` internals
- `WebhookHandler` flow (unless endpoint/auth model differs a lot)
- `WpHttpClient` (unless you need another transport — implement `ClientInterface`)

Change **Provider + settings/assets** first.
