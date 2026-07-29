# Reference providers (`Example/`)

These classes are **teaching adapters**, not the purpose of this plugin.

| File | Role |
|---|---|
| `StripeReferenceProvider.php` | Complete real-API example (Stripe Checkout Session) |

**Default runtime provider is always `StubProvider`.**  
You may enable the Stripe reference from gateway settings only to study / sandbox-test the pattern.

### When building your orchestrator

1. Clone this repo and rename (see `documentation/create-new-gateway.md`).
2. Create `includes/Provider/YourProvider.php` implementing `ProviderInterface`.
3. Use this Stripe reference as a **read-only guide** (HTTP, webhooks, refunds, redirects).
4. Wire your provider in `Plugin::boot_services()` (or the `wc_gateway_boilerplate_provider` filter).
5. **Delete** this `Example/` folder (and the settings option for the reference) before shipping to a client.

This boilerplate is a skeleton for **any** payment orchestrator — not a Stripe product.
