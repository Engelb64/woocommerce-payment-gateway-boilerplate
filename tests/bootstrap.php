<?php
/**
 * PHPUnit bootstrap (no full WordPress install required).
 *
 * @package WCGatewayBoilerplate
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$autoload = $root . '/vendor/autoload.php';

if ( ! file_exists( $autoload ) ) {
	fwrite( STDERR, "Run `composer install` before tests.\n" );
	exit( 1 );
}

require_once $autoload;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

if ( ! defined( 'WC_GATEWAY_BOILERPLATE_FILE' ) ) {
	define( 'WC_GATEWAY_BOILERPLATE_FILE', $root . '/woocommerce-payment-gateway-boilerplate.php' );
}

if ( ! defined( 'WC_GATEWAY_BOILERPLATE_PATH' ) ) {
	define( 'WC_GATEWAY_BOILERPLATE_PATH', $root . '/' );
}

if ( ! defined( 'WC_GATEWAY_BOILERPLATE_URL' ) ) {
	define( 'WC_GATEWAY_BOILERPLATE_URL', 'http://example.test/wp-content/plugins/woocommerce-payment-gateway-boilerplate/' );
}

if ( ! defined( 'WC_GATEWAY_BOILERPLATE_VERSION' ) ) {
	define( 'WC_GATEWAY_BOILERPLATE_VERSION', '0.8.0' );
}

/**
 * Minimal WordPress helpers used by production classes outside Brain Monkey patches.
 */
if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data Data.
	 * @return string|false
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}
}
