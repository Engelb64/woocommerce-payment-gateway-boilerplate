<?php
/**
 * Deterministic stub provider for local development.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Provider;

use WCGatewayBoilerplate\Dto\PaymentResult;
use WCGatewayBoilerplate\Dto\WebhookEvent;

defined( 'ABSPATH' ) || exit;

/**
 * Fake provider — no external HTTP calls.
 *
 * Rules:
 * - create_payment succeeds unless order_data['force_fail'] is true.
 * - Payment ids look like stub_pay_{order_id}.
 * - Webhook signature header: X-Stub-Signature = hash_hmac('sha256', raw_body, webhook_secret).
 * - Default webhook_secret: stub_secret (override via config).
 */
class StubProvider extends AbstractProvider {

	/**
	 * {@inheritdoc}
	 */
	public function create_payment( array $order_data ): PaymentResult {
		$order_id   = isset( $order_data['order_id'] ) ? (string) $order_data['order_id'] : '0';
		$force_fail = ! empty( $order_data['force_fail'] );

		$payment_id = 'stub_pay_' . $order_id;

		if ( $force_fail ) {
			return new PaymentResult(
				false,
				$payment_id,
				'failed',
				'',
				'Stub provider forced failure.',
				array(
					'order_data' => $order_data,
				)
			);
		}

		$redirect = isset( $order_data['return_url'] ) ? (string) $order_data['return_url'] : '';

		return new PaymentResult(
			true,
			$payment_id,
			'paid',
			$redirect,
			'Stub payment created successfully.',
			array(
				'order_data' => $order_data,
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function refund_payment( string $provider_payment_id, float $amount, string $currency ): PaymentResult {
		if ( '' === $provider_payment_id || $amount <= 0 ) {
			return new PaymentResult(
				false,
				$provider_payment_id,
				'failed',
				'',
				'Stub refund rejected: invalid payment id or amount.',
				array(
					'amount'   => $amount,
					'currency' => $currency,
				)
			);
		}

		return new PaymentResult(
			true,
			$provider_payment_id,
			'refunded',
			'',
			'Stub refund completed.',
			array(
				'amount'   => $amount,
				'currency' => $currency,
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_payment( string $provider_payment_id ): PaymentResult {
		if ( 0 !== strpos( $provider_payment_id, 'stub_pay_' ) ) {
			return new PaymentResult(
				false,
				$provider_payment_id,
				'failed',
				'',
				'Stub payment not found.',
				array()
			);
		}

		return new PaymentResult(
			true,
			$provider_payment_id,
			'paid',
			'',
			'Stub payment found.',
			array()
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function parse_webhook( array $headers, string $raw_body ): WebhookEvent {
		$payload = $this->decode_json( $raw_body );

		$event_id = isset( $payload['event_id'] ) ? (string) $payload['event_id'] : 'stub_evt_' . md5( $raw_body );
		$type     = isset( $payload['type'] ) ? (string) $payload['type'] : 'payment.updated';
		$payment  = isset( $payload['provider_payment_id'] ) ? (string) $payload['provider_payment_id'] : '';
		$status   = isset( $payload['status'] ) ? (string) $payload['status'] : 'paid';
		$order_id = isset( $payload['order_id'] ) ? (int) $payload['order_id'] : null;

		return new WebhookEvent(
			$event_id,
			$type,
			$payment,
			$status,
			$order_id,
			$payload
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function verify_webhook_signature( array $headers, string $raw_body ): bool {
		$secret = (string) $this->get_config( 'webhook_secret', 'stub_secret' );
		$given  = $this->find_header( $headers, 'X-Stub-Signature' );

		if ( '' === $given ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $raw_body, $secret );

		return $this->hash_equals_safe( $expected, $given );
	}

	/**
	 * Case-insensitive header lookup.
	 *
	 * @param array<string, string> $headers Headers.
	 * @param string                $name    Header name.
	 * @return string
	 */
	private function find_header( array $headers, string $name ): string {
		foreach ( $headers as $key => $value ) {
			if ( 0 === strcasecmp( (string) $key, $name ) ) {
				return (string) $value;
			}
		}

		return '';
	}
}
