<?php
/**
 * Provider contract.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Provider;

use WCGatewayBoilerplate\Dto\PaymentResult;
use WCGatewayBoilerplate\Dto\WebhookEvent;

defined( 'ABSPATH' ) || exit;

/**
 * Contract every payment orchestrator / gateway adapter must implement.
 *
 * This is the main extension point when creating a real provider.
 */
interface ProviderInterface {

	/**
	 * Create a payment for an order payload.
	 *
	 * Expected keys in $order_data (convention for the boilerplate):
	 * - order_id (int|string)
	 * - amount (float|string)
	 * - currency (string)
	 * - customer (array, optional)
	 * - return_url (string, optional)
	 * - force_fail (bool, optional — used by StubProvider for tests)
	 *
	 * @param array<string, mixed> $order_data Normalized order data (not a WC_Order).
	 * @return PaymentResult
	 */
	public function create_payment( array $order_data ): PaymentResult;

	/**
	 * Refund a payment (full or partial).
	 *
	 * @param string $provider_payment_id Provider payment id.
	 * @param float  $amount              Refund amount.
	 * @param string $currency            Currency code.
	 * @return PaymentResult
	 */
	public function refund_payment( string $provider_payment_id, float $amount, string $currency ): PaymentResult;

	/**
	 * Fetch current payment status from the provider.
	 *
	 * @param string $provider_payment_id Provider payment id.
	 * @return PaymentResult
	 */
	public function get_payment( string $provider_payment_id ): PaymentResult;

	/**
	 * Parse a raw webhook into a normalized event.
	 *
	 * @param array<string, string> $headers HTTP headers.
	 * @param string                $raw_body Raw body.
	 * @return WebhookEvent
	 */
	public function parse_webhook( array $headers, string $raw_body ): WebhookEvent;

	/**
	 * Verify webhook authenticity (signature / secret).
	 *
	 * @param array<string, string> $headers HTTP headers.
	 * @param string                $raw_body Raw body.
	 * @return bool
	 */
	public function verify_webhook_signature( array $headers, string $raw_body ): bool;
}
