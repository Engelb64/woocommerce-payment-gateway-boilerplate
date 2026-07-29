<?php
/**
 * Base WooCommerce payment gateway.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Gateway;

use WCGatewayBoilerplate\Plugin;
use WCGatewayBoilerplate\Webhook\WebhookHandler;

defined( 'ABSPATH' ) || exit;

/**
 * AbstractGateway — WC_Payment_Gateway wired to PaymentService.
 *
 * Extend / rename when building a real provider plugin from this boilerplate.
 */
class AbstractGateway extends \WC_Payment_Gateway {

	/**
	 * Gateway id used in WooCommerce settings and wc-api endpoints.
	 */
	public const GATEWAY_ID = 'wc_gateway_boilerplate';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = self::GATEWAY_ID;
		$this->method_title       = __( 'Payment Gateway Boilerplate', 'wc-payment-gateway-boilerplate' );
		$this->method_description = __( 'Skeleton for custom WooCommerce payment orchestrators. Default: StubProvider. Optional Stripe class under Provider/Example is a deletable reference only.', 'wc-payment-gateway-boilerplate' );
		$this->has_fields         = false;
		$this->supports           = array( 'products', 'refunds' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', $this->method_title );
		$this->description = $this->get_option( 'description', '' );
		$this->enabled     = $this->get_option( 'enabled', 'no' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Admin settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'          => array(
				'title'   => __( 'Enable/Disable', 'wc-payment-gateway-boilerplate' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Payment Gateway Boilerplate', 'wc-payment-gateway-boilerplate' ),
				'default' => 'no',
			),
			'title'            => array(
				'title'       => __( 'Title', 'wc-payment-gateway-boilerplate' ),
				'type'        => 'text',
				'description' => __( 'Payment method title shown at checkout.', 'wc-payment-gateway-boilerplate' ),
				'default'     => __( 'Boilerplate Payment', 'wc-payment-gateway-boilerplate' ),
				'desc_tip'    => true,
			),
			'description'      => array(
				'title'       => __( 'Description', 'wc-payment-gateway-boilerplate' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description shown at checkout.', 'wc-payment-gateway-boilerplate' ),
				'default'     => __( 'Pay using the boilerplate payment provider.', 'wc-payment-gateway-boilerplate' ),
				'desc_tip'    => true,
			),
			'active_provider'  => array(
				'title'       => __( 'Active provider', 'wc-payment-gateway-boilerplate' ),
				'type'        => 'select',
				'description' => __( 'Stub is the default for this skeleton. “Stripe reference” is an optional teaching adapter (Provider/Example) — replace with your orchestrator for real projects.', 'wc-payment-gateway-boilerplate' ),
				'default'     => 'stub',
				'options'     => array(
					'stub'             => __( 'Stub (default — local simulation)', 'wc-payment-gateway-boilerplate' ),
					'stripe_reference' => __( 'Reference only: Stripe Checkout (example)', 'wc-payment-gateway-boilerplate' ),
				),
				'desc_tip'    => true,
			),
			'sandbox'          => array(
				'title'   => __( 'Sandbox', 'wc-payment-gateway-boilerplate' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable sandbox / test mode (for your provider or the Stripe reference)', 'wc-payment-gateway-boilerplate' ),
				'default' => 'yes',
			),
			'api_key'          => array(
				'title'       => __( 'API key / Secret key', 'wc-payment-gateway-boilerplate' ),
				'type'        => 'password',
				'description' => __( 'Used by real/reference providers (e.g. Stripe sk_test_…). Unused by StubProvider.', 'wc-payment-gateway-boilerplate' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'webhook_secret'   => array(
				'title'       => __( 'Webhook secret', 'wc-payment-gateway-boilerplate' ),
				'type'        => 'password',
				'description' => __( 'Stub: HMAC secret (default stub_secret). Stripe reference: whsec_…. Your provider: whatever its docs require.', 'wc-payment-gateway-boilerplate' ),
				'default'     => 'stub_secret',
				'desc_tip'    => true,
			),
			'logging'          => array(
				'title'   => __( 'Logging', 'wc-payment-gateway-boilerplate' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable debug logging (WooCommerce → Status → Logs)', 'wc-payment-gateway-boilerplate' ),
				'default' => 'yes',
			),
			'simulate_failure' => array(
				'title'       => __( 'Simulate failure (stub)', 'wc-payment-gateway-boilerplate' ),
				'type'        => 'checkbox',
				'label'       => __( 'Force StubProvider to fail create_payment (for testing)', 'wc-payment-gateway-boilerplate' ),
				'default'     => 'no',
				'description' => __( 'Only affects StubProvider.', 'wc-payment-gateway-boilerplate' ),
			),
			'webhook_url'      => array(
				'title'       => __( 'Webhook URL', 'wc-payment-gateway-boilerplate' ),
				'type'        => 'title',
				'description' => sprintf(
					/* translators: %s: webhook URL */
					__( 'Send provider webhooks to: %s — For the Stripe reference locally, forward with Stripe CLI.', 'wc-payment-gateway-boilerplate' ),
					'<code>' . esc_html( WebhookHandler::get_url() ) . '</code>'
				),
			),
		);
	}

	/**
	 * Process payment at checkout.
	 *
	 * @param int $order_id Order id.
	 * @return array<string, string>
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice( __( 'Order not found.', 'wc-payment-gateway-boilerplate' ), 'error' );
			return array( 'result' => 'fail' );
		}

		$extra = array(
			'return_url' => $this->get_return_url( $order ),
			'cancel_url' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : $this->get_return_url( $order ),
		);

		if ( 'stub' === $this->get_option( 'active_provider', 'stub' ) && 'yes' === $this->get_option( 'simulate_failure', 'no' ) ) {
			$extra['force_fail'] = true;
		}

		try {
			$outcome = Plugin::instance()->get_payment_service()->create( $extra, $order );
		} catch ( \Throwable $e ) {
			Plugin::instance()->get_logger()->error(
				'process_payment exception',
				array( 'message' => $e->getMessage() )
			);
			wc_add_notice( __( 'Payment processing error. Please try again.', 'wc-payment-gateway-boilerplate' ), 'error' );
			return array( 'result' => 'fail' );
		}

		$result = $outcome['result'];

		if ( ! $result->is_success() ) {
			$message = $result->get_message()
				? $result->get_message()
				: __( 'Payment failed.', 'wc-payment-gateway-boilerplate' );
			wc_add_notice( $message, 'error' );
			return array( 'result' => 'fail' );
		}

		$redirect = $result->get_redirect_url()
			? $result->get_redirect_url()
			: $this->get_return_url( $order );

		return array(
			'result'   => 'success',
			'redirect' => $redirect,
		);
	}

	/**
	 * Process refund from WooCommerce admin.
	 *
	 * @param int        $order_id Order id.
	 * @param float|null $amount   Refund amount.
	 * @param string     $reason   Reason.
	 * @return bool|\WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_Error( 'wc_gateway_boilerplate_refund', __( 'Order not found.', 'wc-payment-gateway-boilerplate' ) );
		}

		$amount = null !== $amount ? (float) $amount : (float) $order->get_total();

		if ( $amount <= 0 ) {
			return new \WP_Error( 'wc_gateway_boilerplate_refund', __( 'Invalid refund amount.', 'wc-payment-gateway-boilerplate' ) );
		}

		try {
			$outcome = Plugin::instance()->get_payment_service()->refund(
				'',
				$amount,
				$order->get_currency(),
				$order
			);
		} catch ( \Throwable $e ) {
			Plugin::instance()->get_logger()->error(
				'process_refund exception',
				array( 'message' => $e->getMessage() )
			);
			return new \WP_Error( 'wc_gateway_boilerplate_refund', $e->getMessage() );
		}

		$result = $outcome['result'];

		if ( ! $result->is_success() ) {
			return new \WP_Error(
				'wc_gateway_boilerplate_refund',
				$result->get_message() ? $result->get_message() : __( 'Refund failed.', 'wc-payment-gateway-boilerplate' )
			);
		}

		if ( $reason ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: refund reason */
					__( 'Refund reason: %s', 'wc-payment-gateway-boilerplate' ),
					$reason
				)
			);
		}

		return true;
	}
}
