# Checkout Blocks

## What ships in v1.0

- `includes/Gateway/BlocksSupport.php` — registers the payment method type with WooCommerce Blocks
- `assets/js/blocks.js` — `registerPaymentMethod` (no webpack build step)
- `assets/css/gateway.css` — minimal description styles
- Feature compatibility: `cart_checkout_blocks` declared in the main plugin file

## How it works

1. Blocks loads payment method integrations.
2. `BlocksSupport` exposes script handle + `get_payment_method_data()` (title, description, supports).
3. JS reads `wc.wcSettings.getSetting( 'wc_gateway_boilerplate_data' )` and registers the method.
4. On place order, Woo still calls the PHP gateway `process_payment()` — same path as classic checkout.

## Classic checkout

Classic remains supported via `AbstractGateway`. Do not remove it when enhancing Blocks.

## Customizing for a real provider

- Keep the Blocks `name` equal to the gateway id.
- Keep the settings key `{gateway_id}_data`.
- Add token / 3DS UI in `blocks.js` (and classic JS if needed).
- If you introduce a build pipeline (`@wordpress/scripts`), replace the plain `blocks.js` but keep the same registration contract.

## Verify

1. Enable the gateway in **Payments**.
2. Use a Checkout page that contains the WooCommerce Checkout **block**.
3. Method appears and completes a stub (or real) payment.
4. Re-check classic checkout still works.
