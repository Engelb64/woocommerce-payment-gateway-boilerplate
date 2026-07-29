# Create a new payment gateway from this boilerplate

This repo is a **skeleton**: clone it, rename it, implement **your** provider.  
It is **not** a Stripe (or any other brand) product. The optional Stripe class under `Provider/Example/` is only a teaching reference.

## Mental model

```text
Keep:     Gateway → PaymentService → webhooks → HTTP client → Stub for local tests
Replace:  ProviderInterface implementation  ← your orchestrator (DEUNA, Acme, …)
Delete:   includes/Provider/Example/        ← when you no longer need the Stripe sample
```

## 1. Clone and rename

```bash
git clone https://github.com/Engelb64/woocommerce-payment-gateway-boilerplate.git my-gateway
cd my-gateway
```

Suggested renames (search/replace carefully):

| Current | Example for a real gateway |
|---|---|
| Folder / plugin file `woocommerce-payment-gateway-boilerplate` | `woocommerce-gateway-acme` |
| Text domain `wc-payment-gateway-boilerplate` | `wc-gateway-acme` |
| Namespace `WCGatewayBoilerplate` | `WCGatewayAcme` |
| Gateway id `wc_gateway_boilerplate` | `wc_gateway_acme` |
| Composer package `engelb64/woocommerce-payment-gateway-boilerplate` | `you/woocommerce-gateway-acme` |
| Constants `WC_GATEWAY_BOILERPLATE_*` | `WC_GATEWAY_ACME_*` |

Also update:

- Plugin header in the main PHP file (`Plugin Name`, `Description`, `Text Domain`, `Domain Path`)
- `composer.json` autoload namespace
- Blocks JS `name` / `getSetting( '..._data' )` key (must match gateway id + `_data`)
- Meta keys in `OrderHelper` if you want provider-specific prefixes

## 2. Implement `ProviderInterface`

Create e.g. `includes/Provider/AcmeProvider.php` extending `AbstractProvider` (or implementing the interface directly).

**Study aids (optional):**

- `StubProvider` — minimal success/fail + HMAC webhooks  
- `Provider/Example/StripeReferenceProvider` — real API, redirect, Stripe webhooks (safe to delete)

Required methods:

- `create_payment( array $order_data ): PaymentResult`
- `refund_payment( string $id, float $amount, string $currency ): PaymentResult`
- `get_payment( string $id ): PaymentResult`
- `parse_webhook( array $headers, string $raw_body ): WebhookEvent`
- `verify_webhook_signature( array $headers, string $raw_body ): bool`

`order_data` convention from the boilerplate:

- `order_id`, `amount`, `currency`
- `customer` (email/name)
- `return_url` / `cancel_url` (optional)

Return normalized statuses the mapper understands: `pending`, `created`, `authorized`, `paid`, `captured`, `failed`, `cancelled`/`canceled`, `refunded` — or extend the map via `wc_gateway_boilerplate_status_map`.

Use `ClientInterface` / `$this->http` for API calls. Do **not** call WooCommerce APIs from the provider.

If your orchestrator ships a **Web SDK** (widget) plus a backend API (typical of DEUNA-style products): keep secrets and create-session/refund/webhook in the Provider; load the SDK from gateway/Blocks assets.

## 3. Wire the provider

Prefer making **your** provider the default in `Plugin::boot_services()` (remove the Stripe reference option from settings).

Or temporarily:

```php
add_filter( 'wc_gateway_boilerplate_provider', function ( $provider, $config ) {
    return new \WCGatewayAcme\Provider\AcmeProvider(
        new \WCGatewayAcme\Http\WpHttpClient(),
        $config
    );
}, 10, 2 );
```

Prefer reading secrets from gateway settings (`api_key`, `webhook_secret`, `sandbox`) via `wc_gateway_boilerplate_provider_config`.

## 4. Settings & assets

- Adjust form fields in `AbstractGateway` (titles, help text; remove **Simulate failure** and the Stripe reference select for production).
- If the provider needs hosted fields / 3DS / redirect JS, add `assets/js/checkout.js` (classic) and extend `assets/js/blocks.js`.
- Keep PCI scope minimal: prefer provider-hosted / tokenized fields; never store PAN/CVV.

## 5. Test checklist

- [ ] `composer test` and `composer phpcs` green
- [ ] Docker setup (`DOCKER.md`) — plugin activates
- [ ] Payment **success** → expected Woo status + provider payment id meta
- [ ] Payment **failure** → failed notice / failed order handling
- [ ] Webhook valid signature → reconcile
- [ ] Webhook invalid signature → 401, order unchanged
- [ ] Duplicate `event_id` → idempotent
- [ ] Admin refund (full / partial as supported)
- [ ] Classic checkout + Checkout Blocks
- [ ] `Provider/Example/` removed (or clearly not enabled) for client builds

## 6. Ship

- Bump version in the main plugin file
- Tag a release
- Document provider-specific webhook URL and required headers for ops

## What “done” looks like

For a real gateway you should **not** rewrite PaymentService / WebhookHandler / HTTP client.  
If you must, the architecture probably needs a new extension point — prefer a filter or a small interface addition over a fork.

Stripe reference walkthrough (optional learning): [reference-stripe-adapter.md](./reference-stripe-adapter.md).
