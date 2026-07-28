<?php
/**
 * Payment result DTO.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Dto;

defined( 'ABSPATH' ) || exit;

/**
 * Normalized outcome of a provider payment operation.
 *
 * Independent of WooCommerce so providers/services stay testable in isolation.
 */
final class PaymentResult {

	/**
	 * @var bool
	 */
	private $success;

	/**
	 * Provider payment / charge id.
	 *
	 * @var string
	 */
	private $provider_payment_id;

	/**
	 * Internal status key (e.g. pending, paid, failed).
	 *
	 * @var string
	 */
	private $status;

	/**
	 * Optional redirect URL (hosted checkout / 3DS).
	 *
	 * @var string
	 */
	private $redirect_url;

	/**
	 * Human-readable message (safe for customers when intended).
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Raw provider payload for debugging (never log secrets from here blindly).
	 *
	 * @var array<string, mixed>
	 */
	private $raw;

	/**
	 * @param bool                 $success             Whether the operation succeeded.
	 * @param string               $provider_payment_id Provider reference id.
	 * @param string               $status              Normalized status key.
	 * @param string               $redirect_url        Optional redirect.
	 * @param string               $message             Optional message.
	 * @param array<string, mixed> $raw                 Raw provider data.
	 */
	public function __construct(
		bool $success,
		string $provider_payment_id = '',
		string $status = '',
		string $redirect_url = '',
		string $message = '',
		array $raw = array()
	) {
		$this->success             = $success;
		$this->provider_payment_id = $provider_payment_id;
		$this->status              = $status;
		$this->redirect_url        = $redirect_url;
		$this->message             = $message;
		$this->raw                 = $raw;
	}

	/**
	 * @return bool
	 */
	public function is_success(): bool {
		return $this->success;
	}

	/**
	 * @return string
	 */
	public function get_provider_payment_id(): string {
		return $this->provider_payment_id;
	}

	/**
	 * @return string
	 */
	public function get_status(): string {
		return $this->status;
	}

	/**
	 * @return string
	 */
	public function get_redirect_url(): string {
		return $this->redirect_url;
	}

	/**
	 * @return string
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_raw(): array {
		return $this->raw;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'success'             => $this->success,
			'provider_payment_id' => $this->provider_payment_id,
			'status'              => $this->status,
			'redirect_url'        => $this->redirect_url,
			'message'             => $this->message,
			'raw'                 => $this->raw,
		);
	}
}
