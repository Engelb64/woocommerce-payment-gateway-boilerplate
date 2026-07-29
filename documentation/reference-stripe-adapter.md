# Reference adapter: Stripe (example only)

> **This is not “the” payment product of this repo.**  
> The boilerplate is a **skeleton** so you can build **your** orchestrator (DEUNA, Acme, …).  
> `StripeReferenceProvider` is a **complete, deletable example** of `ProviderInterface` against a real API.

Default runtime provider remains **`StubProvider`**.

Code location:

```text
includes/Provider/Example/StripeReferenceProvider.php
includes/Provider/Example/README.md
```

## Why it exists

Reading `StubProvider` shows the contract.  
Reading this Stripe reference shows:

- HTTP form calls with `ClientInterface`
- Redirect checkout (`PaymentResult::$redirect_url`)
- Pending → paid via signed webhooks
- Refunds against a provider payment id

Copy the **patterns**, then implement `YourProvider` and **delete** `includes/Provider/Example/` before shipping a client plugin.

## Optional: try the reference in Docker

WooCommerce → **Settings → Payments → Payment Gateway Boilerplate**:

| Setting | Value |
|---|---|
| Active provider | **Reference only: Stripe Checkout (example)** |
| API key | `sk_test_…` |
| Webhook secret | `whsec_…` (Stripe CLI or Dashboard) |

Local forward (Stripe cannot hit localhost alone):

```bash
stripe listen --forward-to http://localhost:9080/?wc-api=wc_gateway_boilerplate
```

Flow:

```text
Pay in Woo
  → Stub is NOT used
  → StripeReferenceProvider::create_payment
  → Checkout Session URL
  → customer pays (4242…)
  → webhook checkout.session.completed → order Processing
```

Switch **Active provider** back to **Stub** anytime. No code change required.

## Building YOUR orchestrator instead

Follow [create-new-gateway.md](./create-new-gateway.md):

1. Rename the plugin.
2. Add `includes/Provider/DeunaProvider.php` (or Acme, …).
3. Wire it as the only default in `Plugin` (remove the Stripe reference option).
4. Delete `Provider/Example/`.

Filter while developing:

```php
add_filter( 'wc_gateway_boilerplate_provider', function ( $provider, $config ) {
    return new \WCGatewayAcme\Provider\AcmeProvider(
        new \WCGatewayAcme\Http\WpHttpClient(),
        $config
    );
}, 10, 2 );
```

## Manual checklist (reference only)

- [ ] Stub still default / works with Active provider = Stub
- [ ] Stripe reference opens Checkout with test keys
- [ ] Webhook signed → Processing; bad signature → 401
- [ ] Admin refund works after paid
- [ ] You understand what to delete before a client delivery
