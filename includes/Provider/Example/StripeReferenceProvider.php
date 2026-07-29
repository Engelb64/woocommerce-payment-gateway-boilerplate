<?php
/**
 * REFERENCE adapter — Stripe Checkout Sessions (example only).
 *
 * Safe to delete when you implement your own orchestrator provider.
 * This is not the product of this boilerplate; it shows a complete
 * ProviderInterface implementation against a real API.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Provider\Example;

use WCGatewayBoilerplate\Dto\PaymentResult;
use WCGatewayBoilerplate\Dto\WebhookEvent;
use WCGatewayBoilerplate\Provider\AbstractProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Example Stripe adapter (Checkout Session redirect).
 *
 * Copy patterns from here into your own Provider (DEUNA, Acme, …), then remove this class.
 *
 * Config keys:
 * - api_key (secret key sk_test_… / sk_live_…)
 * - webhook_secret (whsec_…)
 * - sandbox (bool, informational — keys determine environment)
 */
class StripeReferenceProvider extends AbstractProvider {

	private const API_BASE = 'https://api.stripe.com/v1';

	/**
	 * {@inheritdoc}
	 */
	public function create_payment( array $order_data ): PaymentResult {
		$api_key = (string) $this->get_config( 'api_key', '' );
		if ( '' === $api_key ) {
			return $this->fail_result( '', 'Stripe API key is missing.' );
		}

		$order_id   = isset( $order_data['order_id'] ) ? (string) $order_data['order_id'] : '0';
		$amount     = isset( $order_data['amount'] ) ? (float) $order_data['amount'] : 0.0;
		$currency   = isset( $order_data['currency'] ) ? strtolower( (string) $order_data['currency'] ) : 'usd';
		$return_url = isset( $order_data['return_url'] ) ? (string) $order_data['return_url'] : '';
		$cancel_url = isset( $order_data['cancel_url'] ) ? (string) $order_data['cancel_url'] : $return_url;

		if ( $amount <= 0 || '' === $return_url ) {
			return $this->fail_result( '', 'Invalid amount or return_url for Stripe Checkout.' );
		}

		$unit_amount = $this->to_stripe_amount( $amount, $currency );
		$product     = sprintf( 'Order #%s', $order_id );

		$body = array(
			'mode'                                   => 'payment',
			'success_url'                            => $return_url,
			'cancel_url'                             => $cancel_url ? $cancel_url : $return_url,
			'client_reference_id'                    => $order_id,
			'metadata[order_id]'                     => $order_id,
			'line_items[0][price_data][currency]'    => $currency,
			'line_items[0][price_data][product_data][name]' => $product,
			'line_items[0][price_data][unit_amount]' => (string) $unit_amount,
			'line_items[0][quantity]'                => '1',
		);

		if ( ! empty( $order_data['customer']['email'] ) ) {
			$body['customer_email'] = (string) $order_data['customer']['email'];
		}

		try {
			$response = $this->api_request( 'POST', '/checkout/sessions', $body );
		} catch ( \Throwable $e ) {
			return $this->fail_result( '', $e->getMessage() );
		}

		if ( $response['status'] < 200 || $response['status'] >= 300 ) {
			return $this->fail_result( '', $this->extract_error_message( $response['body'] ), $response['body'] );
		}

		$payload      = $this->decode_json( $response['body'] );
		$session_id   = isset( $payload['id'] ) ? (string) $payload['id'] : '';
		$checkout_url = isset( $payload['url'] ) ? (string) $payload['url'] : '';

		if ( '' === $session_id || '' === $checkout_url ) {
			return $this->fail_result( $session_id, 'Stripe Checkout Session response incomplete.', $payload );
		}

		return new PaymentResult(
			true,
			$session_id,
			'pending',
			$checkout_url,
			'Redirecting to Stripe Checkout.',
			$payload
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function refund_payment( string $provider_payment_id, float $amount, string $currency ): PaymentResult {
		if ( '' === $provider_payment_id || $amount <= 0 ) {
			return $this->fail_result( $provider_payment_id, 'Invalid payment id or amount for Stripe refund.' );
		}

		try {
			$payment_intent = $this->resolve_payment_intent_id( $provider_payment_id );
		} catch ( \Throwable $e ) {
			return $this->fail_result( $provider_payment_id, $e->getMessage() );
		}

		if ( '' === $payment_intent ) {
			return $this->fail_result( $provider_payment_id, 'Could not resolve Stripe PaymentIntent for refund.' );
		}

		$body = array(
			'payment_intent' => $payment_intent,
			'amount'         => (string) $this->to_stripe_amount( $amount, strtolower( $currency ) ),
		);

		try {
			$response = $this->api_request( 'POST', '/refunds', $body );
		} catch ( \Throwable $e ) {
			return $this->fail_result( $provider_payment_id, $e->getMessage() );
		}

		if ( $response['status'] < 200 || $response['status'] >= 300 ) {
			return $this->fail_result( $provider_payment_id, $this->extract_error_message( $response['body'] ), $response['body'] );
		}

		$payload = $this->decode_json( $response['body'] );

		return new PaymentResult(
			true,
			$payment_intent,
			'refunded',
			'',
			'Stripe refund created.',
			$payload
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_payment( string $provider_payment_id ): PaymentResult {
		if ( '' === $provider_payment_id ) {
			return $this->fail_result( '', 'Missing Stripe payment id.' );
		}

		$path = 0 === strpos( $provider_payment_id, 'cs_' )
			? '/checkout/sessions/' . rawurlencode( $provider_payment_id )
			: '/payment_intents/' . rawurlencode( $provider_payment_id );

		try {
			$response = $this->api_request( 'GET', $path );
		} catch ( \Throwable $e ) {
			return $this->fail_result( $provider_payment_id, $e->getMessage() );
		}

		if ( $response['status'] < 200 || $response['status'] >= 300 ) {
			return $this->fail_result( $provider_payment_id, $this->extract_error_message( $response['body'] ), $response['body'] );
		}

		$payload = $this->decode_json( $response['body'] );
		$status  = $this->map_stripe_object_status( $payload );

		return new PaymentResult(
			'failed' !== $status && 'canceled' !== $status,
			$provider_payment_id,
			$status,
			isset( $payload['url'] ) ? (string) $payload['url'] : '',
			'Stripe payment retrieved.',
			$payload
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function parse_webhook( array $headers, string $raw_body ): WebhookEvent {
		$payload  = $this->decode_json( $raw_body );
		$type     = isset( $payload['type'] ) ? (string) $payload['type'] : 'unknown';
		$event_id = isset( $payload['id'] ) ? (string) $payload['id'] : ( 'stripe_evt_' . md5( $raw_body ) );
		$object   = isset( $payload['data']['object'] ) && is_array( $payload['data']['object'] )
			? $payload['data']['object']
			: array();

		$order_id = null;
		if ( isset( $object['client_reference_id'] ) && is_numeric( $object['client_reference_id'] ) ) {
			$order_id = (int) $object['client_reference_id'];
		} elseif ( isset( $object['metadata']['order_id'] ) && is_numeric( $object['metadata']['order_id'] ) ) {
			$order_id = (int) $object['metadata']['order_id'];
		}

		$payment_id = '';
		if ( isset( $object['payment_intent'] ) && is_string( $object['payment_intent'] ) ) {
			$payment_id = $object['payment_intent'];
		} elseif ( isset( $object['id'] ) && is_string( $object['id'] ) ) {
			$payment_id = $object['id'];
		}

		$status = $this->map_event_type_to_status( $type, $object );

		return new WebhookEvent(
			$event_id,
			$type,
			$payment_id,
			$status,
			$order_id,
			$payload
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function verify_webhook_signature( array $headers, string $raw_body ): bool {
		$secret = (string) $this->get_config( 'webhook_secret', '' );
		if ( '' === $secret ) {
			return false;
		}

		$header = $this->find_header( $headers, 'Stripe-Signature' );
		if ( '' === $header ) {
			return false;
		}

		$parts = array();
		foreach ( explode( ',', $header ) as $item ) {
			$kv = explode( '=', trim( $item ), 2 );
			if ( 2 === count( $kv ) ) {
				$parts[ $kv[0] ][] = $kv[1];
			}
		}

		if ( empty( $parts['t'][0] ) || empty( $parts['v1'] ) ) {
			return false;
		}

		$timestamp = (int) $parts['t'][0];
		if ( abs( time() - $timestamp ) > 300 ) {
			return false;
		}

		$signed_payload = $timestamp . '.' . $raw_body;
		$expected       = hash_hmac( 'sha256', $signed_payload, $secret );

		foreach ( $parts['v1'] as $candidate ) {
			if ( $this->hash_equals_safe( $expected, (string) $candidate ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Perform a Stripe API call (form-urlencoded).
	 *
	 * @param string                $method HTTP method.
	 * @param string                $path   API path starting with /.
	 * @param array<string, string> $body  Form fields (POST only).
	 * @return array{status:int,headers:array<string,mixed>,body:string}
	 */
	private function api_request( string $method, string $path, array $body = array() ): array {
		if ( null === $this->http ) {
			throw new \RuntimeException( 'HTTP client is not configured for StripeReferenceProvider.' );
		}

		$api_key = (string) $this->get_config( 'api_key', '' );
		$options = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
			),
			'timeout' => 30,
		);

		if ( ! empty( $body ) ) {
			$options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
			$options['body']                    = http_build_query( $body );
		}

		return $this->http->request( $method, self::API_BASE . $path, $options );
	}

	/**
	 * Resolve a PaymentIntent id from a session or intent id.
	 *
	 * @param string $provider_payment_id cs_… or pi_….
	 * @return string
	 */
	private function resolve_payment_intent_id( string $provider_payment_id ): string {
		if ( 0 === strpos( $provider_payment_id, 'pi_' ) ) {
			return $provider_payment_id;
		}

		if ( 0 !== strpos( $provider_payment_id, 'cs_' ) ) {
			return '';
		}

		$response = $this->api_request( 'GET', '/checkout/sessions/' . rawurlencode( $provider_payment_id ) );
		if ( $response['status'] < 200 || $response['status'] >= 300 ) {
			throw new \RuntimeException( esc_html( $this->extract_error_message( $response['body'] ) ) );
		}

		$payload = $this->decode_json( $response['body'] );
		if ( isset( $payload['payment_intent'] ) && is_string( $payload['payment_intent'] ) ) {
			return $payload['payment_intent'];
		}

		return '';
	}

	/**
	 * Convert major units to Stripe minor units (cents for most currencies).
	 *
	 * @param float  $amount   Amount in major units.
	 * @param string $currency Lowercase currency code.
	 * @return int
	 */
	private function to_stripe_amount( float $amount, string $currency ): int {
		$zero_decimal = array( 'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf' );
		if ( in_array( strtolower( $currency ), $zero_decimal, true ) ) {
			return (int) round( $amount );
		}

		return (int) round( $amount * 100 );
	}

	/**
	 * Map Stripe event type (+ object) to boilerplate status.
	 *
	 * @param string               $type   Event type.
	 * @param array<string, mixed> $object Event object.
	 * @return string
	 */
	private function map_event_type_to_status( string $type, array $object ): string {
		switch ( $type ) {
			case 'checkout.session.completed':
			case 'checkout.session.async_payment_succeeded':
			case 'payment_intent.succeeded':
			case 'charge.succeeded':
				return 'paid';
			case 'checkout.session.expired':
			case 'payment_intent.canceled':
				return 'cancelled';
			case 'checkout.session.async_payment_failed':
			case 'payment_intent.payment_failed':
			case 'charge.failed':
				return 'failed';
			case 'charge.refunded':
			case 'refund.created':
			case 'refund.updated':
				return 'refunded';
			default:
				return $this->map_stripe_object_status( $object );
		}
	}

	/**
	 * Map a Stripe object status field to boilerplate status.
	 *
	 * @param array<string, mixed> $object Stripe object.
	 * @return string
	 */
	private function map_stripe_object_status( array $object ): string {
		$status         = isset( $object['status'] ) ? strtolower( (string) $object['status'] ) : '';
		$payment_status = isset( $object['payment_status'] ) ? strtolower( (string) $object['payment_status'] ) : '';

		if ( 'paid' === $payment_status || 'complete' === $status || 'succeeded' === $status ) {
			return 'paid';
		}
		if ( 'unpaid' === $payment_status || 'open' === $status || 'requires_payment_method' === $status || 'requires_action' === $status || 'processing' === $status ) {
			return 'pending';
		}
		if ( 'expired' === $status || 'canceled' === $status ) {
			return 'cancelled';
		}
		if ( 'failed' === $status ) {
			return 'failed';
		}

		return $status ? $status : 'pending';
	}

	/**
	 * @param string                      $payment_id Payment id.
	 * @param string                      $message    Message.
	 * @param array<string, mixed>|string $raw Raw payload.
	 * @return PaymentResult
	 */
	private function fail_result( string $payment_id, string $message, $raw = array() ): PaymentResult {
		if ( is_string( $raw ) ) {
			$raw = $this->decode_json( $raw );
		}

		return new PaymentResult( false, $payment_id, 'failed', '', $message, is_array( $raw ) ? $raw : array() );
	}

	/**
	 * @param string $body Response body.
	 * @return string
	 */
	private function extract_error_message( string $body ): string {
		$payload = $this->decode_json( $body );
		if ( isset( $payload['error']['message'] ) ) {
			return (string) $payload['error']['message'];
		}

		return 'Stripe API request failed.';
	}

	/**
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
