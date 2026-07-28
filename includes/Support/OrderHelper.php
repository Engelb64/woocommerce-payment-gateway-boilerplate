<?php
/**
 * Order meta helpers.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Support;

defined( 'ABSPATH' ) || exit;

/**
 * OrderHelper — read/write payment meta and build provider payloads from WC orders.
 *
 * Safe to construct without WooCommerce; WC methods no-op / return defaults when unavailable.
 */
class OrderHelper {

	public const META_PROVIDER_PAYMENT_ID = '_wc_gateway_boilerplate_payment_id';
	public const META_PROVIDER_STATUS     = '_wc_gateway_boilerplate_provider_status';
	public const META_PROCESSED_EVENTS    = '_wc_gateway_boilerplate_processed_events';

	/**
	 * Build normalized order_data array for ProviderInterface::create_payment.
	 *
	 * @param object|null          $order   WC_Order-like object (duck typed).
	 * @param array<string, mixed> $extra   Extra fields (return_url, force_fail, ...).
	 * @return array<string, mixed>
	 */
	public function build_order_data( $order = null, array $extra = array() ): array {
		$data = array(
			'order_id' => 0,
			'amount'   => 0.0,
			'currency' => 'USD',
			'customer' => array(),
		);

		if ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
			$data['order_id'] = (int) $order->get_id();

			if ( method_exists( $order, 'get_total' ) ) {
				$data['amount'] = (float) $order->get_total();
			}

			if ( method_exists( $order, 'get_currency' ) ) {
				$data['currency'] = (string) $order->get_currency();
			}

			$data['customer'] = array(
				'email' => method_exists( $order, 'get_billing_email' ) ? (string) $order->get_billing_email() : '',
				'name'  => method_exists( $order, 'get_formatted_billing_full_name' ) ? (string) $order->get_formatted_billing_full_name() : '',
			);
		}

		return array_merge( $data, $extra );
	}

	/**
	 * Persist provider payment id on the order.
	 *
	 * @param object|null $order      WC_Order-like.
	 * @param string      $payment_id Provider payment id.
	 * @return void
	 */
	public function set_provider_payment_id( $order, string $payment_id ): void {
		if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) ) {
			return;
		}

		$order->update_meta_data( self::META_PROVIDER_PAYMENT_ID, $payment_id );

		if ( method_exists( $order, 'save' ) ) {
			$order->save();
		}
	}

	/**
	 * @param object|null $order WC_Order-like.
	 * @return string
	 */
	public function get_provider_payment_id( $order ): string {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return '';
		}

		return (string) $order->get_meta( self::META_PROVIDER_PAYMENT_ID, true );
	}

	/**
	 * Store last known provider status.
	 *
	 * @param object|null $order           WC_Order-like.
	 * @param string      $provider_status Provider status key.
	 * @return void
	 */
	public function set_provider_status( $order, string $provider_status ): void {
		if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) ) {
			return;
		}

		$order->update_meta_data( self::META_PROVIDER_STATUS, $provider_status );

		if ( method_exists( $order, 'save' ) ) {
			$order->save();
		}
	}

	/**
	 * Apply a WooCommerce order status if possible.
	 *
	 * @param object|null $order      WC_Order-like.
	 * @param string      $woo_status Status slug without wc- prefix.
	 * @param string      $note       Optional order note.
	 * @return bool True if status was applied.
	 */
	public function apply_woo_status( $order, string $woo_status, string $note = '' ): bool {
		if ( '' === $woo_status || ! is_object( $order ) || ! method_exists( $order, 'update_status' ) ) {
			return false;
		}

		$order->update_status( $woo_status, $note );
		return true;
	}

	/**
	 * Idempotency: has this webhook event already been processed?
	 *
	 * @param object|null $order    WC_Order-like.
	 * @param string      $event_id Provider event id.
	 * @return bool
	 */
	public function has_processed_event( $order, string $event_id ): bool {
		if ( '' === $event_id || ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return false;
		}

		$events = $order->get_meta( self::META_PROCESSED_EVENTS, true );
		$events = is_array( $events ) ? $events : array();

		return in_array( $event_id, $events, true );
	}

	/**
	 * Mark webhook event as processed.
	 *
	 * @param object|null $order    WC_Order-like.
	 * @param string      $event_id Provider event id.
	 * @return void
	 */
	public function mark_event_processed( $order, string $event_id ): void {
		if ( '' === $event_id || ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) || ! method_exists( $order, 'update_meta_data' ) ) {
			return;
		}

		$events = $order->get_meta( self::META_PROCESSED_EVENTS, true );
		$events = is_array( $events ) ? $events : array();

		if ( ! in_array( $event_id, $events, true ) ) {
			$events[] = $event_id;
			$order->update_meta_data( self::META_PROCESSED_EVENTS, $events );

			if ( method_exists( $order, 'save' ) ) {
				$order->save();
			}
		}
	}

	/**
	 * Resolve a WC order by id when WooCommerce is available.
	 *
	 * @param int $order_id Order id.
	 * @return object|null
	 */
	public function get_order( int $order_id ) {
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order = wc_get_order( $order_id );

		return $order ? $order : null;
	}
}
