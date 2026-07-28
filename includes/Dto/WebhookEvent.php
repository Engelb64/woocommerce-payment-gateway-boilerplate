<?php
/**
 * Webhook event DTO.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Dto;

defined( 'ABSPATH' ) || exit;

/**
 * Normalized provider webhook event.
 */
final class WebhookEvent {

	/**
	 * Provider event id (for idempotency).
	 *
	 * @var string
	 */
	private $event_id;

	/**
	 * Event type (e.g. payment.paid, payment.failed).
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Related provider payment id.
	 *
	 * @var string
	 */
	private $provider_payment_id;

	/**
	 * Normalized status key for StatusMapper.
	 *
	 * @var string
	 */
	private $status;

	/**
	 * Optional Woo order id if the provider sends it.
	 *
	 * @var int|null
	 */
	private $order_id;

	/**
	 * Raw payload.
	 *
	 * @var array<string, mixed>
	 */
	private $raw;

	/**
	 * @param string               $event_id            Unique event id.
	 * @param string               $type                Event type.
	 * @param string               $provider_payment_id Provider payment id.
	 * @param string               $status              Normalized status.
	 * @param int|null             $order_id            Optional order id.
	 * @param array<string, mixed> $raw                 Raw payload.
	 */
	public function __construct(
		string $event_id,
		string $type,
		string $provider_payment_id,
		string $status,
		?int $order_id = null,
		array $raw = array()
	) {
		$this->event_id            = $event_id;
		$this->type                = $type;
		$this->provider_payment_id = $provider_payment_id;
		$this->status              = $status;
		$this->order_id            = $order_id;
		$this->raw                 = $raw;
	}

	/**
	 * @return string
	 */
	public function get_event_id(): string {
		return $this->event_id;
	}

	/**
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
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
	 * @return int|null
	 */
	public function get_order_id(): ?int {
		return $this->order_id;
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
			'event_id'            => $this->event_id,
			'type'                => $this->type,
			'provider_payment_id' => $this->provider_payment_id,
			'status'              => $this->status,
			'order_id'            => $this->order_id,
			'raw'                 => $this->raw,
		);
	}
}
