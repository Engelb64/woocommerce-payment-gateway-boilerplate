<?php
/**
 * Plugin bootstrap.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class (singleton).
 *
 * Boots textdomain and extension hook. Gateway + PaymentService wire-up comes in later versions.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {}

	/**
	 * Boot plugin hooks.
	 *
	 * @return void
	 */
	public function init() {
		$this->load_textdomain();

		/**
		 * Fires after the boilerplate plugin has initialized.
		 *
		 * Useful for extensions / future provider wiring.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'wc_gateway_boilerplate_init', $this );
	}

	/**
	 * Load plugin translations based on the site locale.
	 *
	 * @return void
	 */
	private function load_textdomain() {
		load_plugin_textdomain(
			'wc-payment-gateway-boilerplate',
			false,
			dirname( plugin_basename( WC_GATEWAY_BOILERPLATE_FILE ) ) . '/languages'
		);
	}
}
