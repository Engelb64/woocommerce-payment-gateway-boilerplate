<?php
/**
 * Plugin Name:       WooCommerce Payment Gateway Boilerplate
 * Plugin URI:        https://github.com/Engelb64/woocommerce-payment-gateway-boilerplate
 * Description:       Modular boilerplate for WooCommerce payment gateways and orchestrators.
 * Version:           0.7.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Engelbert Jesus Bracho Ramirez
 * Author URI:        https://github.com/Engelb64
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       wc-payment-gateway-boilerplate
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * @package WCGatewayBoilerplate
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_GATEWAY_BOILERPLATE_VERSION', '0.7.0' );
define( 'WC_GATEWAY_BOILERPLATE_FILE', __FILE__ );
define( 'WC_GATEWAY_BOILERPLATE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_GATEWAY_BOILERPLATE_URL', plugin_dir_url( __FILE__ ) );

$wc_gateway_boilerplate_autoload = WC_GATEWAY_BOILERPLATE_PATH . 'vendor/autoload.php';

if ( file_exists( $wc_gateway_boilerplate_autoload ) ) {
	require_once $wc_gateway_boilerplate_autoload;
} else {
	/**
	 * Fallback PSR-4 autoloader when Composer vendor/ is missing.
	 *
	 * @param string $class Fully qualified class name.
	 */
	spl_autoload_register(
		static function ( $class ) {
			$prefix = 'WCGatewayBoilerplate\\';

			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}

			$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
			$file     = WC_GATEWAY_BOILERPLATE_PATH . 'includes/' . $relative . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);
}

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_orders_table',
				WC_GATEWAY_BOILERPLATE_FILE,
				true
			);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				WC_GATEWAY_BOILERPLATE_FILE,
				true
			);
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}

					echo '<div class="notice notice-error"><p>';
					echo esc_html__(
						'WooCommerce Payment Gateway Boilerplate requires WooCommerce to be installed and active.',
						'wc-payment-gateway-boilerplate'
					);
					echo '</p></div>';
				}
			);
			return;
		}

		\WCGatewayBoilerplate\Plugin::instance()->init();
	},
	20
);
