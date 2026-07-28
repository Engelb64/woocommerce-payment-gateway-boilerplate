<?php
/**
 * WooCommerce Blocks payment method integration.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Gateway;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit;

/**
 * BlocksSupport — registers the gateway with Checkout Blocks.
 */
final class BlocksSupport extends AbstractPaymentMethodType {

	/**
	 * Payment method name / gateway id.
	 *
	 * @var string
	 */
	protected $name = AbstractGateway::GATEWAY_ID;

	/**
	 * Gateway settings.
	 *
	 * @var array<string, mixed>
	 */
	protected $settings = array();

	/**
	 * {@inheritdoc}
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . AbstractGateway::GATEWAY_ID . '_settings', array() );
		if ( ! is_array( $this->settings ) ) {
			$this->settings = array();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_active() {
		return isset( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		$handle = 'wc-gateway-boilerplate-blocks';
		$src    = WC_GATEWAY_BOILERPLATE_URL . 'assets/js/blocks.js';
		$deps   = array(
			'wc-blocks-registry',
			'wc-settings',
			'wp-element',
			'wp-html-entities',
			'wp-i18n',
		);

		wp_register_script(
			$handle,
			$src,
			$deps,
			WC_GATEWAY_BOILERPLATE_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				$handle,
				'wc-payment-gateway-boilerplate',
				WC_GATEWAY_BOILERPLATE_PATH . 'languages'
			);
		}

		$style_handle = 'wc-gateway-boilerplate-blocks-style';
		wp_register_style(
			$style_handle,
			WC_GATEWAY_BOILERPLATE_URL . 'assets/css/gateway.css',
			array(),
			WC_GATEWAY_BOILERPLATE_VERSION
		);
		wp_enqueue_style( $style_handle );

		return array( $handle );
	}

	/**
	 * Data passed to the Blocks frontend script via wcSettings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'Boilerplate Payment', 'wc-payment-gateway-boilerplate' ),
			'description' => isset( $this->settings['description'] ) ? $this->settings['description'] : '',
			'supports'    => array( 'products', 'refunds' ),
		);
	}
}
