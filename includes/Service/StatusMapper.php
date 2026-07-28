<?php
/**
 * Maps provider statuses to WooCommerce order statuses.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Service;

defined( 'ABSPATH' ) || exit;

/**
 * StatusMapper — provider status key → WooCommerce order status (without wc- prefix).
 */
class StatusMapper {

	/**
	 * Default map used by the boilerplate.
	 *
	 * @var array<string, string>
	 */
	private $map;

	/**
	 * @param array<string, string>|null $map Optional override map.
	 */
	public function __construct( ?array $map = null ) {
		$this->map = null !== $map ? $map : self::default_map();
	}

	/**
	 * Default provider → Woo status mapping.
	 *
	 * @return array<string, string>
	 */
	public static function default_map(): array {
		return array(
			'pending'   => 'on-hold',
			'created'   => 'on-hold',
			'authorized'=> 'on-hold',
			'paid'      => 'processing',
			'captured'  => 'processing',
			'failed'    => 'failed',
			'cancelled' => 'cancelled',
			'canceled'  => 'cancelled',
			'refunded'  => 'refunded',
		);
	}

	/**
	 * Map a provider status to a WooCommerce order status slug.
	 *
	 * @param string $provider_status Provider status key.
	 * @return string Woo status (e.g. processing). Empty string if unknown.
	 */
	public function to_woo_status( string $provider_status ): string {
		$key = strtolower( trim( $provider_status ) );

		return isset( $this->map[ $key ] ) ? $this->map[ $key ] : '';
	}

	/**
	 * Whether the provider status is known.
	 *
	 * @param string $provider_status Provider status key.
	 * @return bool
	 */
	public function has( string $provider_status ): bool {
		return '' !== $this->to_woo_status( $provider_status );
	}

	/**
	 * @return array<string, string>
	 */
	public function all(): array {
		return $this->map;
	}
}
