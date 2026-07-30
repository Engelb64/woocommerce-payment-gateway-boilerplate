<?php
/**
 * Maps PaymentService outcomes to headless JSON payloads (no secrets).
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Headless;

use WCGatewayBoilerplate\Dto\PaymentResult;

defined( 'ABSPATH' ) || exit;

/**
 * Pure response shaping for REST / future GraphQL resolvers.
 */
class PaymentResponseMapper {

	/**
	 * Map a create/refund PaymentService outcome.
	 *
	 * @param array{result:PaymentResult,woo_status:string} $outcome Service outcome.
	 * @return array<string, mixed>
	 */
	public function from_outcome( array $outcome ): array {
		$result = $outcome['result'];
		$woo    = isset( $outcome['woo_status'] ) ? (string) $outcome['woo_status'] : '';

		return array(
			'success'             => $result->is_success(),
			'provider_payment_id' => $result->get_provider_payment_id(),
			'provider_status'     => $result->get_status(),
			'woo_status'          => $woo,
			'redirect_url'        => $result->get_redirect_url(),
			'message'             => $result->get_message(),
		);
	}

	/**
	 * Map order payment meta for a status read.
	 *
	 * @param int    $order_id              Order id.
	 * @param string $provider_payment_id   Stored provider id.
	 * @param string $provider_status       Stored provider status.
	 * @param string $woo_status            Current Woo order status (no wc- prefix).
	 * @return array<string, mixed>
	 */
	public function from_order_meta(
		int $order_id,
		string $provider_payment_id,
		string $provider_status,
		string $woo_status
	): array {
		return array(
			'success'             => true,
			'order_id'            => $order_id,
			'provider_payment_id' => $provider_payment_id,
			'provider_status'     => $provider_status,
			'woo_status'          => $woo_status,
		);
	}

	/**
	 * Error payload (never include secrets / raw provider dumps).
	 *
	 * @param string $code    Machine code.
	 * @param string $message Human message.
	 * @param int    $status  Suggested HTTP status.
	 * @return array{success:bool,code:string,message:string,status:int}
	 */
	public function error( string $code, string $message, int $status = 400 ): array {
		return array(
			'success' => false,
			'code'    => $code,
			'message' => $message,
			'status'  => $status,
		);
	}
}
